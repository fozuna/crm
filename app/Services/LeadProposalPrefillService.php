<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\ClientRepositoryContract;
use App\Contracts\LeadInteractionRepositoryContract;
use App\Contracts\LeadRepositoryContract;
use App\Contracts\LeadStageHistoryRepositoryContract;
use App\Repositories\ClientRepository;
use App\Repositories\LeadInteractionRepository;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStageHistoryRepository;

final class LeadProposalPrefillService
{
    public function __construct(
        private readonly ?LeadRepositoryContract $leads = null,
        private readonly ?LeadInteractionRepositoryContract $interactions = null,
        private readonly ?LeadStageHistoryRepositoryContract $history = null,
        private readonly ?ClientRepositoryContract $clients = null,
    ) {
    }

    public function build(int $leadId, string $basePath): array
    {
        $lead = $this->leadRepo()->find($leadId);
        if ($lead === null) {
            throw new \RuntimeException('Lead não encontrado para gerar a proposta.');
        }

        $client = $this->clientRepo()->findBySourceLeadId($leadId);
        if ($client === null) {
            $clientId = $this->clientRepo()->createProposalProspectFromLead($lead);
            $client = $this->clientRepo()->find($clientId);
        }

        if ($client === null) {
            throw new \RuntimeException('Não foi possível preparar o cadastro base do lead para a proposta.');
        }

        $history = $this->historyRepo()->listByLead($leadId);
        $interactions = $this->interactionRepo()->listByLead($leadId);

        return [
            'lead' => $lead,
            'client' => $client,
            'proposal' => [
                'client_id' => (int) ($client['id'] ?? 0),
                'source_lead_id' => $leadId,
                'title' => $this->proposalTitle($lead),
                'description' => $this->proposalDescription($lead, $interactions),
                'notes' => $this->proposalNotes($lead, $history, $interactions),
            ],
            'items' => [$this->seedItem($lead, $interactions)],
            'milestones' => [['title' => '', 'due_date' => '', 'notes' => '', 'penalty_terms' => '']],
            'summary' => $this->summary($lead, $history, $interactions, (int) ($client['id'] ?? 0)),
            'retry_url' => rtrim($basePath, '/') . '/propostas/nova?lead_id=' . $leadId,
            'back_url' => rtrim($basePath, '/') . '/leads/' . $leadId . '/editar',
        ];
    }

    private function proposalTitle(array $lead): string
    {
        $target = trim((string) (($lead['company'] ?? '') !== '' ? $lead['company'] : ($lead['name'] ?? 'Lead')));
        return $target !== '' ? 'Proposta Comercial - ' . $target : 'Proposta Comercial';
    }

    private function proposalDescription(array $lead, array $interactions): string
    {
        $sections = [];

        $leadNotes = trim((string) ($lead['notes'] ?? ''));
        if ($leadNotes !== '') {
            $sections[] = "Contexto e necessidades identificadas\n" . $leadNotes;
        }

        $timeline = [];
        foreach (array_slice($interactions, 0, 5) as $interaction) {
            $when = isset($interaction['created_at']) ? date('d/m/Y H:i', strtotime((string) $interaction['created_at'])) : '';
            $kind = strtoupper((string) ($interaction['kind'] ?? 'nota'));
            $note = trim((string) ($interaction['note'] ?? ''));
            if ($note === '') {
                continue;
            }
            $timeline[] = '- ' . trim($when . ' ' . $kind) . ': ' . $note;
        }
        if ($timeline !== []) {
            $sections[] = "Histórico do atendimento\n" . implode("\n", $timeline);
        }

        $commercial = [];
        $commercial[] = 'Segmento: ' . (string) (($lead['market_segment'] ?? '') !== '' ? $lead['market_segment'] : 'Não informado');
        $commercial[] = 'Fonte de aquisição: ' . (string) (($lead['acquisition_source'] ?? '') !== '' ? $lead['acquisition_source'] : 'Não informada');
        $commercial[] = 'Contato principal: ' . (string) (($lead['contact_person'] ?? '') !== '' ? $lead['contact_person'] : ($lead['name'] ?? 'Não informado'));
        $sections[] = "Dados comerciais\n" . implode("\n", $commercial);

        return implode("\n\n", array_filter($sections));
    }

    private function proposalNotes(array $lead, array $history, array $interactions): string
    {
        $lines = [];
        $lines[] = 'Lead origem: #' . (int) ($lead['id'] ?? 0);
        $lines[] = 'Documento: ' . (string) (($lead['document_number'] ?? '') !== '' ? $lead['document_number'] : 'Não informado');
        $lines[] = 'Contato: ' . (string) (($lead['email'] ?? '') !== '' ? $lead['email'] : 'Sem e-mail') . ' | ' . (string) (($lead['phone'] ?? '') !== '' ? $lead['phone'] : 'Sem telefone');
        $lines[] = 'Endereço: ' . $this->address($lead);

        if ($history !== []) {
            $latest = $history[0];
            $lines[] = 'Última movimentação do funil: ' . (string) ($latest['to_stage_label'] ?? '');
        }

        if ($interactions !== []) {
            $latestInteraction = $interactions[0];
            $lines[] = 'Última interação: ' . strtoupper((string) ($latestInteraction['kind'] ?? 'nota')) . ' em ' . date('d/m/Y H:i', strtotime((string) ($latestInteraction['created_at'] ?? 'now')));
        }

        return implode("\n", $lines);
    }

    private function seedItem(array $lead, array $interactions): array
    {
        $base = trim((string) ($lead['notes'] ?? ''));
        if ($base === '' && $interactions !== []) {
            $base = trim((string) ($interactions[0]['note'] ?? ''));
        }
        if ($base === '') {
            $base = 'Serviços e entregáveis a detalhar conforme diagnóstico comercial do lead.';
        }

        return [
            'service_id' => null,
            'description' => $base,
            'qty' => 1,
            'unit_price' => 0,
            'total' => 0,
        ];
    }

    private function summary(array $lead, array $history, array $interactions, int $clientId): array
    {
        $recentHistory = [];
        foreach (array_slice($history, 0, 4) as $row) {
            $recentHistory[] = [
                'title' => (string) ($row['to_stage_label'] ?? ''),
                'meta' => isset($row['created_at']) ? date('d/m/Y H:i', strtotime((string) $row['created_at'])) : '',
                'detail' => (string) ($row['note'] ?? ''),
            ];
        }

        $recentInteractions = [];
        foreach (array_slice($interactions, 0, 4) as $row) {
            $recentInteractions[] = [
                'title' => strtoupper((string) ($row['kind'] ?? 'nota')),
                'meta' => isset($row['created_at']) ? date('d/m/Y H:i', strtotime((string) $row['created_at'])) : '',
                'detail' => (string) ($row['note'] ?? ''),
            ];
        }

        return [
            'client_id' => $clientId,
            'lead_name' => (string) ($lead['name'] ?? ''),
            'company' => (string) (($lead['company'] ?? '') !== '' ? $lead['company'] : ($lead['name'] ?? '')),
            'contact' => (string) (($lead['contact_person'] ?? '') !== '' ? $lead['contact_person'] : ($lead['name'] ?? '')),
            'document' => (string) ($lead['document_number'] ?? ''),
            'email' => (string) ($lead['email'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'secondary_phone' => (string) ($lead['secondary_phone'] ?? ''),
            'segment' => (string) ($lead['market_segment'] ?? ''),
            'source' => (string) ($lead['acquisition_source'] ?? ''),
            'address' => $this->address($lead),
            'notes' => (string) ($lead['notes'] ?? ''),
            'history' => $recentHistory,
            'interactions' => $recentInteractions,
        ];
    }

    private function address(array $lead): string
    {
        $parts = [
            $lead['street'] ?? null,
            $lead['street_number'] ?? null,
            $lead['address_complement'] ?? null,
            $lead['neighborhood'] ?? null,
            $lead['city'] ?? null,
            $lead['state'] ?? null,
            $lead['postal_code'] ?? null,
        ];

        $parts = array_values(array_filter(array_map(static fn($v): string => trim((string) $v), $parts), static fn(string $v): bool => $v !== ''));
        return $parts !== [] ? implode(', ', $parts) : 'Não informado';
    }

    private function leadRepo(): LeadRepositoryContract
    {
        return $this->leads ?? new LeadRepository();
    }

    private function interactionRepo(): LeadInteractionRepositoryContract
    {
        return $this->interactions ?? new LeadInteractionRepository();
    }

    private function historyRepo(): LeadStageHistoryRepositoryContract
    {
        return $this->history ?? new LeadStageHistoryRepository();
    }

    private function clientRepo(): ClientRepositoryContract
    {
        return $this->clients ?? new ClientRepository();
    }
}
