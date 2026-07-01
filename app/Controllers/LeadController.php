<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\LeadInteractionRepository;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStageHistoryRepository;
use App\Services\LeadPipelineNavigation;
use App\Services\LeadService;
use App\Services\LeadStages;
use App\Services\LeadValidator;

final class LeadController
{
    public function index(Request $request): void
    {
        $q = trim((string) $request->input('q', ''));
        View::render('leads/kanban', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'board' => (new LeadService())->board($q),
            'q' => $q,
            'stages' => LeadStages::kanban(),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('leads/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'lead' => $this->defaults(),
            'errors' => [],
            'stages' => LeadStages::kanban(),
            'histories' => [],
            'interactions' => [],
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): void
    {
        try {
            $lead = (new LeadService())->create($request->allPost(), (int) Session::get('user_id', 0));
            Response::redirect($request->basePath() . '/leads/' . (int) ($lead['id'] ?? 0) . '/editar?toast=success&msg=' . rawurlencode('Lead cadastrado com sucesso.'));
        } catch (\Throwable $e) {
            $validation = $this->validationResult($request->allPost(), null);
            View::render('leads/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'lead' => $this->prepareFormLead($request->allPost()),
                'errors' => (array) ($validation['errors'] ?? []),
                'error' => $e->getMessage(),
                'stages' => LeadStages::kanban(),
                'histories' => [],
                'interactions' => [],
                'isEdit' => false,
            ]);
        }
    }

    public function edit(Request $request, array $params): void
    {
        $lead = $this->loadLead((int) ($params['id'] ?? 0));
        if ($lead === null) {
            http_response_code(404);
            echo 'Lead não encontrado.';
            return;
        }

        $toastType = strtolower((string) $request->input('toast', ''));
        $toastMessage = trim((string) $request->input('msg', ''));
        if (!in_array($toastType, ['success', 'error', 'warning', 'info'], true)) {
            $toastType = '';
        }

        View::render('leads/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'lead' => $this->prepareFormLead($lead),
            'errors' => [],
            'stages' => LeadStages::kanban(),
            'histories' => (new LeadStageHistoryRepository())->listByLead((int) $lead['id']),
            'interactions' => (new LeadInteractionRepository())->listByLead((int) $lead['id']),
            'isEdit' => true,
            'toastType' => $toastType,
            'toastMessage' => $toastMessage,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->loadLead($id);
        if ($existing === null) {
            http_response_code(404);
            echo 'Lead não encontrado.';
            return;
        }
        try {
            $lead = (new LeadService())->update($id, $request->allPost(), (int) Session::get('user_id', 0));
            $redirectUrl = (new LeadPipelineNavigation())->proposalRedirectUrl(
                $id,
                (string) ($existing['stage'] ?? ''),
                (string) ($lead['stage'] ?? ''),
                $request->basePath()
            );
            if (is_string($redirectUrl) && $redirectUrl !== '') {
                Response::redirect($redirectUrl);
            }
            Response::redirect($request->basePath() . '/leads/' . $id . '/editar?toast=success&msg=' . rawurlencode('Lead atualizado com sucesso.'));
        } catch (\Throwable $e) {
            $lead = $this->prepareFormLead(array_merge($request->allPost(), ['id' => $id]));
            $validation = $this->validationResult($request->allPost(), $id);
            View::render('leads/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'lead' => $lead,
                'errors' => (array) ($validation['errors'] ?? []),
                'error' => $e->getMessage(),
                'stages' => LeadStages::kanban(),
                'histories' => (new LeadStageHistoryRepository())->listByLead($id),
                'interactions' => (new LeadInteractionRepository())->listByLead($id),
                'isEdit' => true,
            ]);
        }
    }

    public function addInteraction(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            (new LeadService())->addInteraction(
                $id,
                trim((string) $request->input('kind', 'nota')),
                trim((string) $request->input('note', '')),
                (int) Session::get('user_id', 0)
            );
            Response::redirect($request->basePath() . '/leads/' . $id . '/editar?toast=success&msg=' . rawurlencode('Interação registrada com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/leads/' . $id . '/editar?toast=error&msg=' . rawurlencode($e->getMessage()));
        }
    }

    private function loadLead(int $id): ?array
    {
        return $id > 0 ? (new LeadRepository())->find($id) : null;
    }

    private function validationResult(array $payload, ?int $leadId): array
    {
        $validator = new LeadValidator();
        $preview = $validator->validate($payload);
        $duplicates = (new LeadRepository())->duplicateCounts((array) ($preview['data'] ?? []), $leadId);
        return $validator->validate($payload, $duplicates);
    }

    private function defaults(): array
    {
        return [
            'name' => '',
            'company' => '',
            'contact_person' => '',
            'person_type' => 'pj',
            'document_number' => '',
            'email' => '',
            'phone' => '',
            'secondary_phone' => '',
            'postal_code' => '',
            'street' => '',
            'street_number' => '',
            'address_complement' => '',
            'neighborhood' => '',
            'city' => '',
            'state' => '',
            'birth_or_opening_date' => '',
            'market_segment' => '',
            'acquisition_source' => '',
            'stage' => LeadStages::CADASTRO_REALIZADO,
            'notes' => '',
            'converted_at' => null,
        ];
    }

    private function prepareFormLead(array $lead): array
    {
        $lead = array_merge($this->defaults(), $lead);
        $lead['birth_or_opening_date'] = $this->formatDateForForm($lead['birth_or_opening_date'] ?? '');
        return $lead;
    }

    private function formatDateForForm(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $raw) {
                return $date->format('Y-m-d');
            }
        }

        return $raw;
    }
}
