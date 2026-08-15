<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Repositories\FinancialCatalogRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\ServiceOrderHistoryRepository;
use App\Repositories\ServiceOrderRepository;

/**
 * Orquestra o fluxo "Definir cobrança" ao faturar uma Ordem de Serviço: calcula o
 * cronograma de parcelas (pagamento único, parcelado ou personalizado), cria os
 * títulos correspondentes em financial_accounts_receivable (fonte financeira oficial,
 * reaproveitando FinancialReceivableService::createStandalone() para validação e
 * auditoria) e só então marca a OS como Faturado — tudo em uma única transação.
 */
final class ServiceOrderBillingService
{
    public const MODE_UNICO = 'unico';
    public const MODE_PARCELADO = 'parcelado';
    public const MODE_PERSONALIZADO = 'personalizado';

    private const PERIODICIDADES = ['mensal', 'quinzenal', 'semanal', 'personalizada'];

    public function invoice(int $serviceOrderId, array $payload, int $actorId): array
    {
        $orderRepo = new ServiceOrderRepository();
        $order = $orderRepo->find($serviceOrderId);
        if ($order === null) {
            throw new \RuntimeException('Ordem de serviço não encontrada.');
        }
        if ((int) ($order['billable'] ?? 0) !== 1) {
            throw new \RuntimeException('Esta Ordem de Serviço não possui cobrança.');
        }

        $companyId = CompanyContext::currentCompanyId();
        if ($this->linkedTitles($companyId, $serviceOrderId, $order) !== []) {
            throw new \RuntimeException('Esta Ordem de Serviço já possui cobrança gerada.');
        }
        if ((string) ($order['status'] ?? '') !== ServiceOrderStatus::CONCLUIDO) {
            throw new \RuntimeException('Conclua a Ordem de Serviço antes de definir a cobrança.');
        }

        $totalAmount = round((float) ($order['final_amount'] ?? 0), 2);
        if ($totalAmount <= 0) {
            throw new \RuntimeException('A Ordem de Serviço não possui valor final para faturamento.');
        }

        $installments = $this->buildInstallments($payload, $totalAmount);
        $mode = trim((string) ($payload['mode'] ?? ''));

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $created = $this->createInstallmentPlan($order, $companyId, $installments, $actorId);

            $firstId = (int) ($created[0]['id'] ?? 0);
            $orderRepo->attachFinancialReceivable($serviceOrderId, $firstId > 0 ? $firstId : null, $actorId);

            (new ServiceOrderService())->updateStatus($serviceOrderId, ServiceOrderStatus::FATURADO, $actorId);

            (new ServiceOrderHistoryRepository())->create(
                $serviceOrderId,
                'faturamento',
                $actorId,
                null,
                null,
                null,
                sprintf(
                    'Cobrança definida (%s): %d parcela(s), total R$ %s.',
                    $this->modeLabel($mode),
                    count($created),
                    number_format($totalAmount, 2, ',', '.')
                )
            );

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $created;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Refaz a cobrança de uma OS já faturada (ex.: um título antigo, incorreto ou
     * incompleto, precisa ser substituído por um cronograma correto). Estratégia de
     * segurança escolhida: só é permitido quando NENHUM título atualmente vinculado à
     * OS (via service_order_id ou o vínculo legado financial_receivable_id) tem
     * received_amount > 0 — havendo qualquer recebimento, a operação é bloqueada e
     * precisa ser tratada manualmente no financeiro (baixa/estorno primeiro). Isso evita
     * a complexidade e o risco de reaplicar recebimentos parciais sobre um novo
     * cronograma. Os títulos antigos são removidos (soft delete, preservando auditoria)
     * e substituídos pelos novos, na mesma transação.
     */
    public function reparcel(int $serviceOrderId, array $payload, int $actorId): array
    {
        $orderRepo = new ServiceOrderRepository();
        $order = $orderRepo->find($serviceOrderId);
        if ($order === null) {
            throw new \RuntimeException('Ordem de serviço não encontrada.');
        }
        if ((int) ($order['billable'] ?? 0) !== 1) {
            throw new \RuntimeException('Esta Ordem de Serviço não possui cobrança.');
        }

        $companyId = CompanyContext::currentCompanyId();
        $linked = $this->linkedTitles($companyId, $serviceOrderId, $order);
        if ($linked === []) {
            throw new \RuntimeException('Esta Ordem de Serviço ainda não possui cobrança gerada. Utilize o faturamento normal.');
        }
        foreach ($linked as $title) {
            if ((float) ($title['received_amount'] ?? 0) > 0.0001) {
                throw new \RuntimeException(sprintf(
                    'Não é possível reparcelar: o título "%s" já possui recebimento (R$ %s). Faça a baixa/estorno necessário no financeiro antes de reparcelar.',
                    (string) ($title['title'] ?? ('#' . $title['id'])),
                    number_format((float) ($title['received_amount'] ?? 0), 2, ',', '.')
                ));
            }
        }

        $totalAmount = round((float) ($order['final_amount'] ?? 0), 2);
        if ($totalAmount <= 0) {
            throw new \RuntimeException('A Ordem de Serviço não possui valor final para faturamento.');
        }

        $installments = $this->buildInstallments($payload, $totalAmount);
        $mode = trim((string) ($payload['mode'] ?? ''));
        $oldCount = count($linked);
        $oldTotal = round(array_sum(array_map(static fn (array $t): float => (float) ($t['original_amount'] ?? 0), $linked)), 2);

        $pdo = DB::pdo();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $financialService = new FinancialReceivableService();
            $orderRepo->attachFinancialReceivable($serviceOrderId, null, $actorId);
            foreach ($linked as $title) {
                $financialService->delete($companyId, (int) $title['id'], $actorId);
            }

            $created = $this->createInstallmentPlan($order, $companyId, $installments, $actorId);

            $firstId = (int) ($created[0]['id'] ?? 0);
            $orderRepo->attachFinancialReceivable($serviceOrderId, $firstId > 0 ? $firstId : null, $actorId);

            if ((string) ($order['status'] ?? '') !== ServiceOrderStatus::FATURADO) {
                (new ServiceOrderService())->updateStatus($serviceOrderId, ServiceOrderStatus::FATURADO, $actorId);
            }

            (new ServiceOrderHistoryRepository())->create(
                $serviceOrderId,
                'reparcelamento',
                $actorId,
                null,
                sprintf('%d título(s), R$ %s', $oldCount, number_format($oldTotal, 2, ',', '.')),
                sprintf('%d parcela(s), R$ %s', count($created), number_format($totalAmount, 2, ',', '.')),
                sprintf(
                    'Cobrança reparcelada (%s): %d título(s) anterior(es) substituído(s) por %d parcela(s), total R$ %s.',
                    $this->modeLabel($mode),
                    $oldCount,
                    count($created),
                    number_format($totalAmount, 2, ',', '.')
                )
            );

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $created;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Todos os títulos atualmente vinculados à OS: via service_order_id (fluxo novo,
     * um-para-muitos) e via o vínculo legado escalar servicos_avulsos.financial_receivable_id
     * (títulos criados antes desta sprint, ou ainda não migrados), sem duplicar caso o
     * mesmo id apareça nas duas buscas.
     */
    private function linkedTitles(int $companyId, int $serviceOrderId, array $order): array
    {
        $receivableRepo = new FinancialReceivableRepository();
        $rows = $receivableRepo->listByServiceOrder($companyId, $serviceOrderId);

        $legacyId = (int) ($order['financial_receivable_id'] ?? 0);
        if ($legacyId > 0 && !in_array($legacyId, array_map(static fn (array $r): int => (int) $r['id'], $rows), true)) {
            $legacy = $receivableRepo->find($companyId, $legacyId);
            if ($legacy !== null) {
                $rows[] = $legacy;
            }
        }

        return $rows;
    }

    /**
     * Cria os N títulos de um cronograma já calculado (buildInstallments()) para a OS,
     * sem tocar em status/histórico/vínculo — reutilizado por invoice() (primeiro
     * faturamento) e reparcel() (substituição de cobrança existente).
     */
    private function createInstallmentPlan(array $order, int $companyId, array $installments, int $actorId): array
    {
        $financialService = new FinancialReceivableService();
        $catalog = new FinancialCatalogRepository();
        $categoryId = $catalog->findCategoryIdByName($companyId, 'Serviços avulsos');
        $costCenterId = $catalog->findCostCenterIdByName($companyId, 'Serviços Avulsos');
        $numeroOs = (string) ($order['numero_os'] ?? '');
        $serviceOrderId = (int) ($order['id'] ?? 0);
        $total = count($installments);
        $group = $total > 1 ? ('OS-' . $numeroOs . '-' . date('YmdHis')) : null;

        $created = [];
        foreach ($installments as $index => $item) {
            $number = $index + 1;
            $title = $total > 1
                ? sprintf('OS %s - Parcela %d/%d', $numeroOs, $number, $total)
                : sprintf('OS %s', $numeroOs);
            $description = trim((string) ($item['description'] ?? ''));

            $row = $financialService->createStandalone([
                'client_id' => (int) $order['client_id'],
                'service_order_id' => $serviceOrderId,
                'installment_number' => $number,
                'total_installments' => $total,
                'title' => $title,
                'description' => $description !== '' ? $description : (string) ($order['service_name'] ?? ''),
                'original_amount' => $item['amount'],
                'discount_amount' => 0,
                'interest_amount' => 0,
                'fine_amount' => 0,
                'due_date' => $item['due_date'],
                'issue_date' => date('Y-m-d'),
                'competence_date' => date('Y-m-d'),
                'status' => 'pending',
                'category_id' => $categoryId,
                'cost_center_id' => $costCenterId,
                'external_reference' => $numeroOs,
                'recurrence_group' => $group,
                'notes' => trim((string) ($item['notes'] ?? '')) !== ''
                    ? (string) $item['notes']
                    : 'Gerado pelo faturamento da ordem de serviço ' . $numeroOs . '.',
            ], $actorId);
            $created[] = $row;
        }

        return $created;
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            self::MODE_UNICO => 'pagamento único',
            self::MODE_PARCELADO => 'parcelado',
            self::MODE_PERSONALIZADO => 'personalizado',
            default => $mode,
        };
    }

    private function buildInstallments(array $payload, float $totalAmount): array
    {
        $mode = trim((string) ($payload['mode'] ?? ''));
        return match ($mode) {
            self::MODE_UNICO => $this->buildUnico($payload, $totalAmount),
            self::MODE_PARCELADO => $this->buildParcelado($payload, $totalAmount),
            self::MODE_PERSONALIZADO => $this->buildPersonalizado($payload, $totalAmount),
            default => throw new \RuntimeException('Selecione um modelo de cobrança válido.'),
        };
    }

    private function buildUnico(array $payload, float $totalAmount): array
    {
        $dueDate = $this->parseDate((string) ($payload['due_date'] ?? ''), 'Vencimento inválido.');
        return [[
            'amount' => $totalAmount,
            'due_date' => $dueDate,
            'description' => trim((string) ($payload['description'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
        ]];
    }

    private function buildParcelado(array $payload, float $totalAmount): array
    {
        $count = max(0, (int) ($payload['installments_count'] ?? 0));
        if ($count < 2) {
            throw new \RuntimeException('Informe ao menos 2 parcelas para o modelo parcelado.');
        }

        $firstDueDate = $this->parseDate((string) ($payload['first_due_date'] ?? ''), 'Primeiro vencimento inválido.');
        $periodicity = trim((string) ($payload['periodicity'] ?? 'mensal'));
        if (!in_array($periodicity, self::PERIODICIDADES, true)) {
            throw new \RuntimeException('Periodicidade inválida.');
        }
        $customDays = max(1, (int) ($payload['custom_interval_days'] ?? 0));

        $base = round($totalAmount / $count, 2);
        $items = [];
        $sum = 0.0;
        for ($i = 1; $i <= $count; $i++) {
            $amount = $i === $count ? round($totalAmount - $sum, 2) : $base;
            $sum = round($sum + $amount, 2);
            $items[] = [
                'amount' => $amount,
                'due_date' => $this->addInterval($firstDueDate, $i - 1, $periodicity, $customDays),
                'description' => '',
                'notes' => '',
            ];
        }
        return $items;
    }

    private function buildPersonalizado(array $payload, float $totalAmount): array
    {
        $rows = is_array($payload['installments'] ?? null) ? $payload['installments'] : [];
        if ($rows === []) {
            throw new \RuntimeException('Informe ao menos uma parcela no parcelamento personalizado.');
        }

        $items = [];
        $sum = 0.0;
        foreach ($rows as $row) {
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new \RuntimeException('Todas as parcelas devem ter valor maior que zero.');
            }
            $dueDate = $this->parseDate((string) ($row['due_date'] ?? ''), 'Vencimento inválido em uma das parcelas.');
            $sum = round($sum + $amount, 2);
            $items[] = [
                'amount' => $amount,
                'due_date' => $dueDate,
                'description' => trim((string) ($row['description'] ?? '')),
                'notes' => '',
            ];
        }

        $diff = round($totalAmount - $sum, 2);
        if (abs($diff) > 0.01) {
            throw new \RuntimeException(sprintf(
                'A soma das parcelas deve corresponder ao valor total da cobrança. Diferença: R$ %s',
                number_format(abs($diff), 2, ',', '.')
            ));
        }

        return $items;
    }

    private function addInterval(string $baseDate, int $steps, string $periodicity, int $customDays): string
    {
        if ($steps === 0) {
            return $baseDate;
        }

        return match ($periodicity) {
            'mensal' => date('Y-m-d', strtotime($baseDate . ' +' . $steps . ' month')),
            'quinzenal' => date('Y-m-d', strtotime($baseDate . ' +' . ($steps * 15) . ' day')),
            'semanal' => date('Y-m-d', strtotime($baseDate . ' +' . ($steps * 7) . ' day')),
            'personalizada' => date('Y-m-d', strtotime($baseDate . ' +' . ($steps * $customDays) . ' day')),
            default => $baseDate,
        };
    }

    private function parseDate(string $value, string $errorMessage): string
    {
        $value = trim($value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new \RuntimeException($errorMessage);
        }
        return $value;
    }
}
