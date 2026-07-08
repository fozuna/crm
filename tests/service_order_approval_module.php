<?php
declare(strict_types=1);

use App\Contracts\AuditLogRepositoryContract;
use App\Contracts\ServiceOrderHistoryRepositoryContract;
use App\Contracts\ServiceOrderRepositoryContract;
use App\Core\Config;
use App\Repositories\ServiceOrderApprovalEventRepository;
use App\Repositories\ServiceOrderApprovalNotificationRepository;
use App\Repositories\ServiceOrderApprovalRepository;
use App\Services\ServiceOrderApprovalGeoService;
use App\Services\ServiceOrderApprovalService;
use App\Services\ServiceOrderApprovalTokenService;
use App\Services\ServiceOrderStatus;
use App\Services\SystemMailer;

$failures = 0;
$assert = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL- {$message}\n";
};

Config::setAll(array_merge(Config::all(), [
    'APP_KEY' => 'service-order-approval-test-key',
    'APP_URL' => 'https://crmtraxter.test/gestor',
    'APPROVAL_REQUIRE_HTTPS' => true,
    'SERVICE_ORDER_APPROVAL_TTL_HOURS' => 48,
]));

class FakeApprovalOrderRepository implements ServiceOrderRepositoryContract
{
    public function __construct(private array $row)
    {
    }

    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        return ['rows' => [$this->row], 'page' => 1, 'per_page' => 20, 'total' => 1, 'pages' => 1];
    }

    public function find(int $id): ?array
    {
        return (int) ($this->row['id'] ?? 0) === $id ? $this->row : null;
    }

    public function create(array $data, int $actorId): int
    {
        return 0;
    }

    public function update(int $id, array $data, int $actorId): void
    {
    }

    public function updateStatus(int $id, string $status, int $actorId, ?string $completedAt = null): void
    {
    }

    public function markDeleted(int $id, int $actorId): void
    {
    }

    public function attachFinancialReceivable(int $id, ?int $receivableId, int $actorId, ?string $status = null): void
    {
    }

    public function nextSequence(): int
    {
        return 1;
    }

    public function listByClient(int $clientId, int $limit = 20): array
    {
        return [];
    }
}

class FakeApprovalRepository extends ServiceOrderApprovalRepository
{
    public array $rows = [];
    private int $nextId = 1;

    public function findByServiceOrderId(int $serviceOrderId): ?array
    {
        foreach ($this->rows as $row) {
            if ((int) ($row['servico_avulso_id'] ?? 0) === $serviceOrderId) {
                return $row;
            }
        }
        return null;
    }

    public function findByPublicId(string $publicId): ?array
    {
        foreach ($this->rows as $row) {
            if ((string) ($row['public_id'] ?? '') === $publicId) {
                return $row;
            }
        }
        return null;
    }

    public function upsertGenerated(int $serviceOrderId, array $payload, ?int $actorId): array
    {
        $existing = $this->findByServiceOrderId($serviceOrderId);
        $base = [
            'id' => $existing['id'] ?? $this->nextId++,
            'servico_avulso_id' => $serviceOrderId,
            'numero_os' => 'OS-000321',
            'service_name' => 'Ajuste de integração',
            'service_order_status' => ServiceOrderStatus::CONCLUIDO,
            'client_id' => 15,
            'contact_name' => 'Fabio Ozuna',
            'assigned_user_id' => 5,
            'assigned_user_name' => 'Analista Interno',
            'assigned_user_email' => 'analista@traxter.com.br',
            'client_name' => 'Cliente Teste',
            'client_company' => 'Cliente Teste LTDA',
            'client_email' => 'cliente@teste.com.br',
            'client_billing_email' => 'financeiro@cliente.com.br',
            'client_phone' => '67999990000',
            'client_billing_phone' => '6733334444',
            'client_contact_person' => 'Fabio Ozuna',
            'request_description' => '<p>Validar processamento da API.</p>',
            'executed_activities' => '<p>Executado ajuste e homologação.</p>',
            'technical_notes' => '<p>Sem impacto estrutural.</p>',
            'final_amount' => 250.75,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'opened_at' => '2026-07-03 08:00:00',
            'completed_at' => '2026-07-03 10:00:00',
            'status' => 'pendente',
            'token_used_at' => null,
            'token_last_access_at' => null,
            'token_revoked_at' => null,
            'requester_name' => null,
            'requester_email' => null,
            'requester_phone' => null,
            'justification' => null,
            'actor_identifier' => null,
            'actor_ip' => null,
            'actor_user_agent' => null,
            'actor_geo_summary' => null,
            'actor_geo_json' => null,
            'first_access_at' => null,
            'decision_at' => null,
            'proof_pdf_path' => null,
            'proof_pdf_hash' => null,
            'proof_pdf_generated_at' => null,
            'email_sent_at' => null,
            'sms_status' => 'indisponivel',
            'sms_message' => null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'created_at' => '2026-07-03 09:00:00',
            'updated_at' => '2026-07-03 09:00:00',
        ];

        $row = array_merge($base, $payload);
        $this->rows[$row['id']] = $row;
        return $row;
    }

    public function markAccess(int $approvalId, string $accessedAt): void
    {
        $this->rows[$approvalId]['token_last_access_at'] = $accessedAt;
        $this->rows[$approvalId]['first_access_at'] = $this->rows[$approvalId]['first_access_at'] ?? $accessedAt;
    }

    public function markEmailSent(int $approvalId, string $sentAt): void
    {
        $this->rows[$approvalId]['email_sent_at'] = $sentAt;
    }

    public function recordDecision(int $approvalId, array $payload, ?int $actorId): void
    {
        $this->rows[$approvalId] = array_merge($this->rows[$approvalId], $payload, ['updated_by' => $actorId]);
    }

    public function markExpired(int $approvalId): void
    {
        $this->rows[$approvalId]['status'] = 'expirada';
    }

    public function attachProof(int $approvalId, string $path, string $hash, string $generatedAt, ?int $actorId): void
    {
        $this->rows[$approvalId]['proof_pdf_path'] = $path;
        $this->rows[$approvalId]['proof_pdf_hash'] = $hash;
        $this->rows[$approvalId]['proof_pdf_generated_at'] = $generatedAt;
        $this->rows[$approvalId]['updated_by'] = $actorId;
    }
}

class FakeApprovalEventRepository extends ServiceOrderApprovalEventRepository
{
    public array $items = [];

    public function create(int $serviceOrderId, int $approvalId, string $eventType, array $payload = []): int
    {
        $id = count($this->items) + 1;
        $this->items[$id] = [
            'id' => $id,
            'service_order_id' => $serviceOrderId,
            'approval_id' => $approvalId,
            'event_type' => $eventType,
            'payload' => $payload,
        ];
        return $id;
    }
}

class FakeApprovalNotificationRepository extends ServiceOrderApprovalNotificationRepository
{
    public array $items = [];

    public function create(int $serviceOrderId, int $approvalId, array $payload): int
    {
        $id = count($this->items) + 1;
        $this->items[$id] = array_merge($payload, [
            'id' => $id,
            'service_order_id' => $serviceOrderId,
            'approval_id' => $approvalId,
        ]);
        return $id;
    }
}

class FakeApprovalAuditRepository implements AuditLogRepositoryContract
{
    public array $items = [];

    public function create(string $entityType, int $entityId, string $action, ?int $actorId, ?array $data): void
    {
        $this->items[] = compact('entityType', 'entityId', 'action', 'actorId', 'data');
    }
}

class FakeApprovalHistoryRepository implements ServiceOrderHistoryRepositoryContract
{
    public array $items = [];

    public function create(int $serviceOrderId, string $action, ?int $actorId, ?string $fieldName = null, mixed $oldValue = null, mixed $newValue = null, ?string $message = null, ?array $metadata = null): int
    {
        $this->items[] = compact('serviceOrderId', 'action', 'actorId', 'fieldName', 'oldValue', 'newValue', 'message', 'metadata');
        return count($this->items);
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        return array_values(array_filter($this->items, static fn(array $item): bool => $item['serviceOrderId'] === $serviceOrderId));
    }
}

class FakeApprovalMailer extends SystemMailer
{
    public array $sent = [];

    public function send(string $to, string $subject, string $message, ?string $replyTo = null): array
    {
        $this->sent[] = compact('to', 'subject', 'message', 'replyTo');
        return ['ok' => true, 'error' => null];
    }
}

class FakeApprovalTransaction
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

$tokenService = new ServiceOrderApprovalTokenService();
$token = $tokenService->issue(['sub' => 'service-order-approval', 'public_id' => 'abc123', 'service_order_id' => 1, 'client_id' => 10]);
$payload = $tokenService->validate($token);
$assert(($payload['public_id'] ?? '') === 'abc123', 'Token criptografado valida corretamente');
$expired = $tokenService->issue(['sub' => 'service-order-approval', 'public_id' => 'old', 'service_order_id' => 1, 'client_id' => 10], new DateTimeImmutable('-1 hour'));
$assert($tokenService->validate($expired) === [], 'Token expirado é rejeitado');

$approvalRepo = new FakeApprovalRepository();
$eventRepo = new FakeApprovalEventRepository();
$notificationRepo = new FakeApprovalNotificationRepository();
$auditRepo = new FakeApprovalAuditRepository();
$historyRepo = new FakeApprovalHistoryRepository();
$mailer = new FakeApprovalMailer();
$transaction = new FakeApprovalTransaction();
$orderRepo = new FakeApprovalOrderRepository([
    'id' => 321,
    'numero_os' => 'OS-000321',
    'service_name' => 'Ajuste de integração',
    'status' => ServiceOrderStatus::CONCLUIDO,
    'client_id' => 15,
    'client_email' => 'cliente@teste.com.br',
    'client_billing_email' => 'financeiro@cliente.com.br',
    'client_phone' => '67999990000',
    'client_billing_phone' => '6733334444',
    'client_name' => 'Cliente Teste',
    'client_company' => 'Cliente Teste LTDA',
    'client_contact_person' => 'Fabio Ozuna',
    'contact_name' => 'Fabio Ozuna',
    'assigned_user_id' => 5,
    'assigned_user_name' => 'Analista Interno',
    'assigned_user_email' => 'analista@traxter.com.br',
    'request_description' => '<p>Validar processamento da API.</p>',
    'executed_activities' => '<p>Executado ajuste e homologação.</p>',
    'technical_notes' => '<p>Sem impacto estrutural.</p>',
    'final_amount' => 250.75,
    'discount_amount' => 0,
    'surcharge_amount' => 0,
    'opened_at' => '2026-07-03 08:00:00',
    'completed_at' => '2026-07-03 10:00:00',
]);

$service = new ServiceOrderApprovalService(
    $approvalRepo,
    $eventRepo,
    $notificationRepo,
    $auditRepo,
    $historyRepo,
    $orderRepo,
    $tokenService,
    new ServiceOrderApprovalGeoService(),
    $mailer,
    $transaction
);

$generated = $service->generateForServiceOrder(321, 7);
$generatedApproval = $generated['approval'];
$assert(($generatedApproval['public_id'] ?? '') !== '', 'Geração cria public_id da aprovação');
$assert(str_contains((string) ($generated['url'] ?? ''), '/os/aprovacao/'), 'Geração retorna URL pública da aprovação');
$assert(count($mailer->sent) >= 1, 'Geração envia e-mail ao cliente');
$assert(count($notificationRepo->items) >= 2, 'Geração registra notificações de e-mail e SMS/outbox');

$publicAccess = $service->resolvePublicAccess((string) $generatedApproval['public_id'], (string) $generated['token'], [
    'HTTP_HOST' => 'crmtraxter.test',
    'HTTPS' => 'on',
    'REMOTE_ADDR' => '179.1.2.3',
    'HTTP_USER_AGENT' => 'PHPUnit Browser',
]);
$assert(($publicAccess['ok'] ?? false) === true, 'Acesso público válido é permitido');

$decision = $service->decide((string) $generatedApproval['public_id'], (string) $generated['token'], [
    'decision' => 'request_adjustments',
    'requester_name' => 'Fabio Ozuna',
    'requester_email' => 'fabio@cliente.com.br',
    'requester_phone' => '67999888777',
    'justification' => 'Ajustar detalhamento das atividades executadas.',
], [
    'HTTP_HOST' => 'crmtraxter.test',
    'HTTPS' => 'on',
    'REMOTE_ADDR' => '179.1.2.3',
    'HTTP_USER_AGENT' => 'PHPUnit Browser',
    'HTTP_CF_IPCOUNTRY' => 'BR',
    'HTTP_X_CITY' => 'Campo Grande',
    'HTTP_X_REGION_CODE' => 'MS',
]);

$savedDecision = $decision['approval'];
$assert((string) ($savedDecision['status'] ?? '') === 'ajustes_solicitados', 'Decisão do cliente atualiza o status da aprovação');
$assert((string) ($savedDecision['justification'] ?? '') === 'Ajustar detalhamento das atividades executadas.', 'Decisão salva justificativa obrigatória');
$assert(is_file((string) ($savedDecision['proof_pdf_path'] ?? '')), 'Decisão gera comprovante PDF vinculado à OS');
$assert($transaction->committed === true, 'Decisão confirma transação com sucesso');
$assert(count($historyRepo->items) >= 1, 'Decisão registra histórico interno');
$assert(count($auditRepo->items) >= 1, 'Decisão registra auditoria interna');

$blockedReuse = $service->resolvePublicAccess((string) $generatedApproval['public_id'], (string) $generated['token'], [
    'HTTP_HOST' => 'crmtraxter.test',
    'HTTPS' => 'on',
    'REMOTE_ADDR' => '179.1.2.3',
    'HTTP_USER_AGENT' => 'PHPUnit Browser',
]);
$assert(($blockedReuse['ok'] ?? true) === false, 'Link utilizado não pode ser reutilizado');

if (is_file((string) ($savedDecision['proof_pdf_path'] ?? ''))) {
    @unlink((string) $savedDecision['proof_pdf_path']);
}

return $failures;
