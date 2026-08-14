<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ClientRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\ProposalBrandingRepository;
use App\Repositories\ServiceOrderAttachmentRepository;
use App\Repositories\ServiceOrderHistoryRepository;
use App\Repositories\ServiceOrderRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\UserListRepository;
use App\Services\CompanyContext;
use App\Services\Money;
use App\Services\ServiceOrderAttachmentUploadService;
use App\Services\ServiceOrderApprovalService;
use App\Services\ServiceOrderBillingService;
use App\Services\ServiceOrderPdfGenerator;
use App\Services\ServiceOrderService;
use App\Services\ServiceOrderStatus;
use App\Services\ServiceOrderType;

final class ServiceOrderController
{
    public function index(Request $request): void
    {
        $filters = $this->filters($request);
        $page = max(1, (int) $request->input('page', 1));

        View::render('service_orders/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'listing' => (new ServiceOrderRepository())->paginate($filters, $page, 20),
            'summary' => (new ServiceOrderRepository())->summary($filters),
            'clients' => (new ClientRepository())->options(),
            'users' => (new UserListRepository())->all(),
            'statusOptions' => ServiceOrderStatus::all(),
            'typeOptions' => ServiceOrderType::all(),
            'canManage' => $this->canManage(),
            'ok' => trim((string) $request->input('ok', '')),
            'error' => trim((string) $request->input('error', '')),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('service_orders/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'serviceOrder' => $this->defaults(),
            'clients' => (new ClientRepository())->all(),
            'users' => (new UserListRepository())->all(),
            'services' => (new ServiceRepository())->activeList(false),
            'attachments' => [],
            'history' => [],
            'approvalSummary' => null,
            'statusOptions' => ServiceOrderStatus::all(),
            'typeOptions' => ServiceOrderType::all(),
            'isEdit' => false,
            'error' => '',
        ]);
    }

    public function store(Request $request): void
    {
        $actorId = (int) Session::get('user_id', 0);
        try {
            $order = (new ServiceOrderService())->create($request->allPost(), $actorId);
            $id = (int) ($order['id'] ?? 0);
            if ($id > 0 && isset($_FILES['attachments']) && is_array($_FILES['attachments'])) {
                (new ServiceOrderService())->addAttachments($id, $_FILES['attachments'], $actorId);
            }
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode('Ordem de serviço cadastrada com sucesso.'));
        } catch (\Throwable $e) {
            View::render('service_orders/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'serviceOrder' => $this->prepareFormData($request->allPost()),
                'clients' => (new ClientRepository())->all(),
                'users' => (new UserListRepository())->all(),
                'services' => (new ServiceRepository())->activeList(false),
                'attachments' => [],
                'history' => [],
                'approvalSummary' => null,
                'statusOptions' => ServiceOrderStatus::all(),
                'typeOptions' => ServiceOrderType::all(),
                'isEdit' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $order = (new ServiceOrderRepository())->find($id);
        if ($order === null) {
            http_response_code(404);
            echo 'Ordem de serviço não encontrada.';
            return;
        }

        $toastType = strtolower((string) $request->input('toast', ''));
        $toastMessage = trim((string) $request->input('msg', ''));
        if (!in_array($toastType, ['success', 'error', 'warning', 'info'], true)) {
            $toastType = '';
        }

        View::render('service_orders/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'serviceOrder' => $this->prepareFormData($order),
            'clients' => (new ClientRepository())->all(),
            'users' => (new UserListRepository())->all(),
            'services' => (new ServiceRepository())->activeList(false),
            'attachments' => (new ServiceOrderAttachmentRepository())->listByServiceOrder($id),
            'history' => (new ServiceOrderHistoryRepository())->listByServiceOrder($id),
            'approvalSummary' => (new ServiceOrderApprovalService())->approvalSummaryForOrder($id),
            'receivables' => $this->receivablesForOrder($order),
            'statusOptions' => ServiceOrderStatus::all(),
            'typeOptions' => ServiceOrderType::all(),
            'isEdit' => true,
            'toastType' => $toastType,
            'toastMessage' => $toastMessage,
            'error' => '',
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $order = (new ServiceOrderRepository())->find($id);
        if ($order === null) {
            http_response_code(404);
            echo 'Ordem de serviço não encontrada.';
            return;
        }

        View::render('service_orders/show', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'serviceOrder' => $order,
            'attachments' => (new ServiceOrderAttachmentRepository())->listByServiceOrder($id),
            'history' => (new ServiceOrderHistoryRepository())->listByServiceOrder($id),
            'receivables' => $this->receivablesForOrder($order),
            'canManage' => $this->canManage(),
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new ServiceOrderService())->update($id, $request->allPost(), $actorId);
            if (isset($_FILES['attachments']) && is_array($_FILES['attachments'])) {
                (new ServiceOrderService())->addAttachments($id, $_FILES['attachments'], $actorId);
            }
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode('Ordem de serviço atualizada com sucesso.'));
        } catch (\Throwable $e) {
            View::render('service_orders/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'serviceOrder' => $this->prepareFormData(array_merge($request->allPost(), ['id' => $id])),
                'clients' => (new ClientRepository())->all(),
                'users' => (new UserListRepository())->all(),
                'services' => (new ServiceRepository())->activeList(false),
                'attachments' => (new ServiceOrderAttachmentRepository())->listByServiceOrder($id),
                'history' => (new ServiceOrderHistoryRepository())->listByServiceOrder($id),
                'approvalSummary' => (new ServiceOrderApprovalService())->approvalSummaryForOrder($id),
                'statusOptions' => ServiceOrderStatus::all(),
                'typeOptions' => ServiceOrderType::all(),
                'isEdit' => true,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function billing(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $order = (new ServiceOrderRepository())->find($id);
        if ($order === null) {
            http_response_code(404);
            echo 'Ordem de serviço não encontrada.';
            return;
        }

        if ((int) ($order['billable'] ?? 0) !== 1) {
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=error&msg=' . rawurlencode('Esta Ordem de Serviço não possui cobrança.'));
            return;
        }
        if ((string) ($order['status'] ?? '') !== ServiceOrderStatus::CONCLUIDO && $this->receivablesForOrder($order) === []) {
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=error&msg=' . rawurlencode('Conclua a Ordem de Serviço antes de definir a cobrança.'));
            return;
        }

        View::render('service_orders/billing', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'order' => $order,
            'receivables' => $this->receivablesForOrder($order),
            'error' => trim((string) $request->input('error', '')),
        ]);
    }

    public function invoice(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        $post = $request->allPost();

        try {
            (new ServiceOrderBillingService())->invoice($id, $this->billingPayload($post), $actorId);
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode('Cobrança definida e Ordem de Serviço faturada com sucesso.'));
        } catch (\Throwable $e) {
            $order = (new ServiceOrderRepository())->find($id);
            View::render('service_orders/billing', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'order' => $order ?? [],
                'receivables' => $order !== null ? $this->receivablesForOrder($order) : [],
                'formData' => $post,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updateStatus(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new ServiceOrderService())->updateStatus($id, $status, $actorId);
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode('Status atualizado com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=error&msg=' . rawurlencode($e->getMessage()));
        }
    }

    public function cancel(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new ServiceOrderService())->cancel($id, $actorId, trim((string) $request->input('reason', '')));
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode('Ordem de serviço cancelada.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=error&msg=' . rawurlencode($e->getMessage()));
        }
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new ServiceOrderService())->delete($id, $actorId);
            Response::redirect($request->basePath() . '/ordens-servico?ok=' . rawurlencode('Ordem de serviço excluída com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/ordens-servico?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function deleteAttachment(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $attachmentId = (int) ($params['attachmentId'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new ServiceOrderService())->removeAttachment($id, $attachmentId, $actorId);
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode('Anexo removido com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=error&msg=' . rawurlencode($e->getMessage()));
        }
    }

    public function attachment(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $attachmentId = (int) ($params['attachmentId'] ?? 0);
        $attachment = (new ServiceOrderAttachmentRepository())->find($attachmentId);
        if ($attachment === null || (int) ($attachment['servico_avulso_id'] ?? 0) !== $id) {
            http_response_code(404);
            return;
        }

        $path = (string) ($attachment['file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        $mime = (string) ($attachment['mime_type'] ?? 'application/octet-stream');
        $download = (int) $request->input('download', 0) === 1;
        $isInline = !$download && (str_starts_with($mime, 'image/') || $mime === 'application/pdf');

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
        header('Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . rawurlencode((string) ($attachment['original_name'] ?? 'anexo')) . '"');
        readfile($path);
        exit;
    }

    public function pdf(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $order = (new ServiceOrderRepository())->find($id);
        if ($order === null) {
            http_response_code(404);
            echo 'Ordem de serviço não encontrada.';
            return;
        }

        $branding = [];
        try {
            $branding = (new ProposalBrandingRepository())->get();
        } catch (\Throwable) {
            $branding = [];
        }

        $bytes = (new ServiceOrderPdfGenerator())->build(
            $branding,
            $order,
            (new ServiceOrderAttachmentRepository())->listByServiceOrder($id),
            (new ServiceOrderHistoryRepository())->listByServiceOrder($id)
        );

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ordem-servico-' . (string) ($order['numero_os'] ?? $id) . '.pdf"');
        echo $bytes;
        exit;
    }

    public function reports(Request $request): void
    {
        $filters = $this->filters($request);
        $report = (new ServiceOrderService())->report($filters);

        View::render('service_orders/reports', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'report' => $report,
            'clients' => (new ClientRepository())->options(),
            'users' => (new UserListRepository())->all(),
            'statusOptions' => ServiceOrderStatus::all(),
            'typeOptions' => ServiceOrderType::all(),
            'canManage' => $this->canManage(),
        ]);
    }

    private function receivablesForOrder(array $order): array
    {
        $id = (int) ($order['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }
        $companyId = CompanyContext::currentCompanyId();
        $rows = (new FinancialReceivableRepository())->listByServiceOrder($companyId, $id);
        if ($rows !== []) {
            return $rows;
        }

        $legacyId = (int) ($order['financial_receivable_id'] ?? 0);
        if ($legacyId > 0) {
            $legacy = (new FinancialReceivableRepository())->find($companyId, $legacyId);
            return $legacy !== null ? [$legacy] : [];
        }

        return [];
    }

    private function billingPayload(array $post): array
    {
        $mode = trim((string) ($post['mode'] ?? ''));
        $payload = [
            'mode' => $mode,
            'due_date' => trim((string) ($post['due_date'] ?? '')),
            'description' => trim((string) ($post['description'] ?? '')),
            'notes' => trim((string) ($post['notes'] ?? '')),
            'installments_count' => (int) ($post['installments_count'] ?? 0),
            'first_due_date' => trim((string) ($post['first_due_date'] ?? '')),
            'periodicity' => trim((string) ($post['periodicity'] ?? 'mensal')),
            'custom_interval_days' => (int) ($post['custom_interval_days'] ?? 0),
        ];

        if ($mode === ServiceOrderBillingService::MODE_PERSONALIZADO) {
            $amounts = (array) ($post['installment_amount'] ?? []);
            $dueDates = (array) ($post['installment_due_date'] ?? []);
            $descriptions = (array) ($post['installment_description'] ?? []);
            $rows = [];
            foreach ($amounts as $index => $amount) {
                $rows[] = [
                    'amount' => Money::parseBRL((string) $amount),
                    'due_date' => trim((string) ($dueDates[$index] ?? '')),
                    'description' => trim((string) ($descriptions[$index] ?? '')),
                ];
            }
            $payload['installments'] = $rows;
        }

        return $payload;
    }

    private function canManage(): bool
    {
        if ((int) Session::get('is_admin', 0) === 1) {
            return true;
        }

        return trim((string) Session::get('role', '')) === 'pm';
    }

    private function filters(Request $request): array
    {
        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && !ServiceOrderStatus::isValid($status)) {
            $status = '';
        }

        $type = trim((string) $request->input('type', ''));
        if ($type !== '' && !ServiceOrderType::isValid($type)) {
            $type = '';
        }

        $billable = trim((string) $request->input('billable', ''));
        if (!in_array($billable, ['', '0', '1'], true)) {
            $billable = '';
        }

        $sort = trim((string) $request->input('sort', 'opened_desc'));
        $allowedSorts = ['opened_desc', 'opened_asc', 'due_asc', 'due_desc', 'client_asc', 'status_asc'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'opened_desc';
        }

        return [
            'q' => trim((string) $request->input('q', '')),
            'status' => $status,
            'type' => $type,
            'client_id' => (int) $request->input('client_id', 0),
            'assigned_user_id' => (int) $request->input('assigned_user_id', 0),
            'billable' => $billable,
            'sort' => $sort,
        ];
    }

    private function defaults(): array
    {
        return [
            'numero_os' => 'Será gerado automaticamente',
            'service_name' => '',
            'client_id' => 0,
            'contact_name' => '',
            'assigned_user_id' => 0,
            'type' => ServiceOrderType::SUPORTE,
            'type_other_description' => '',
            'status' => ServiceOrderStatus::ABERTO,
            'request_description' => '',
            'executed_activities' => '',
            'technical_notes' => '',
            'internal_notes' => '',
            'opened_at' => date('Y-m-d\TH:i'),
            'due_at' => '',
            'completed_at' => '',
            'estimated_hours' => '',
            'executed_hours' => '',
            'billable' => 0,
            'base_service_id' => 0,
            'base_amount' => '0,00',
            'discount_amount' => '0,00',
            'surcharge_amount' => '0,00',
            'final_amount' => '0,00',
            'financial_receivable_id' => null,
        ];
    }

    private function prepareFormData(array $data): array
    {
        $item = array_merge($this->defaults(), $data);
        foreach (['opened_at', 'due_at', 'completed_at'] as $field) {
            $raw = trim((string) ($item[$field] ?? ''));
            if ($raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) === 1) {
                $item[$field] = date('Y-m-d\TH:i', strtotime($raw));
            }
        }
        foreach (['base_amount', 'discount_amount', 'surcharge_amount', 'final_amount'] as $field) {
            if (isset($item[$field]) && is_numeric($item[$field])) {
                $item[$field] = number_format((float) $item[$field], 2, ',', '.');
            }
        }
        return $item;
    }
}
