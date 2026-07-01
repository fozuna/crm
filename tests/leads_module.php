<?php
declare(strict_types=1);

use App\Contracts\AuditLogRepositoryContract;
use App\Contracts\ClientInteractionRepositoryContract;
use App\Contracts\ClientRepositoryContract;
use App\Contracts\LeadInteractionRepositoryContract;
use App\Contracts\LeadRepositoryContract;
use App\Contracts\LeadStageHistoryRepositoryContract;
use App\Services\LeadPipelineNavigation;
use App\Services\LeadProposalPrefillService;
use App\Services\LeadService;
use App\Services\LeadStages;
use App\Services\LeadValidator;

$leadFailures = 0;
$leadAssert = static function (bool $ok, string $message) use (&$leadFailures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $leadFailures++;
    echo "FAIL- {$message}\n";
};

final class FakeLeadRepository implements LeadRepositoryContract
{
    public array $rows = [];
    public int $nextId = 1;

    public function listKanban(string $query = ''): array
    {
        return array_values(array_filter($this->rows, static function (array $row) use ($query): bool {
            if (($row['converted_at'] ?? null) !== null) {
                return false;
            }
            if ($query === '') {
                return true;
            }
            return str_contains(strtolower((string) ($row['name'] ?? '')), strtolower($query));
        }));
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
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'created_at' => '2026-05-14 10:00:00',
            'updated_at' => '2026-05-14 10:00:00',
            'converted_at' => null,
            'converted_client_id' => null,
        ]);
        return $id;
    }

    public function update(int $id, array $data, int $actorId): void
    {
        $this->rows[$id] = array_merge($this->rows[$id], $data, ['updated_by' => $actorId]);
    }

    public function updateStage(int $id, string $stage, int $actorId): void
    {
        $this->rows[$id]['stage'] = $stage;
        $this->rows[$id]['updated_by'] = $actorId;
    }

    public function markConverted(int $id, int $clientId, int $actorId): void
    {
        $this->rows[$id]['stage'] = LeadStages::APROVADO;
        $this->rows[$id]['converted_at'] = '2026-05-14 12:00:00';
        $this->rows[$id]['converted_client_id'] = $clientId;
        $this->rows[$id]['updated_by'] = $actorId;
    }

    public function duplicateCounts(array $data, ?int $excludeLeadId = null): array
    {
        return ['document_number' => 0, 'email' => 0, 'phone' => 0, 'secondary_phone' => 0];
    }
}

final class FakeLeadStageHistoryRepository implements LeadStageHistoryRepositoryContract
{
    public array $items = [];

    public function create(int $leadId, ?string $fromStage, string $toStage, ?int $actorId, string $action = 'move', ?string $note = null): int
    {
        $this->items[] = compact('leadId', 'fromStage', 'toStage', 'actorId', 'action', 'note');
        return count($this->items);
    }

    public function listByLead(int $leadId): array
    {
        $rows = array_values(array_reverse(array_filter($this->items, static fn(array $item): bool => $item['leadId'] === $leadId)));
        foreach ($rows as &$row) {
            $row['from_stage_label'] = ($row['fromStage'] ?? null) !== null
                ? LeadStages::label((string) $row['fromStage'])
                : 'Inicial';
            $row['to_stage_label'] = LeadStages::label((string) ($row['toStage'] ?? ''));
            $row['created_at'] = $row['created_at'] ?? '2026-05-14 10:30:00';
        }

        return $rows;
    }
}

final class FakeLeadInteractionRepository implements LeadInteractionRepositoryContract
{
    public array $items = [];

    public function listByLead(int $leadId): array
    {
        return array_values(array_filter($this->items, static fn(array $item): bool => $item['lead_id'] === $leadId));
    }

    public function create(int $leadId, string $kind, string $note, ?int $createdBy): int
    {
        $this->items[] = [
            'id' => count($this->items) + 1,
            'lead_id' => $leadId,
            'kind' => $kind,
            'note' => $note,
            'created_by' => $createdBy,
            'created_at' => '2026-05-14 11:00:00',
        ];
        return count($this->items);
    }
}

final class FakeClientRepository implements ClientRepositoryContract
{
    public array $created = [];
    public array $rows = [];
    public int $nextId = 501;

    public function find(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findBySourceLeadId(int $leadId): ?array
    {
        foreach ($this->rows as $row) {
            if ((int) ($row['source_lead_id'] ?? 0) === $leadId) {
                return $row;
            }
        }

        return null;
    }

    public function createFromLead(array $data): int
    {
        $this->created[] = $data;
        $id = $this->nextId++;
        $this->rows[$id] = array_merge($data, ['id' => $id]);
        return $id;
    }

    public function createProposalProspectFromLead(array $lead): int
    {
        $payload = [
            'name' => (string) ($lead['name'] ?? ''),
            'email' => (string) ($lead['email'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'company' => (string) (($lead['company'] ?? '') !== '' ? $lead['company'] : ($lead['name'] ?? '')),
            'contact_person' => (string) (($lead['contact_person'] ?? '') !== '' ? $lead['contact_person'] : ($lead['name'] ?? '')),
            'status' => 'lead',
            'project_reference' => 'Origem no Kanban de leads #' . (int) ($lead['id'] ?? 0),
            'source_lead_id' => (int) ($lead['id'] ?? 0),
        ];

        return $this->createFromLead($payload);
    }

    public function promoteLeadProspectToActive(int $clientId, array $data): void
    {
        $this->rows[$clientId] = array_merge($this->rows[$clientId] ?? ['id' => $clientId], $data, ['id' => $clientId]);
    }
}

final class FakeClientInteractionRepository implements ClientInteractionRepositoryContract
{
    public array $items = [];

    public function createHistorical(int $clientId, string $kind, string $note, string $createdAt): int
    {
        $this->items[] = compact('clientId', 'kind', 'note', 'createdAt');
        return count($this->items);
    }
}

final class FakeAuditLogRepository implements AuditLogRepositoryContract
{
    public array $items = [];

    public function create(string $entityType, int $entityId, string $action, ?int $actorId, ?array $data): void
    {
        $this->items[] = compact('entityType', 'entityId', 'action', 'actorId', 'data');
    }
}

final class FakeTransaction
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

$validator = new LeadValidator();
$validLead = [
    'name' => 'Dona Consultorias Ltda',
    'company' => 'Dona Consultorias',
    'contact_person' => 'Fabio Ozuna',
    'person_type' => 'pj',
    'document_number' => '30.358.115/0001-13',
    'email' => 'contato@donaconsultorias.com.br',
    'phone' => '(67) 99325-6260',
    'secondary_phone' => '(67) 3322-1000',
    'postal_code' => '79002-200',
    'street' => 'Rua Exemplo',
    'street_number' => '100',
    'address_complement' => 'Sala 5',
    'neighborhood' => 'Centro',
    'city' => 'Campo Grande',
    'state' => 'MS',
    'birth_or_opening_date' => '2020-05-10',
    'market_segment' => 'Consultoria',
    'acquisition_source' => 'Indicação',
    'stage' => LeadStages::CADASTRO_REALIZADO,
    'notes' => 'Lead estratégico',
];

$leadAssert(($validator->validate($validLead)['ok'] ?? false) === true, 'Validador aceita lead completo com CNPJ válido');
$leadAssert(($validator->validate(array_merge($validLead, ['document_number' => '11.111.111/1111-11']))['ok'] ?? true) === false, 'Validador rejeita CNPJ inválido');
$leadAssert(($validator->validate(array_merge($validLead, ['email' => 'email-invalido']))['ok'] ?? true) === false, 'Validador rejeita e-mail inválido');
$leadAssert(($validator->validate($validLead, ['email' => 1])['ok'] ?? true) === false, 'Validador bloqueia contatos duplicados');

$leadRepo = new FakeLeadRepository();
$historyRepo = new FakeLeadStageHistoryRepository();
$interactionRepo = new FakeLeadInteractionRepository();
$clientRepo = new FakeClientRepository();
$clientInteractionRepo = new FakeClientInteractionRepository();
$auditRepo = new FakeAuditLogRepository();
$transaction = new FakeTransaction();

$service = new LeadService(
    $leadRepo,
    $historyRepo,
    $interactionRepo,
    $clientRepo,
    $clientInteractionRepo,
    $auditRepo,
    $transaction
);

$created = $service->create($validLead, 7);
$leadAssert((int) ($created['id'] ?? 0) === 1, 'Serviço cria lead e retorna identificador');
$leadAssert(count($historyRepo->items) === 1 && $historyRepo->items[0]['action'] === 'create', 'Cadastro registra histórico inicial do Kanban');

$service->addInteraction(1, 'email', 'Primeiro contato realizado.', 7);
$leadAssert(count($interactionRepo->items) === 1, 'Serviço registra interação comercial do lead');

$moved = $service->move(1, LeadStages::PRONTO_APROVACAO, 7, 'Lead pronto para revisão final.');
$leadAssert((string) ($moved['stage'] ?? '') === LeadStages::PRONTO_APROVACAO, 'Movimentação atualiza estágio do lead');

$converted = $service->convert(1, [
    'billing_email' => 'financeiro@donaconsultorias.com.br',
    'billing_phone' => '(67) 99999-0000',
    'contract_notes' => 'Contrato anual com reajuste.',
    'billing_notes' => 'Vencimento todo dia 10.',
], 7);

$leadAssert((int) ($converted['client_id'] ?? 0) === 501, 'Conversão cria cliente ativo');
$leadAssert(($leadRepo->rows[1]['converted_client_id'] ?? 0) === 501, 'Lead convertido sai do Kanban e mantém vínculo com cliente criado');
$leadAssert(count($clientRepo->created) === 1 && (string) ($clientRepo->created[0]['billing_email'] ?? '') === 'financeiro@donaconsultorias.com.br', 'Conversão migra e complementa dados de faturamento');
$leadAssert(count($clientInteractionRepo->items) === 1 && str_contains((string) $clientInteractionRepo->items[0]['note'], '[Lead #1]'), 'Conversão preserva histórico de interações no cliente ativo');
$leadAssert($transaction->started && $transaction->committed && !$transaction->rolledBack, 'Conversão executa transação com commit bem-sucedido');
$leadAssert(count($auditRepo->items) >= 4, 'Fluxo completo gera trilha de auditoria');

$navigation = new LeadPipelineNavigation();
$leadAssert(
    $navigation->proposalRedirectUrl(15, LeadStages::EM_CONTATO, LeadStages::PROPOSTA_ENVIADA, '/crm') === '/crm/propostas/nova?lead_id=15',
    'Redirecionamento para proposta ocorre apenas ao entrar em Proposta Enviada'
);
$leadAssert(
    $navigation->proposalRedirectUrl(15, LeadStages::PROPOSTA_ENVIADA, LeadStages::PROPOSTA_ENVIADA, '/crm') === null,
    'Redirecionamento nao ocorre ao permanecer na mesma etapa'
);
$leadAssert(
    $navigation->proposalRedirectUrl(15, LeadStages::EM_CONTATO, LeadStages::NEGOCIACAO, '/crm') === null,
    'Outras etapas do pipeline nao disparam redirecionamento'
);

$prefillLead = array_merge($validLead, [
    'name' => 'Lead Proposta',
    'company' => 'Lead Proposta LTDA',
    'contact_person' => 'Contato Comercial',
    'document_number' => '12.345.678/0001-95',
    'email' => 'proposta@leadteste.com.br',
    'phone' => '(67) 99111-2233',
    'secondary_phone' => '(67) 3322-4455',
    'notes' => 'Necessita landing page, CRM comercial e investimento estimado em R$ 4.500,00.',
]);
$prefillCreated = $service->create($prefillLead, 7);
$prefillLeadId = (int) ($prefillCreated['id'] ?? 0);
$service->addInteraction($prefillLeadId, 'meeting', 'Necessidade confirmada: funil comercial com proposta inicial de R$ 4.500,00.', 7);
$service->move($prefillLeadId, LeadStages::PROPOSTA_ENVIADA, 7, 'Lead pronto para gerar proposta.');

$prefillService = new LeadProposalPrefillService($leadRepo, $interactionRepo, $historyRepo, $clientRepo);
$prefill = $prefillService->build($prefillLeadId, '/crm');

$leadAssert((int) ($prefill['proposal']['source_lead_id'] ?? 0) === $prefillLeadId, 'Prefill vincula a proposta ao lead de origem');
$leadAssert((int) ($prefill['proposal']['client_id'] ?? 0) > 0, 'Prefill garante cliente base para a proposta');
$leadAssert(str_contains((string) ($prefill['proposal']['description'] ?? ''), 'R$ 4.500,00'), 'Prefill reaproveita necessidades e valores identificados no atendimento');
$leadAssert((string) ($prefill['summary']['contact'] ?? '') === 'Contato Comercial', 'Prefill inclui dados cadastrais do contato do lead');
$leadAssert((string) ($prefill['summary']['history'][0]['title'] ?? '') === 'Proposta Enviada', 'Prefill inclui historico recente do funil comercial');
$leadAssert((string) ($prefill['retry_url'] ?? '') === '/crm/propostas/nova?lead_id=' . $prefillLeadId, 'Prefill fornece link de retentativa em caso de falha');

return $leadFailures;
