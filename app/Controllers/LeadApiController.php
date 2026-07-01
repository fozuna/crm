<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\LeadInteractionRepository;
use App\Repositories\LeadRepository;
use App\Repositories\LeadStageHistoryRepository;
use App\Services\LeadPipelineNavigation;
use App\Services\LeadService;

final class LeadApiController
{
    public function kanban(Request $request): void
    {
        $q = trim((string) $request->input('q', ''));
        Response::json([
            'ok' => true,
            'data' => [
                'board' => (new LeadService())->board($q),
            ],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $lead = (new LeadRepository())->find($id);
        if ($lead === null) {
            Response::json(['ok' => false, 'error' => 'Lead não encontrado.'], 404);
        }

        Response::json([
            'ok' => true,
            'data' => [
                'lead' => $lead,
                'history' => (new LeadStageHistoryRepository())->listByLead($id),
                'interactions' => (new LeadInteractionRepository())->listByLead($id),
            ],
        ]);
    }

    public function move(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $payload = $this->body($request);
        $repo = new LeadRepository();
        $existing = $repo->find($id);
        if ($existing === null) {
            Response::json(['ok' => false, 'error' => 'Lead não encontrado.'], 404);
        }
        try {
            $lead = (new LeadService())->move(
                $id,
                trim((string) ($payload['stage'] ?? '')),
                (int) Session::get('user_id', 0),
                trim((string) ($payload['note'] ?? ''))
            );
            $redirectUrl = (new LeadPipelineNavigation())->proposalRedirectUrl(
                $id,
                (string) ($existing['stage'] ?? ''),
                (string) ($lead['stage'] ?? ''),
                $request->basePath()
            );
            Response::json([
                'ok' => true,
                'data' => [
                    'lead' => $lead,
                    'redirect_url' => $redirectUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function convert(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $payload = $this->body($request);
        try {
            $result = (new LeadService())->convert($id, $payload, (int) Session::get('user_id', 0));
            Response::json(['ok' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function body(Request $request): array
    {
        $json = $request->jsonBody();
        if ($json !== []) {
            return $json;
        }
        parse_str($request->rawBody(), $parsed);
        return is_array($parsed) && $parsed !== [] ? $parsed : $_POST;
    }
}
