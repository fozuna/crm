<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditLogRepositoryContract;
use App\Contracts\ServiceOrderAttachmentRepositoryContract;
use App\Contracts\ServiceOrderHistoryRepositoryContract;
use App\Contracts\ServiceOrderRepositoryContract;
use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\ServiceOrderAttachmentRepository;
use App\Repositories\ServiceOrderHistoryRepository;
use App\Repositories\ServiceOrderRepository;

final class ServiceOrderService
{
    public function __construct(
        private readonly ?ServiceOrderRepositoryContract $orders = null,
        private readonly ?ServiceOrderAttachmentRepositoryContract $attachments = null,
        private readonly ?ServiceOrderHistoryRepositoryContract $history = null,
        private readonly ?AuditLogRepositoryContract $audit = null,
        private readonly mixed $transaction = null,
    ) {
    }

    public function create(array $payload, int $actorId): array
    {
        $data = $this->validated($payload);
        $pdo = is_object($this->transaction) ? $this->transaction : DB::pdo();
        $pdo->beginTransaction();

        try {
            $id = $this->orderRepo()->create($data, $actorId);
            $current = $this->requireOrder($id);
            $this->historyRepo()->create($id, 'create', $actorId, null, null, null, 'Ordem de serviço criada.', ['after' => $current]);

            $this->auditRepo()->create('service_order', $id, 'create', $actorId, ['after' => $current]);
            $pdo->commit();
            $this->triggerApprovalWorkflow([], $current, $actorId);
            return $current;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, array $payload, int $actorId): array
    {
        $existing = $this->requireOrder($id);
        $data = $this->validated($payload);
        $pdo = is_object($this->transaction) ? $this->transaction : DB::pdo();
        $pdo->beginTransaction();

        try {
            $this->orderRepo()->update($id, $data, $actorId);
            $this->recordDiffHistory($existing, $data, $actorId);

            $updated = $this->requireOrder($id);
            $this->auditRepo()->create('service_order', $id, 'update', $actorId, [
                'before' => $existing,
                'after' => $updated,
            ]);
            $pdo->commit();
            $this->triggerApprovalWorkflow($existing, $updated, $actorId);
            return $updated;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status, int $actorId): array
    {
        $existing = $this->requireOrder($id);
        if (!ServiceOrderStatus::isValid($status)) {
            throw new \RuntimeException('Status inválido.');
        }
        if ((string) ($existing['status'] ?? '') === $status) {
            return $existing;
        }
        if ($status === ServiceOrderStatus::FATURADO
            && (int) ($existing['billable'] ?? 0) === 1
            && (int) ($existing['financial_receivable_id'] ?? 0) === 0
            && (new FinancialReceivableRepository())->listByServiceOrder(CompanyContext::currentCompanyId(), $id) === []
        ) {
            throw new \RuntimeException('Utilize o fluxo de faturamento para definir a cobrança antes de marcar como Faturado.');
        }

        $completedAt = in_array($status, [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true)
            ? date('Y-m-d H:i:s')
            : null;

        $this->orderRepo()->updateStatus($id, $status, $actorId, $completedAt);
        $updated = $this->requireOrder($id);
        $this->historyRepo()->create(
            $id,
            'status_change',
            $actorId,
            'status',
            (string) ($existing['status'] ?? ''),
            $status,
            'Status alterado para ' . ServiceOrderStatus::label($status) . '.'
        );
        $this->auditRepo()->create('service_order', $id, 'status_change', $actorId, [
            'before_status' => $existing['status'] ?? null,
            'after_status' => $status,
        ]);
        $this->triggerApprovalWorkflow($existing, $updated, $actorId);
        return $updated;
    }

    public function cancel(int $id, int $actorId, string $reason = ''): array
    {
        $order = $this->requireOrder($id);
        if ((string) ($order['status'] ?? '') === ServiceOrderStatus::FATURADO) {
            throw new \RuntimeException('Ordens faturadas não podem ser canceladas por este fluxo.');
        }

        $updated = $this->updateStatus($id, ServiceOrderStatus::CANCELADO, $actorId);
        $note = trim($reason);
        if ($note !== '') {
            $this->historyRepo()->create($id, 'cancel_reason', $actorId, null, null, null, $note);
        }
        return $updated;
    }

    public function delete(int $id, int $actorId): void
    {
        $order = $this->requireOrder($id);
        if (in_array((string) ($order['status'] ?? ''), [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true)) {
            throw new \RuntimeException('Ordens concluídas ou faturadas não podem ser excluídas.');
        }
        $this->orderRepo()->markDeleted($id, $actorId);
        $this->historyRepo()->create($id, 'delete', $actorId, null, null, null, 'Ordem de serviço removida logicamente.');
        $this->auditRepo()->create('service_order', $id, 'delete', $actorId, ['before' => $order]);
    }

    public function addAttachments(int $id, array $files, int $actorId): array
    {
        $order = $this->requireOrder($id);
        $uploaded = (new ServiceOrderAttachmentUploadService())->uploadMany($id, $files);
        $created = [];
        foreach ($uploaded as $item) {
            $attachmentId = $this->attachmentRepo()->create($id, $item, $actorId > 0 ? $actorId : null);
            $createdItem = $this->attachmentRepo()->find($attachmentId);
            if (is_array($createdItem)) {
                $created[] = $createdItem;
            }
            $this->historyRepo()->create(
                $id,
                'attachment_create',
                $actorId,
                'attachment',
                null,
                $item['original_name'] ?? null,
                'Anexo incluído: ' . (string) ($item['original_name'] ?? 'arquivo')
            );
        }

        $this->auditRepo()->create('service_order', $id, 'attachment_create', $actorId, [
            'service_order' => $order['numero_os'] ?? null,
            'attachments' => array_column($created, 'original_name'),
        ]);

        return $created;
    }

    public function removeAttachment(int $serviceOrderId, int $attachmentId, int $actorId): void
    {
        $attachment = $this->attachmentRepo()->find($attachmentId);
        if ($attachment === null || (int) ($attachment['servico_avulso_id'] ?? 0) !== $serviceOrderId) {
            throw new \RuntimeException('Anexo não encontrado.');
        }

        $path = (string) ($attachment['file_path'] ?? '');
        $this->attachmentRepo()->delete($attachmentId);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }

        $this->historyRepo()->create(
            $serviceOrderId,
            'attachment_delete',
            $actorId,
            'attachment',
            $attachment['original_name'] ?? null,
            null,
            'Anexo removido: ' . (string) ($attachment['original_name'] ?? 'arquivo')
        );
        $this->auditRepo()->create('service_order', $serviceOrderId, 'attachment_delete', $actorId, [
            'attachment_id' => $attachmentId,
            'name' => $attachment['original_name'] ?? null,
        ]);
    }

    public function report(array $filters): array
    {
        $reportData = $this->orderRepo()->reportRows($filters);
        $rows = is_array($reportData['rows'] ?? null) ? $reportData['rows'] : [];
        $total = (int) ($reportData['total'] ?? count($rows));
        $limit = (int) ($reportData['limit'] ?? count($rows));
        $totals = [
            'aberto' => 0,
            'em_andamento' => 0,
            'concluido' => 0,
            'faturado' => 0,
            'valor_faturado' => 0.0,
            'tempo_medio_horas' => 0.0,
        ];
        $durations = [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $totals)) {
                $totals[$status]++;
            }
            if ($status === ServiceOrderStatus::FATURADO) {
                $totals['valor_faturado'] += (float) ($row['final_amount'] ?? 0);
            }

            $opened = strtotime((string) ($row['opened_at'] ?? ''));
            $completed = strtotime((string) ($row['completed_at'] ?? ''));
            if ($opened !== false && $completed !== false && $completed >= $opened) {
                $durations[] = round(($completed - $opened) / 3600, 2);
            }
        }

        if (count($durations) > 0) {
            $totals['tempo_medio_horas'] = round(array_sum($durations) / count($durations), 2);
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'total' => $total,
            'limit' => $limit,
            'truncated' => $total > count($rows),
        ];
    }

    private function recordDiffHistory(array $existing, array $data, int $actorId): void
    {
        $labels = [
            'service_name' => 'Nome do serviço',
            'client_id' => 'Cliente',
            'contact_name' => 'Contato responsável',
            'assigned_user_id' => 'Responsável interno',
            'type' => 'Tipo',
            'type_other_description' => 'Descrição do tipo',
            'status' => 'Status',
            'opened_at' => 'Data de abertura',
            'due_at' => 'Data prevista',
            'completed_at' => 'Data de conclusão',
            'estimated_hours' => 'Horas previstas',
            'executed_hours' => 'Horas executadas',
            'billable' => 'Possui cobrança',
            'base_service_id' => 'Serviço base',
            'base_amount' => 'Valor base',
            'discount_amount' => 'Desconto',
            'surcharge_amount' => 'Acréscimo',
            'final_amount' => 'Valor final',
        ];

        foreach ($labels as $field => $label) {
            $before = $existing[$field] ?? null;
            $after = $data[$field] ?? null;
            if ($this->normalizeCompare($before) === $this->normalizeCompare($after)) {
                continue;
            }
            $this->historyRepo()->create(
                (int) $existing['id'],
                'update',
                $actorId,
                $field,
                $before,
                $after,
                $label . ' atualizado.'
            );
        }
    }

    private function validated(array $payload): array
    {
        $validation = (new ServiceOrderValidator())->validate($payload);
        if (!($validation['ok'] ?? false)) {
            $errors = (array) ($validation['errors'] ?? []);
            throw new \RuntimeException((string) reset($errors));
        }
        return (array) ($validation['data'] ?? []);
    }

    private function requireOrder(int $id): array
    {
        $row = $this->orderRepo()->find($id);
        if ($row === null) {
            throw new \RuntimeException('Ordem de serviço não encontrada.');
        }
        return $row;
    }

    private function normalizeCompare(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }
        return trim((string) $value);
    }

    private function triggerApprovalWorkflow(array $before, array $after, int $actorId): void
    {
        $serviceOrderId = (int) ($after['id'] ?? 0);
        if ($serviceOrderId <= 0) {
            return;
        }

        $afterStatus = (string) ($after['status'] ?? '');
        if (!in_array($afterStatus, [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true)) {
            return;
        }

        $shouldGenerate = $before === []
            || (new ServiceOrderApprovalService())->shouldAutoGenerate($before, $after);
        if (!$shouldGenerate) {
            return;
        }

        try {
            (new ServiceOrderApprovalService())->generateForServiceOrder($serviceOrderId, $actorId);
        } catch (\Throwable $e) {
            $this->historyRepo()->create(
                $serviceOrderId,
                'approval_link_error',
                $actorId,
                null,
                null,
                null,
                'Falha ao gerar o link externo de aprovação: ' . $e->getMessage()
            );
            $this->auditRepo()->create('service_order', $serviceOrderId, 'approval_link_error', $actorId, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function orderRepo(): ServiceOrderRepositoryContract
    {
        return $this->orders ?? new ServiceOrderRepository();
    }

    private function attachmentRepo(): ServiceOrderAttachmentRepositoryContract
    {
        return $this->attachments ?? new ServiceOrderAttachmentRepository();
    }

    private function historyRepo(): ServiceOrderHistoryRepositoryContract
    {
        return $this->history ?? new ServiceOrderHistoryRepository();
    }

    private function auditRepo(): AuditLogRepositoryContract
    {
        return $this->audit ?? new AuditLogRepository();
    }
}
