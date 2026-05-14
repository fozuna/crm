<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FinancialEnterpriseDashboardRepository;
use App\Repositories\FinancialEnterpriseReportRepository;
use App\Repositories\FinancialReceiptRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\ProjectRepository;
use App\Services\CompanyContext;
use App\Services\FinancialReceivableService;
use App\Services\Money;

final class FinancialModuleApiController
{
    public function index(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        (new FinancialReceivableService())->refreshStatuses($companyId);
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, (int) $request->input('per_page', 20));
        $data = (new FinancialReceivableRepository())->paginate($companyId, $this->filters($request), $page, $perPage);
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function show(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $repo = new FinancialReceivableRepository();
        $item = $repo->find($companyId, $id);
        if ($item === null) {
            Response::json(['ok' => false, 'error' => 'Conta a receber não encontrada.'], 404);
            return;
        }
        Response::json([
            'ok' => true,
            'data' => [
                'receivable' => $item,
                'receipts' => (new FinancialReceiptRepository())->listByReceivable($id),
            ],
        ]);
    }

    public function store(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $actorId = (int) Session::get('user_id', 0);
        try {
            $created = (new FinancialReceivableService())->create(array_merge($this->normalizePayload($this->body($request)), ['company_id' => $companyId]), $actorId, $this->ip());
            Response::json(['ok' => true, 'data' => $created], 201);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            $row = (new FinancialReceivableService())->update($companyId, $id, $this->normalizePayload($this->body($request)), $actorId, $this->ip());
            Response::json(['ok' => true, 'data' => $row]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinancialReceivableService())->delete($companyId, $id, $actorId, $this->ip());
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function receipt(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            $payload = $this->normalizePayload($this->body($request));
            $snapshot = (new FinancialReceivableService())->registerReceipt($companyId, $id, $payload, $actorId, $this->ip());
            Response::json(['ok' => true, 'data' => $snapshot]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function reverse(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        $payload = $this->body($request);
        try {
            $snapshot = (new FinancialReceivableService())->reverseReceipt($companyId, $id, (int) ($payload['receipt_id'] ?? 0), trim((string) ($payload['reason'] ?? '')), $actorId, $this->ip());
            Response::json(['ok' => true, 'data' => $snapshot]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function dashboard(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        (new FinancialReceivableService())->refreshStatuses($companyId);
        $data = (new FinancialEnterpriseDashboardRepository())->data($companyId, $this->filters($request));
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function reports(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $data = (new FinancialEnterpriseReportRepository())->reports($companyId, $this->filters($request));
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function approvedProjectsByClient(Request $request, array $params): void
    {
        $clientId = (int) ($params['clientId'] ?? 0);
        if ($clientId <= 0) {
            Response::json([
                'ok' => true,
                'data' => [
                    'projects' => [],
                    'message' => 'Selecione um cliente para carregar os projetos aprovados.',
                ],
            ]);
        }

        $projects = (new ProjectRepository())->approvedByClient($clientId);
        Response::json([
            'ok' => true,
            'data' => [
                'projects' => $projects,
                'message' => count($projects) === 0
                    ? 'Este cliente não possui projetos vinculados a propostas aprovadas.'
                    : '',
            ],
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'q' => trim((string) $request->input('q', '')),
            'status' => trim((string) $request->input('status', '')),
            'client_id' => (int) $request->input('client_id', 0),
            'project_id' => (int) $request->input('project_id', 0),
            'category_id' => (int) $request->input('category_id', 0),
            'cost_center_id' => (int) $request->input('cost_center_id', 0),
            'due_from' => trim((string) $request->input('due_from', '')),
            'due_to' => trim((string) $request->input('due_to', '')),
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
            'sort' => trim((string) $request->input('sort', 'due_date')),
            'direction' => trim((string) $request->input('direction', 'asc')),
        ];
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

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function normalizePayload(array $payload): array
    {
        foreach (['original_amount', 'discount_amount', 'interest_amount', 'fine_amount', 'received_amount', 'remaining_amount', 'amount_received'] as $field) {
            if (array_key_exists($field, $payload)) {
                $payload[$field] = $this->normalizeMoney($payload[$field]);
            }
        }

        return $payload;
    }

    private function normalizeMoney(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $raw) === 1) {
            return round((float) $raw, 2);
        }

        return Money::parseBRL($raw);
    }
}
