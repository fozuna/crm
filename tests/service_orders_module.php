<?php
declare(strict_types=1);

use App\Contracts\AuditLogRepositoryContract;
use App\Contracts\ServiceOrderAttachmentRepositoryContract;
use App\Contracts\ServiceOrderHistoryRepositoryContract;
use App\Contracts\ServiceOrderRepositoryContract;
use App\Services\ServiceOrderPdfGenerator;
use App\Services\ServiceOrderRichText;
use App\Services\ServiceOrderService;
use App\Services\ServiceOrderStatus;
use App\Services\ServiceOrderType;
use App\Services\ServiceOrderValidator;

$failures = 0;
$assert = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL- {$message}\n";
};

final class FakeServiceOrderRepository implements ServiceOrderRepositoryContract
{
    public array $rows = [];
    public int $nextId = 1;

    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        return ['rows' => array_values($this->rows), 'page' => 1, 'per_page' => $perPage, 'total' => count($this->rows), 'pages' => 1];
    }

    public function find(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function create(array $data, int $actorId): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = array_merge($data, [
            'id' => $id,
            'numero_sequencial' => $id,
            'numero_os' => 'OS-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT),
            'financial_receivable_id' => null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'client_name' => 'Cliente Teste',
            'client_company' => 'Cliente Teste LTDA',
            'assigned_user_name' => 'Fabio Ozuna',
        ]);
        return $id;
    }

    public function update(int $id, array $data, int $actorId): void
    {
        $this->rows[$id] = array_merge($this->rows[$id], $data, ['updated_by' => $actorId]);
    }

    public function updateStatus(int $id, string $status, int $actorId, ?string $completedAt = null): void
    {
        $this->rows[$id]['status'] = $status;
        $this->rows[$id]['completed_at'] = $completedAt;
        $this->rows[$id]['updated_by'] = $actorId;
    }

    public function markDeleted(int $id, int $actorId): void
    {
        $this->rows[$id]['deleted_at'] = '2026-07-01 12:00:00';
        $this->rows[$id]['updated_by'] = $actorId;
    }

    public function attachFinancialReceivable(int $id, ?int $receivableId, int $actorId, ?string $status = null): void
    {
        $this->rows[$id]['financial_receivable_id'] = $receivableId;
        if ($status !== null) {
            $this->rows[$id]['status'] = $status;
        }
        $this->rows[$id]['updated_by'] = $actorId;
    }

    public function nextSequence(): int
    {
        return $this->nextId;
    }

    public function listByClient(int $clientId, int $limit = 20): array
    {
        return array_values(array_filter($this->rows, static fn(array $row): bool => (int) ($row['client_id'] ?? 0) === $clientId));
    }
}

final class FakeServiceOrderAttachmentRepository implements ServiceOrderAttachmentRepositoryContract
{
    public array $items = [];

    public function create(int $serviceOrderId, array $data, ?int $actorId): int
    {
        $id = count($this->items) + 1;
        $this->items[$id] = array_merge($data, ['id' => $id, 'servico_avulso_id' => $serviceOrderId, 'uploaded_by' => $actorId]);
        return $id;
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        return array_values(array_filter($this->items, static fn(array $row): bool => (int) ($row['servico_avulso_id'] ?? 0) === $serviceOrderId));
    }

    public function find(int $id): ?array
    {
        return $this->items[$id] ?? null;
    }

    public function delete(int $id): void
    {
        unset($this->items[$id]);
    }
}

final class FakeServiceOrderHistoryRepository implements ServiceOrderHistoryRepositoryContract
{
    public array $items = [];

    public function create(int $serviceOrderId, string $action, ?int $actorId, ?string $fieldName = null, mixed $oldValue = null, mixed $newValue = null, ?string $message = null, ?array $metadata = null): int
    {
        $this->items[] = compact('serviceOrderId', 'action', 'actorId', 'fieldName', 'oldValue', 'newValue', 'message', 'metadata');
        return count($this->items);
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        return array_values(array_filter($this->items, static fn(array $row): bool => $row['serviceOrderId'] === $serviceOrderId));
    }
}

final class FakeServiceOrderAuditRepository implements AuditLogRepositoryContract
{
    public array $items = [];

    public function create(string $entityType, int $entityId, string $action, ?int $actorId, ?array $data): void
    {
        $this->items[] = compact('entityType', 'entityId', 'action', 'actorId', 'data');
    }
}

final class FakeServiceOrderTransaction
{
    public bool $started = false;
    public bool $committed = false;
    public bool $rolledBack = false;

    public function beginTransaction(): void
    {
        $this->started = true;
    }

    public function commit(): void
    {
        $this->committed = true;
    }

    public function rollBack(): void
    {
        $this->rolledBack = true;
    }

    public function inTransaction(): bool
    {
        return $this->started && !$this->committed && !$this->rolledBack;
    }
}

$validator = new ServiceOrderValidator();
$validPayload = [
    'service_name' => 'Correção de integração fiscal',
    'client_id' => 10,
    'contact_name' => 'Fabio Ozuna',
    'assigned_user_id' => 3,
    'type' => ServiceOrderType::CORRECAO,
    'status' => ServiceOrderStatus::ABERTO,
    'request_description' => '<p>Solicitação com <strong>urgência</strong>.</p>',
    'executed_activities' => '<ul><li>Diagnóstico</li><li>Ajuste</li></ul>',
    'technical_notes' => '<p>Sem impacto estrutural.</p>',
    'internal_notes' => 'Acompanhar retorno do cliente.',
    'opened_at' => '2026-07-01T08:00',
    'due_at' => '2026-07-02T18:00',
    'estimated_hours' => '2,50',
    'executed_hours' => '1,50',
    'billable' => '0',
];

$validation = $validator->validate($validPayload);
$assert(($validation['ok'] ?? false) === true, 'Validador aceita OS não faturável válida');
$assert(($validation['data']['request_description'] ?? '') === '<p>Solicitação com <strong>urgência</strong>.</p>', 'Validador mantém HTML permitido sanitizado');
$billableValidation = $validator->validate(array_merge($validPayload, [
    'billable' => '1',
    'base_service_id' => '5',
    'base_amount' => '120,00',
    'discount_amount' => '15,00',
    'surcharge_amount' => '20,00',
]));
$assert(($billableValidation['ok'] ?? false) === true, 'Validador aceita OS faturável com horas previstas válidas');
$assert((float) ($billableValidation['data']['final_amount'] ?? 0) === 305.00, 'Validador calcula valor final com horas previstas, desconto e acréscimo');
$assert(($validator->validate(array_merge($validPayload, ['type' => 'invalido']))['ok'] ?? true) === false, 'Validador rejeita tipo inválido');
$assert(($validator->validate(array_merge($validPayload, ['billable' => '1', 'base_service_id' => '5', 'base_amount' => '0,00']))['ok'] ?? true) === false, 'Validador exige valor base positivo para OS faturável');
$assert(($validator->validate(array_merge($validPayload, ['billable' => '1', 'base_service_id' => '5', 'estimated_hours' => '0']))['ok'] ?? true) === false, 'Validador exige horas previstas positivas para OS faturável');
$assert(($validator->validate(array_merge($validPayload, ['billable' => '1', 'base_service_id' => '5', 'discount_amount' => '-5,00']))['ok'] ?? true) === false, 'Validador rejeita desconto negativo');

$richText = new ServiceOrderRichText();
$sanitized = $richText->sanitize('<p>Teste</p><script>alert(1)</script><a href="javascript:alert(1)">link</a>');
$assert(!str_contains($sanitized, '<script'), 'Sanitizador remove scripts');
$assert(!str_contains($sanitized, 'javascript:'), 'Sanitizador remove links inseguros');

$repo = new FakeServiceOrderRepository();
$attachments = new FakeServiceOrderAttachmentRepository();
$history = new FakeServiceOrderHistoryRepository();
$audit = new FakeServiceOrderAuditRepository();
$transaction = new FakeServiceOrderTransaction();
$service = new ServiceOrderService($repo, $attachments, $history, $audit, $transaction);

$created = $service->create($validPayload, 7);
$createdId = (int) ($created['id'] ?? 0);
$assert($createdId > 0, 'Service cria OS e retorna identificador');
$assert($transaction->committed === true, 'Service comita transação ao criar OS');
$assert(count($history->items) >= 1, 'Service registra histórico na criação');
$assert(count($audit->items) >= 1, 'Service registra auditoria na criação');

$updated = $service->update($createdId, array_merge($validPayload, [
    'status' => ServiceOrderStatus::EM_ANDAMENTO,
    'executed_hours' => '2,00',
]), 7);
$assert((string) ($updated['status'] ?? '') === ServiceOrderStatus::EM_ANDAMENTO, 'Service atualiza status da OS');
$assert(count($history->items) >= 2, 'Service registra histórico de atualização');

$service->updateStatus($createdId, ServiceOrderStatus::CONCLUIDO, 7);
$assert((string) ($repo->find($createdId)['status'] ?? '') === ServiceOrderStatus::CONCLUIDO, 'Service altera status da OS diretamente');

$report = $service->report([]);
$assert((int) ($report['totals']['concluido'] ?? 0) >= 1, 'Relatório agrega OS concluídas');

$pdf = (new ServiceOrderPdfGenerator())->build(
    [
        'company_name' => 'TRAXTER',
        'primary_color' => '#293241',
        'accent_color' => '#ee6c4d',
        'logo_path' => '',
        'company_cnpj' => '30358115000113',
    ],
    array_merge($created, [
        'numero_os' => 'OS-000001',
        'client_company' => 'Cliente Teste LTDA',
        'assigned_user_name' => 'Fabio Ozuna',
        'type' => ServiceOrderType::CORRECAO,
        'status' => ServiceOrderStatus::CONCLUIDO,
        'opened_at' => '2026-07-01 08:00:00',
        'completed_at' => '2026-07-01 10:00:00',
        'base_amount' => 150.00,
        'discount_amount' => 0,
        'surcharge_amount' => 0,
        'final_amount' => 150.00,
    ]),
    [
        ['original_name' => 'print.png', 'file_size' => 1200, 'uploaded_by_name' => 'Fabio Ozuna', 'mime_type' => 'image/png'],
    ],
    [
        ['message' => 'Ordem criada.', 'created_at' => '2026-07-01 08:00:00'],
    ]
);
$assert(str_starts_with($pdf, '%PDF-1.4'), 'PDF da OS é gerado em formato PDF');
$assert(str_contains($pdf, 'Ordem de Serv') || str_contains($pdf, 'Ordem de Serviço'), 'PDF da OS contém título principal');
$assert(str_contains($pdf, 'OS-000001'), 'PDF da OS contém número da ordem');
$assert(str_contains($pdf, 'Resumo financ'), 'PDF da OS contém quadro financeiro estruturado');
$assert(str_contains($pdf, 'Hist'), 'PDF da OS contém tabela de histórico');

return $failures;
