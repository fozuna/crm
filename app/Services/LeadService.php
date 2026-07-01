<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditLogRepositoryContract;
use App\Contracts\ClientInteractionRepositoryContract;
use App\Contracts\ClientRepositoryContract;
use App\Contracts\LeadInteractionRepositoryContract;
use App\Contracts\LeadRepositoryContract;
use App\Contracts\LeadStageHistoryRepositoryContract;
use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClientInteractionRepository;
use App\Repositories\ClientRepository;
use App\Repositories\LeadInteractionRepository;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStageHistoryRepository;

final class LeadService
{
    public function __construct(
        private readonly ?LeadRepositoryContract $leads = null,
        private readonly ?LeadStageHistoryRepositoryContract $history = null,
        private readonly ?LeadInteractionRepositoryContract $interactions = null,
        private readonly ?ClientRepositoryContract $clients = null,
        private readonly ?ClientInteractionRepositoryContract $clientInteractions = null,
        private readonly ?AuditLogRepositoryContract $audit = null,
        private readonly mixed $transaction = null,
    ) {
    }

    public function create(array $payload, int $actorId): array
    {
        $data = $this->validated($payload, null);
        $id = $this->leadRepo()->create($data, $actorId);
        $this->historyRepo()->create($id, null, (string) $data['stage'], $actorId, 'create', 'Lead cadastrado no Kanban.');
        $this->auditRepo()->create('lead', $id, 'create', $actorId, ['after' => $data]);
        return $this->requireLead($id);
    }

    public function update(int $id, array $payload, int $actorId): array
    {
        $existing = $this->requireLead($id);
        if (($existing['converted_at'] ?? null) !== null) {
            throw new \RuntimeException('Este lead já foi convertido em cliente.');
        }

        $data = $this->validated($payload, $id);
        $this->leadRepo()->update($id, $data, $actorId);

        if ((string) ($existing['stage'] ?? '') !== (string) $data['stage']) {
            $this->historyRepo()->create($id, (string) ($existing['stage'] ?? ''), (string) $data['stage'], $actorId, 'update', 'Estágio ajustado durante edição do cadastro.');
        }

        $this->auditRepo()->create('lead', $id, 'update', $actorId, [
            'before' => $existing,
            'after' => $data,
        ]);

        return $this->requireLead($id);
    }

    public function move(int $id, string $toStage, int $actorId, ?string $note = null): array
    {
        $lead = $this->requireLead($id);
        if (($lead['converted_at'] ?? null) !== null) {
            throw new \RuntimeException('Este lead já foi convertido e não pode mais ser movimentado.');
        }
        if (!LeadStages::isValid($toStage)) {
            throw new \RuntimeException('Estágio de destino inválido.');
        }
        if ($toStage === LeadStages::APROVADO) {
            throw new \RuntimeException('A conversão para cliente deve ser confirmada na etapa de aprovação.');
        }

        $fromStage = (string) ($lead['stage'] ?? LeadStages::CADASTRO_REALIZADO);
        if ($fromStage === $toStage) {
            return $lead;
        }

        $this->leadRepo()->updateStage($id, $toStage, $actorId);
        $this->historyRepo()->create($id, $fromStage, $toStage, $actorId, 'move', $note);
        $this->auditRepo()->create('lead', $id, 'move_stage', $actorId, [
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'note' => $note,
        ]);

        return $this->requireLead($id);
    }

    public function addInteraction(int $id, string $kind, string $note, int $actorId): void
    {
        $lead = $this->requireLead($id);
        if (($lead['converted_at'] ?? null) !== null) {
            throw new \RuntimeException('Este lead já foi convertido em cliente.');
        }

        $kind = trim($kind);
        if (!in_array($kind, ['nota', 'email', 'call', 'meeting'], true)) {
            throw new \RuntimeException('Tipo de interação inválido.');
        }
        $note = trim($note);
        if ($note === '') {
            throw new \RuntimeException('A interação precisa ter um texto.');
        }

        $this->interactionRepo()->create($id, $kind, $note, $actorId > 0 ? $actorId : null);
        $this->auditRepo()->create('lead', $id, 'interaction', $actorId, [
            'kind' => $kind,
            'note' => $note,
        ]);
    }

    public function convert(int $id, array $payload, int $actorId): array
    {
        $lead = $this->requireLead($id);
        if (($lead['converted_at'] ?? null) !== null) {
            throw new \RuntimeException('Este lead já foi convertido em cliente.');
        }

        $contractNotes = trim((string) ($payload['contract_notes'] ?? ''));
        $billingEmail = trim((string) ($payload['billing_email'] ?? ''));
        $billingPhone = preg_replace('/\D+/', '', (string) ($payload['billing_phone'] ?? '')) ?? '';
        $billingNotes = trim((string) ($payload['billing_notes'] ?? ''));

        if ($billingEmail !== '' && !filter_var($billingEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Informe um e-mail de faturamento válido.');
        }

        if ($billingPhone !== '' && (strlen($billingPhone) < 10 || strlen($billingPhone) > 11)) {
            throw new \RuntimeException('Informe um telefone de faturamento válido.');
        }

        if ((string) ($lead['stage'] ?? '') !== LeadStages::PRONTO_APROVACAO) {
            throw new \RuntimeException('O lead precisa estar em "Pronto para Aprovação" antes da conversão.');
        }

        $pdo = is_object($this->transaction) ? $this->transaction : DB::pdo();
        $pdo->beginTransaction();

        try {
            $clientPayload = [
                'name' => (string) ($lead['name'] ?? ''),
                'email' => (string) ($lead['email'] ?? ''),
                'phone' => (string) ($lead['phone'] ?? ''),
                'company' => (string) (($lead['company'] ?? '') !== '' ? $lead['company'] : $lead['name']),
                'contact_person' => (string) (($lead['contact_person'] ?? '') !== '' ? $lead['contact_person'] : $lead['name']),
                'status' => 'ativo',
                'project_reference' => 'Convertido do lead #' . $id,
                'has_hosting_contract' => 0,
                'hosting_contract_amount' => null,
                'hosting_due_date' => null,
                'hosting_renewal_days' => null,
                'manages_domain' => 0,
                'domain_due_date' => null,
                'domain_amount' => null,
                'person_type' => (string) ($lead['person_type'] ?? 'pj'),
                'document_number' => (string) ($lead['document_number'] ?? ''),
                'secondary_phone' => $lead['secondary_phone'] ?? null,
                'postal_code' => $lead['postal_code'] ?? null,
                'street' => $lead['street'] ?? null,
                'street_number' => $lead['street_number'] ?? null,
                'address_complement' => $lead['address_complement'] ?? null,
                'neighborhood' => $lead['neighborhood'] ?? null,
                'city' => $lead['city'] ?? null,
                'state' => $lead['state'] ?? null,
                'birth_or_opening_date' => $lead['birth_or_opening_date'] ?? null,
                'market_segment' => $lead['market_segment'] ?? null,
                'acquisition_source' => $lead['acquisition_source'] ?? null,
                'billing_email' => $billingEmail !== '' ? strtolower($billingEmail) : null,
                'billing_phone' => $billingPhone !== '' ? $billingPhone : null,
                'billing_notes' => $billingNotes !== '' ? $billingNotes : null,
                'contract_notes' => $contractNotes !== '' ? $contractNotes : null,
                'source_lead_id' => $id,
            ];

            $existingClient = $this->clientRepo()->findBySourceLeadId($id);
            if (is_array($existingClient) && (int) ($existingClient['id'] ?? 0) > 0) {
                $clientId = (int) $existingClient['id'];
                $this->clientRepo()->promoteLeadProspectToActive($clientId, $clientPayload);
            } else {
                $clientId = $this->clientRepo()->createFromLead($clientPayload);
            }

            $interactions = $this->interactionRepo()->listByLead($id);
            foreach (array_reverse($interactions) as $interaction) {
                $kind = (string) ($interaction['kind'] ?? 'nota');
                $note = trim((string) ($interaction['note'] ?? ''));
                $createdAt = (string) ($interaction['created_at'] ?? date('Y-m-d H:i:s'));
                $prefix = '[Lead #' . $id . '] ';
                $this->clientInteractionRepo()->createHistorical($clientId, $kind, $prefix . $note, $createdAt);
            }

            $this->leadRepo()->markConverted($id, $clientId, $actorId);
            $this->historyRepo()->create($id, LeadStages::PRONTO_APROVACAO, LeadStages::APROVADO, $actorId, 'convert', 'Lead convertido em cliente ativo #' . $clientId . '.');
            $this->auditRepo()->create('lead', $id, 'convert', $actorId, [
                'client_id' => $clientId,
                'billing_email' => $billingEmail,
                'billing_phone' => $billingPhone,
            ]);
            $this->auditRepo()->create('client', $clientId, 'created_from_lead', $actorId, [
                'lead_id' => $id,
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'client_id' => $clientId,
            'lead_id' => $id,
        ];
    }

    public function board(string $query = ''): array
    {
        $rows = $this->leadRepo()->listKanban($query);
        $grouped = [];
        foreach (LeadStages::kanban() as $stage => $label) {
            $grouped[$stage] = [
                'stage' => $stage,
                'label' => $label,
                'items' => [],
            ];
        }

        foreach ($rows as $row) {
            $stage = (string) ($row['stage'] ?? LeadStages::CADASTRO_REALIZADO);
            if (!isset($grouped[$stage])) {
                continue;
            }
            $grouped[$stage]['items'][] = $row;
        }

        return array_values($grouped);
    }

    private function validated(array $payload, ?int $leadId): array
    {
        $validator = new LeadValidator();
        $preview = $validator->validate($payload);
        $duplicates = $this->leadRepo()->duplicateCounts((array) ($preview['data'] ?? []), $leadId);
        $validation = $validator->validate($payload, $duplicates);
        if (!($validation['ok'] ?? false)) {
            $errors = (array) ($validation['errors'] ?? []);
            throw new \RuntimeException((string) reset($errors));
        }

        return (array) ($validation['data'] ?? []);
    }

    private function requireLead(int $id): array
    {
        $lead = $this->leadRepo()->find($id);
        if ($lead === null) {
            throw new \RuntimeException('Lead não encontrado.');
        }

        return $lead;
    }

    private function leadRepo(): LeadRepositoryContract
    {
        return $this->leads ?? new LeadRepository();
    }

    private function historyRepo(): LeadStageHistoryRepositoryContract
    {
        return $this->history ?? new LeadStageHistoryRepository();
    }

    private function interactionRepo(): LeadInteractionRepositoryContract
    {
        return $this->interactions ?? new LeadInteractionRepository();
    }

    private function clientRepo(): ClientRepositoryContract
    {
        return $this->clients ?? new ClientRepository();
    }

    private function clientInteractionRepo(): ClientInteractionRepositoryContract
    {
        return $this->clientInteractions ?? new ClientInteractionRepository();
    }

    private function auditRepo(): AuditLogRepositoryContract
    {
        return $this->audit ?? new AuditLogRepository();
    }
}
