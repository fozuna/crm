<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\DTOs\FinancialReceivableData;
use App\DTOs\FinancialReceiptData;
use App\Repositories\AuditLogRepository;
use App\Repositories\FinancialAuditLogRepository;
use App\Repositories\FinancialReceiptRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\FinancePaymentRepository;

final class FinancialReceivableService
{
    private FinancialReceivableRepository $receivables;
    private FinancialReceiptRepository $receipts;
    private FinancialAuditLogRepository $financialAudit;
    private AuditLogRepository $audit;
    private FinanceInstallmentRepository $legacyInstallments;
    private FinancePaymentRepository $legacyPayments;

    public function __construct()
    {
        $this->receivables = new FinancialReceivableRepository();
        $this->receipts = new FinancialReceiptRepository();
        $this->financialAudit = new FinancialAuditLogRepository();
        $this->audit = new AuditLogRepository();
        $this->legacyInstallments = new FinanceInstallmentRepository();
        $this->legacyPayments = new FinancePaymentRepository();
    }

    public function create(array $payload, int $actorId, ?string $ip = null): array
    {
        $companyId = max(1, (int) ($payload['company_id'] ?? CompanyContext::currentCompanyId()));
        return $this->transactional(function () use ($payload, $actorId, $ip, $companyId): array {
            $rows = $this->expandSchedule($payload, $actorId, $companyId);
            $created = [];
            foreach ($rows as $row) {
                $created[] = $this->createSingleReceivable($row, $actorId, $companyId, $ip);
            }
            return $created;
        });
    }

    /**
     * Cria um único título já totalmente especificado pelo chamador (installment_number/
     * total_installments/due_date/original_amount definitivos), sem passar pela expansão
     * automática de expandSchedule(). Usado pelo faturamento de Ordens de Serviço
     * (ServiceOrderBillingService), que calcula seu próprio cronograma de parcelas
     * (periodicidade e parcelamento personalizado) e não deve tê-lo re-expandido — reusa
     * a mesma validação e trilha de auditoria de create().
     */
    public function createStandalone(array $payload, int $actorId, ?string $ip = null): array
    {
        $companyId = max(1, (int) ($payload['company_id'] ?? CompanyContext::currentCompanyId()));
        return $this->transactional(function () use ($payload, $actorId, $ip, $companyId): array {
            return $this->createSingleReceivable(array_merge($payload, [
                'company_id' => $companyId,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]), $actorId, $companyId, $ip);
        });
    }

    private function createSingleReceivable(array $row, int $actorId, int $companyId, ?string $ip): array
    {
        $data = FinancialReceivableData::fromArray($row);
        $this->assertReceivableData($data);
        $id = $this->receivables->create($data);
        $created = $this->receivables->find($companyId, $id) ?? [];
        $this->writeAudit($companyId, $id, $actorId, $ip, 'create', null, $created);
        return $created;
    }

    public function update(int $companyId, int $id, array $payload, int $actorId, ?string $ip = null): array
    {
        $existing = $this->requireReceivable($companyId, $id);
        if (in_array((string) ($existing['status'] ?? ''), ['paid', 'canceled'], true)) {
            throw new \RuntimeException('Título já quitado/cancelado não pode ser alterado.');
        }
        if ((int) ($existing['service_order_id'] ?? 0) > 0) {
            // Parcelas nascidas do faturamento de uma Ordem de Serviço têm numeração/
            // quantidade controladas exclusivamente pelo fluxo de faturamento/
            // reparcelamento da OS (ServiceOrderBillingService) — nunca editáveis pela
            // tela genérica de recebíveis. Sem essa trava, "Total de parcelas" podia ser
            // digitado livremente aqui sem nenhuma relação com os títulos realmente
            // existentes (causa confirmada de uma OS de R$ 1.500 exibir "1/3" com um
            // único título de R$ 120 vinculado).
            unset($payload['installment_number'], $payload['total_installments']);
        }
        $data = FinancialReceivableData::fromArray(array_merge($existing, $payload, [
            'company_id' => $companyId,
            'updated_by' => $actorId,
            'created_by' => (int) ($existing['created_by'] ?? $actorId),
        ]));
        $this->assertReceivableData($data);

        $receivedAmount = round((float) ($existing['received_amount'] ?? 0), 2);
        $newGross = round($data->originalAmount + $data->interestAmount + $data->fineAmount - $data->discountAmount, 2);
        if ($receivedAmount > 0.0001 && $newGross < $receivedAmount - 0.0001) {
            throw new \RuntimeException(sprintf(
                'O novo valor (R$ %s) não pode ser menor que o valor já recebido (R$ %s).',
                number_format($newGross, 2, ',', '.'),
                number_format($receivedAmount, 2, ',', '.')
            ));
        }

        return $this->transactional(function () use ($companyId, $id, $data, $existing, $actorId, $ip): array {
            $this->receivables->update($id, $data, (float) ($existing['received_amount'] ?? 0));
            $current = $this->receivables->find($companyId, $id);
            $this->writeAudit($companyId, $id, $actorId, $ip, 'update', $existing, $current);
            return $current ?? $existing;
        });
    }

    public function delete(int $companyId, int $id, int $actorId, ?string $ip = null): void
    {
        $existing = $this->requireReceivable($companyId, $id);
        if ((float) ($existing['received_amount'] ?? 0) > 0.0001 || (string) ($existing['status'] ?? '') === 'paid') {
            throw new \RuntimeException('Não é permitido excluir títulos com recebimentos.');
        }

        $this->transactional(function () use ($companyId, $id, $actorId, $ip, $existing): void {
            $this->receivables->markDeleted($companyId, $id, $actorId);
            $this->writeAudit($companyId, $id, $actorId, $ip, 'delete', $existing, null);
        });
    }

    public function registerReceipt(int $companyId, int $receivableId, array $payload, int $actorId, ?string $ip = null): array
    {
        $existing = $this->requireReceivable($companyId, $receivableId);
        if (in_array((string) ($existing['status'] ?? ''), ['canceled', 'renegotiated'], true)) {
            throw new \RuntimeException('Título cancelado/renegociado não pode receber baixa.');
        }

        $receipt = FinancialReceiptData::fromArray(array_merge($payload, [
            'receivable_id' => $receivableId,
            'created_by' => $actorId,
        ]));
        $this->assertReceiptData($existing, $receipt);

        return $this->transactional(function () use ($companyId, $receivableId, $receipt, $actorId, $ip, $existing): array {
            $receiptId = $this->receipts->create($receipt);
            $snapshot = $this->recalculateSnapshot($companyId, $receivableId, $actorId);
            $this->syncLegacyInstallment($existing, $receipt, 1, $actorId);
            $after = $this->receivables->find($companyId, $receivableId);
            $this->writeAudit($companyId, $receivableId, $actorId, $ip, 'receipt', $existing, $after, ['receipt_id' => $receiptId]);
            return $snapshot;
        });
    }

    public function reverseReceipt(int $companyId, int $receivableId, int $receiptId, string $reason, int $actorId, ?string $ip = null): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Observação obrigatória em estornos.');
        }
        $existing = $this->requireReceivable($companyId, $receivableId);
        $receipt = $this->receipts->find($receiptId);
        if ($receipt === null || (int) ($receipt['receivable_id'] ?? 0) !== $receivableId) {
            throw new \RuntimeException('Recebimento não encontrado.');
        }
        if (($receipt['reversed_at'] ?? null) !== null) {
            throw new \RuntimeException('Recebimento já estornado.');
        }

        return $this->transactional(function () use ($companyId, $receivableId, $receiptId, $reason, $actorId, $ip, $existing, $receipt): array {
            $this->receipts->reverse($receiptId, $actorId, $reason);
            $snapshot = $this->recalculateSnapshot($companyId, $receivableId, $actorId);
            $reverseData = FinancialReceiptData::fromArray([
                'receivable_id' => $receivableId,
                'amount_received' => (float) ($receipt['amount_received'] ?? 0),
                'interest_amount' => (float) ($receipt['interest_amount'] ?? 0),
                'fine_amount' => (float) ($receipt['fine_amount'] ?? 0),
                'discount_amount' => (float) ($receipt['discount_amount'] ?? 0),
                'payment_method' => (string) ($receipt['payment_method'] ?? ''),
                'payment_date' => date('Y-m-d H:i:s'),
                'transaction_reference' => 'ESTORNO-' . $receiptId,
                'bank_reference' => (string) ($receipt['bank_reference'] ?? ''),
                'observation' => 'Estorno: ' . $reason,
                'created_by' => $actorId,
            ]);
            $this->syncLegacyInstallment($existing, $reverseData, -1, $actorId);
            $after = $this->receivables->find($companyId, $receivableId);
            $this->writeAudit($companyId, $receivableId, $actorId, $ip, 'reverse', $existing, $after, ['receipt_id' => $receiptId, 'reason' => $reason]);
            return $snapshot;
        });
    }

    public function duplicate(int $companyId, int $id, int $actorId, ?string $ip = null): array
    {
        $existing = $this->requireReceivable($companyId, $id);
        $payload = $existing;
        unset($payload['id'], $payload['deleted_at'], $payload['created_at'], $payload['updated_at'], $payload['received_amount'], $payload['remaining_amount']);
        $payload['title'] = (string) $existing['title'] . ' (duplicado)';
        $payload['status'] = 'pending';
        $payload['payment_date'] = null;
        $payload['created_by'] = $actorId;
        $payload['updated_by'] = $actorId;

        $created = $this->create($payload, $actorId, $ip);
        return $created[0] ?? [];
    }

    public function renegotiate(int $companyId, int $id, array $payload, int $actorId, ?string $ip = null): array
    {
        $existing = $this->requireReceivable($companyId, $id);
        $remaining = (float) ($existing['remaining_amount'] ?? 0);
        if ($remaining <= 0) {
            throw new \RuntimeException('Apenas títulos com saldo aberto podem ser renegociados.');
        }
        $newDueDate = trim((string) ($payload['new_due_date'] ?? ''));
        if ($newDueDate === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDueDate) !== 1) {
            throw new \RuntimeException('Nova data de vencimento inválida.');
        }

        return $this->transactional(function () use ($companyId, $id, $existing, $remaining, $newDueDate, $payload, $actorId, $ip): array {
            $this->receivables->updateFinancialSnapshot($companyId, $id, (float) ($existing['received_amount'] ?? 0), $remaining, 'renegotiated', null, $actorId);
            $newItems = $this->create(array_merge($existing, [
                'title' => (string) $existing['title'] . ' (renegociação)',
                'original_amount' => $remaining,
                'discount_amount' => 0,
                'interest_amount' => 0,
                'fine_amount' => 0,
                'due_date' => $newDueDate,
                'status' => 'pending',
                'source_installment_id' => null,
                'payment_date' => null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'notes' => trim((string) ($payload['notes'] ?? '')) !== '' ? (string) $payload['notes'] : (string) ($existing['notes'] ?? ''),
            ]), $actorId, $ip);
            $this->writeAudit($companyId, $id, $actorId, $ip, 'renegotiate', $existing, $newItems[0] ?? null, ['new_due_date' => $newDueDate]);
            return $newItems[0] ?? [];
        });
    }

    public function refreshStatuses(int $companyId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE financial_accounts_receivable
                               SET status = 'overdue', updated_at = NOW()
                               WHERE company_id = :company_id
                                 AND deleted_at IS NULL
                                 AND status IN ('pending','partially_paid')
                                 AND remaining_amount > 0
                                 AND due_date < CURDATE()");
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function generateFromProject(int $projectId, int $actorId, ?string $ip = null): void
    {
        $this->syncProjectFromLegacy($projectId, $actorId, $ip, false);
    }

    public function repairProjectReceivables(int $projectId, int $actorId, ?string $ip = null): array
    {
        return $this->syncProjectFromLegacy($projectId, $actorId, $ip, true);
    }

    private function recalculateSnapshot(int $companyId, int $receivableId, int $actorId): array
    {
        $current = $this->requireReceivable($companyId, $receivableId);
        $received = $this->receipts->netReceivedByReceivable($receivableId);
        $gross = round((float) ($current['original_amount'] ?? 0) + (float) ($current['interest_amount'] ?? 0) + (float) ($current['fine_amount'] ?? 0) - (float) ($current['discount_amount'] ?? 0), 2);
        $remaining = max(0, round($gross - $received, 2));
        $status = $remaining <= 0.0001 ? 'paid' : ($received > 0.0001 ? 'partially_paid' : ((string) ($current['due_date'] ?? '') < date('Y-m-d') ? 'overdue' : 'pending'));
        $paymentDate = $status === 'paid' ? date('Y-m-d') : null;
        $this->receivables->updateFinancialSnapshot($companyId, $receivableId, $received, $remaining, $status, $paymentDate, $actorId);
        return [
            'received_amount' => $received,
            'remaining_amount' => $remaining,
            'status' => $status,
        ];
    }

    private function syncLegacyInstallment(array $receivable, FinancialReceiptData $receipt, int $multiplier, int $actorId): void
    {
        $installmentId = (int) ($receivable['source_installment_id'] ?? 0);
        if ($installmentId <= 0) {
            return;
        }
        $legacy = $this->legacyInstallments->find($installmentId);
        if ($legacy === null) {
            return;
        }
        $amount = round(((($receipt->amountReceived + $receipt->interestAmount + $receipt->fineAmount) - $receipt->discountAmount) * $multiplier), 2);
        $paidAt = $receipt->paymentDate;
        $this->legacyPayments->create($installmentId, $amount, $paidAt, $receipt->paymentMethod, $receipt->transactionReference, $receipt->observation, $actorId);

        $currentPaid = round((float) ($legacy['paid_amount'] ?? 0) + $amount, 2);
        if ($currentPaid < 0) {
            $currentPaid = 0;
        }
        $amountDue = (float) ($legacy['amount'] ?? 0);
        $status = 'pendente';
        $paidAtToStore = null;
        if ($currentPaid >= $amountDue - 0.0001) {
            $status = 'pago';
            $paidAtToStore = $paidAt;
        } elseif ($currentPaid > 0.0001) {
            $status = ((string) ($legacy['due_date'] ?? '') < date('Y-m-d')) ? 'atrasado' : 'pendente';
        } elseif ((string) ($legacy['due_date'] ?? '') < date('Y-m-d')) {
            $status = 'atrasado';
        }
        $this->legacyInstallments->markPaidWithStatus($installmentId, min($currentPaid, $amountDue), $paidAtToStore, $status);
    }

    private function assertReceivableData(FinancialReceivableData $data): void
    {
        if ($data->clientId <= 0) {
            throw new \RuntimeException('Cliente obrigatório.');
        }
        if ($data->title === '') {
            throw new \RuntimeException('Título obrigatório.');
        }
        if ($data->originalAmount <= 0) {
            throw new \RuntimeException('Valor original inválido.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data->dueDate) !== 1) {
            throw new \RuntimeException('Data de vencimento inválida.');
        }
        $allowed = ['pending', 'partially_paid', 'paid', 'overdue', 'canceled', 'renegotiated'];
        if (!in_array($data->status, $allowed, true)) {
            throw new \RuntimeException('Status financeiro inválido.');
        }
    }

    private function assertReceiptData(array $receivable, FinancialReceiptData $receipt): void
    {
        if ($receipt->amountReceived <= 0) {
            throw new \RuntimeException('Valor recebido inválido.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $receipt->paymentDate) !== 1) {
            throw new \RuntimeException('Data de recebimento inválida.');
        }
        $gross = round((float) ($receivable['remaining_amount'] ?? 0), 2);
        $incoming = round(($receipt->amountReceived + $receipt->interestAmount + $receipt->fineAmount) - $receipt->discountAmount, 2);
        if ($incoming <= 0) {
            throw new \RuntimeException('Recebimento líquido inválido.');
        }
        if ($incoming - $gross > 0.0001) {
            throw new \RuntimeException('Recebimento maior que o saldo atual.');
        }
    }

    private function requireReceivable(int $companyId, int $id): array
    {
        $item = $this->receivables->find($companyId, $id);
        if ($item === null) {
            throw new \RuntimeException('Conta a receber não encontrada.');
        }
        return $item;
    }

    private function expandSchedule(array $payload, int $actorId, int $companyId): array
    {
        if ($this->shouldKeepSingleReceivable($payload)) {
            return [array_merge($payload, ['company_id' => $companyId, 'created_by' => $actorId, 'updated_by' => $actorId])];
        }
        $count = max(1, (int) ($payload['total_installments'] ?? 1));
        $interval = max(0, (int) ($payload['recurrence_interval_months'] ?? 0));
        if ($count === 1) {
            return [array_merge($payload, ['company_id' => $companyId, 'created_by' => $actorId, 'updated_by' => $actorId])];
        }
        $due = trim((string) ($payload['due_date'] ?? ''));
        if ($due === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) !== 1) {
            throw new \RuntimeException('Data base de vencimento inválida para parcelamento.');
        }
        $baseAmount = round((float) ($payload['original_amount'] ?? 0) / $count, 2);
        $items = [];
        $group = 'RG-' . date('YmdHis') . '-' . random_int(100, 999);
        $sum = 0.0;
        for ($i = 1; $i <= $count; $i++) {
            $amount = $i === $count ? round((float) ($payload['original_amount'] ?? 0) - $sum, 2) : $baseAmount;
            $sum += $amount;
            $dueDate = date('Y-m-d', strtotime($due . ' +' . (($i - 1) * max(1, $interval)) . ' month'));
            $items[] = array_merge($payload, [
                'company_id' => $companyId,
                'title' => trim((string) ($payload['title'] ?? '')) . ' - Parcela ' . $i . '/' . $count,
                'original_amount' => $amount,
                'installment_number' => $i,
                'total_installments' => $count,
                'due_date' => $dueDate,
                'recurrence_group' => $group,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
        }
        return $items;
    }

    private function shouldKeepSingleReceivable(array $payload): bool
    {
        return (int) ($payload['source_installment_id'] ?? 0) > 0;
    }

    private function syncProjectFromLegacy(int $projectId, int $actorId, ?string $ip, bool $repairExisting): array
    {
        $companyId = CompanyContext::currentCompanyId();
        $rows = $this->legacyProjectInstallments($projectId);
        if ($rows === []) {
            return ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];
        }

        $pdo = DB::pdo();
        $contractStmt = $pdo->prepare('SELECT id FROM contracts WHERE proposal_id = :proposal_id ORDER BY id DESC LIMIT 1');

        return $this->transactional(function () use ($rows, $companyId, $contractStmt, $actorId, $ip, $projectId, $repairExisting): array {
            $result = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];
            $totalInstallments = count($rows);

            foreach ($rows as $row) {
                $matches = $this->receivables->listBySourceInstallment($companyId, (int) $row['id']);
                if (!$repairExisting && $matches !== []) {
                    continue;
                }

                $contractStmt->bindValue(':proposal_id', (int) $row['proposal_id'], \PDO::PARAM_INT);
                $contractStmt->execute();
                $contractId = (int) $contractStmt->fetchColumn();

                $payload = $this->legacyReceivablePayload($row, $totalInstallments, $contractId, $actorId, $companyId);

                if ($matches === []) {
                    $created = $this->create($payload, $actorId, $ip);
                    if (!empty($created)) {
                        $result['created']++;
                        $this->writeAudit($companyId, (int) ($created[0]['id'] ?? 0), $actorId, $ip, 'auto_generate', null, $created[0], ['project_id' => $projectId]);
                    }
                    continue;
                }

                $canonical = $this->pickCanonicalReceivable($matches);
                $before = $this->receivables->find($companyId, (int) $canonical['id']) ?? $canonical;
                $data = FinancialReceivableData::fromArray(array_merge($canonical, $payload, [
                    'company_id' => $companyId,
                    'created_by' => (int) ($canonical['created_by'] ?? $actorId),
                    'updated_by' => $actorId,
                ]));
                $this->assertReceivableData($data);
                $this->receivables->update((int) $canonical['id'], $data, (float) ($canonical['received_amount'] ?? 0));
                $after = $this->receivables->find($companyId, (int) $canonical['id']);
                $this->writeAudit($companyId, (int) $canonical['id'], $actorId, $ip, 'repair_sync', $before, $after, ['project_id' => $projectId, 'source_installment_id' => (int) $row['id']]);
                $result['updated']++;

                foreach ($matches as $candidate) {
                    if ((int) ($candidate['id'] ?? 0) === (int) ($canonical['id'] ?? 0)) {
                        continue;
                    }
                    if ($this->canSafelyDeleteDuplicate((int) $candidate['id'], $candidate) === false) {
                        $result['skipped']++;
                        continue;
                    }
                    $this->receivables->markDeleted($companyId, (int) $candidate['id'], $actorId);
                    $this->writeAudit($companyId, (int) $candidate['id'], $actorId, $ip, 'repair_delete_duplicate', $candidate, null, ['project_id' => $projectId, 'source_installment_id' => (int) $row['id']]);
                    $result['deleted']++;
                }
            }

            return $result;
        });
    }

    private function legacyProjectInstallments(int $projectId): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT fi.id, fi.project_id, fi.installment_no, fi.amount, fi.due_date, fi.status, fi.paid_amount, fi.paid_at,
                       pr.client_id, pr.id AS proposal_id, pr.title AS proposal_title, pr.created_at AS proposal_created_at
                FROM finance_installments fi
                INNER JOIN proposals pr ON pr.id = fi.proposal_id
                WHERE fi.project_id = :project_id
                ORDER BY fi.installment_no ASC, fi.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function legacyReceivablePayload(array $row, int $totalInstallments, int $contractId, int $actorId, int $companyId): array
    {
        $dueDate = (string) ($row['due_date'] ?? date('Y-m-d'));
        $issueDate = substr((string) ($row['proposal_created_at'] ?? ''), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate) !== 1) {
            $issueDate = date('Y-m-d');
        }

        return [
            'company_id' => $companyId,
            'project_id' => (int) $row['project_id'],
            'client_id' => (int) $row['client_id'],
            'contract_id' => $contractId > 0 ? $contractId : null,
            'source_installment_id' => (int) $row['id'],
            'installment_number' => max(1, (int) ($row['installment_no'] ?? 1)),
            'total_installments' => max(1, $totalInstallments),
            'title' => 'Projeto: ' . trim((string) ($row['proposal_title'] ?? 'Projeto')) . ' - Parcela ' . max(1, (int) ($row['installment_no'] ?? 1)) . '/' . max(1, $totalInstallments),
            'description' => 'Gerado automaticamente a partir do projeto aprovado.',
            'original_amount' => round((float) ($row['amount'] ?? 0), 2),
            'discount_amount' => 0,
            'interest_amount' => 0,
            'fine_amount' => 0,
            'due_date' => $dueDate,
            'issue_date' => $issueDate,
            'payment_date' => $this->legacyPaymentDate($row),
            'competence_date' => $dueDate,
            'status' => $this->mapLegacyStatus($row),
            'category_id' => 2,
            'cost_center_id' => 3,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
    }

    private function pickCanonicalReceivable(array $matches): array
    {
        usort($matches, static function (array $a, array $b): int {
            $aReceived = (float) ($a['received_amount'] ?? 0);
            $bReceived = (float) ($b['received_amount'] ?? 0);
            if ($aReceived !== $bReceived) {
                return $aReceived < $bReceived ? 1 : -1;
            }
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $matches[0];
    }

    private function canSafelyDeleteDuplicate(int $receivableId, array $candidate): bool
    {
        if ((float) ($candidate['received_amount'] ?? 0) > 0.0001) {
            return false;
        }

        foreach ($this->receipts->listByReceivable($receivableId) as $receipt) {
            if (($receipt['reversed_at'] ?? null) === null) {
                return false;
            }
        }

        return true;
    }

    private function mapLegacyStatus(array $row): string
    {
        $legacyStatus = (string) ($row['status'] ?? '');
        $amount = (float) ($row['amount'] ?? 0);
        $paidAmount = (float) ($row['paid_amount'] ?? 0);
        if ($legacyStatus === 'cancelado') {
            return 'canceled';
        }
        if ($paidAmount >= $amount - 0.0001 && $amount > 0) {
            return 'paid';
        }
        if ($paidAmount > 0.0001) {
            return ((string) ($row['due_date'] ?? '') < date('Y-m-d')) ? 'overdue' : 'partially_paid';
        }
        return ((string) ($row['due_date'] ?? '') < date('Y-m-d')) ? 'overdue' : 'pending';
    }

    private function legacyPaymentDate(array $row): ?string
    {
        $paidAt = (string) ($row['paid_at'] ?? '');
        return trim($paidAt) !== '' ? substr($paidAt, 0, 10) : null;
    }

    private function writeAudit(int $companyId, ?int $receivableId, int $actorId, ?string $ip, string $action, ?array $before, ?array $after, ?array $metadata = null): void
    {
        $this->financialAudit->create($companyId, $receivableId, $actorId > 0 ? $actorId : null, $ip, $action, $before, $after, $metadata);
        if ($receivableId !== null) {
            $this->audit->create('financial_receivable', $receivableId, $action, $actorId > 0 ? $actorId : null, ['before' => $before, 'after' => $after, 'metadata' => $metadata]);
        }
    }

    private function transactional(callable $callback): mixed
    {
        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
