<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ClientRepository;
use App\Repositories\FinancialAuditLogRepository;
use App\Repositories\FinancialCatalogRepository;
use App\Repositories\FinancialEnterpriseDashboardRepository;
use App\Repositories\FinancialEnterpriseReportRepository;
use App\Repositories\FinancialReceiptRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\ProposalBrandingRepository;
use App\Repositories\ProjectRepository;
use App\Services\CompanyContext;
use App\Services\CompanyProfileService;
use App\Services\FinancialReceiptPdfGenerator;
use App\Services\FinancialReceivableService;
use App\Services\Money;
use App\Services\ProfessionalPdf;
use App\Services\XlsxBuilder;

final class FinancialModuleController
{
    public function dashboard(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $filters = $this->filters($request);
        $service = new FinancialReceivableService();
        $service->refreshStatuses($companyId);
        $dashboard = (new FinancialEnterpriseDashboardRepository())->data($companyId, $filters);

        View::render('financial/dashboard', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'clients' => (new ClientRepository())->all(),
            'dashboard' => $dashboard,
            'canManage' => $this->canManage(),
        ]);
    }

    public function index(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $service = new FinancialReceivableService();
        $service->refreshStatuses($companyId);
        $filters = $this->filters($request);
        $page = max(1, (int) $request->input('page', 1));
        $list = (new FinancialReceivableRepository())->paginate($companyId, $filters, $page, 20);

        View::render('financial/receivables/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'list' => $list,
            'clients' => (new ClientRepository())->all(),
            'projects' => (new ProjectRepository())->list(),
            'catalog' => $this->catalogData($companyId),
            'ok' => trim((string) $request->input('ok', '')),
            'error' => trim((string) $request->input('error', '')),
            'canManage' => $this->canManage(),
        ]);
    }

    public function create(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $clientId = (int) ($request->input('client_id', 0));
        $projects = $this->approvedProjects($clientId);
        View::render('financial/receivables/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'receivable' => null,
            'clients' => (new ClientRepository())->all(),
            'projects' => $projects,
            'catalog' => $this->catalogData($companyId),
            'contracts' => $this->contracts(),
            'projectHelpMessage' => $this->projectHelpMessage($clientId, $projects),
            'error' => '',
        ]);
    }

    public function store(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinancialReceivableService())->create($this->payloadFromRequest($request, $companyId, $actorId), $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis?ok=' . rawurlencode('Conta(s) a receber criada(s) com sucesso.'));
        } catch (\Throwable $e) {
            $clientId = (int) $request->input('client_id', 0);
            $projects = $this->approvedProjects($clientId);
            View::render('financial/receivables/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'receivable' => $request->allPost(),
                'clients' => (new ClientRepository())->all(),
                'projects' => $projects,
                'catalog' => $this->catalogData($companyId),
                'contracts' => $this->contracts(),
                'projectHelpMessage' => $this->projectHelpMessage($clientId, $projects),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function show(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $receivable = (new FinancialReceivableRepository())->find($companyId, $id);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }

        View::render('financial/receivables/show', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'receivable' => $receivable,
            'receipts' => (new FinancialReceiptRepository())->listByReceivable($id),
            'latestReceipt' => (new FinancialReceiptRepository())->latestActiveByReceivable($id),
            'audit' => (new FinancialAuditLogRepository())->listByReceivable($companyId, $id),
            'ok' => trim((string) $request->input('ok', '')),
            'error' => trim((string) $request->input('error', '')),
            'canManage' => $this->canManage(),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $receivable = (new FinancialReceivableRepository())->find($companyId, $id);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }
        $clientId = (int) ($receivable['client_id'] ?? 0);
        $projects = $this->approvedProjects($clientId);

        View::render('financial/receivables/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'receivable' => $receivable,
            'clients' => (new ClientRepository())->all(),
            'projects' => $projects,
            'catalog' => $this->catalogData($companyId),
            'contracts' => $this->contracts(),
            'projectHelpMessage' => $this->projectHelpMessage($clientId, $projects),
            'error' => '',
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinancialReceivableService())->update($companyId, $id, $this->payloadFromRequest($request, $companyId, $actorId), $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . $id . '?ok=' . rawurlencode('Conta a receber atualizada com sucesso.'));
        } catch (\Throwable $e) {
            $clientId = (int) $request->input('client_id', 0);
            $projects = $this->approvedProjects($clientId);
            $receivable = array_merge($request->allPost(), ['id' => $id]);
            View::render('financial/receivables/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'receivable' => $receivable,
                'clients' => (new ClientRepository())->all(),
                'projects' => $projects,
                'catalog' => $this->catalogData($companyId),
                'contracts' => $this->contracts(),
                'projectHelpMessage' => $this->projectHelpMessage($clientId, $projects),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function destroy(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinancialReceivableService())->delete($companyId, $id, $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis?ok=' . rawurlencode('Conta a receber excluída com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/financeiro/recebiveis?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function duplicate(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            $new = (new FinancialReceivableService())->duplicate($companyId, $id, $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . (int) ($new['id'] ?? 0) . '?ok=' . rawurlencode('Título duplicado com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/financeiro/recebiveis?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function renegotiate(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            $new = (new FinancialReceivableService())->renegotiate($companyId, $id, [
                'new_due_date' => trim((string) $request->input('new_due_date', '')),
                'notes' => trim((string) $request->input('notes', '')),
            ], $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . (int) ($new['id'] ?? 0) . '?ok=' . rawurlencode('Renegociação concluída com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . $id . '?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function receipt(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinancialReceivableService())->registerReceipt($companyId, $id, [
                'amount_received' => Money::parseBRL((string) $request->input('amount_received', '0')),
                'interest_amount' => Money::parseBRL((string) $request->input('interest_amount', '0')),
                'fine_amount' => Money::parseBRL((string) $request->input('fine_amount', '0')),
                'discount_amount' => Money::parseBRL((string) $request->input('discount_amount', '0')),
                'payment_method' => trim((string) $request->input('payment_method', '')),
                'payment_date' => trim((string) $request->input('payment_date', '')),
                'transaction_reference' => trim((string) $request->input('transaction_reference', '')),
                'bank_reference' => trim((string) $request->input('bank_reference', '')),
                'receipt_file_path' => $this->handleReceiptUpload($id),
                'observation' => trim((string) $request->input('observation', '')),
            ], $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . $id . '?ok=' . rawurlencode('Baixa registrada com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . $id . '?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function reverse(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $receiptId = (int) $request->input('receipt_id', 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinancialReceivableService())->reverseReceipt($companyId, $id, $receiptId, trim((string) $request->input('reason', '')), $actorId, $this->ip());
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . $id . '?ok=' . rawurlencode('Estorno registrado com sucesso.'));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/financeiro/recebiveis/' . $id . '?error=' . rawurlencode($e->getMessage()));
        }
    }

    public function reports(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $filters = $this->filters($request);
        $data = (new FinancialEnterpriseReportRepository())->reports($companyId, $filters);

        View::render('financial/reports/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'reports' => $data,
            'clients' => (new ClientRepository())->all(),
            'projects' => (new ProjectRepository())->list(),
            'canManage' => $this->canManage(),
        ]);
    }

    public function exportCsv(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $reports = (new FinancialEnterpriseReportRepository())->reports($companyId, $this->filters($request));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="financial-reports.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Tipo', 'Referencia', 'Valor'], ';');
        foreach (($reports['receivables'] ?? []) as $row) {
            fputcsv($out, ['conta', (string) ($row['title'] ?? ''), number_format((float) ($row['remaining_amount'] ?? 0), 2, ',', '.')], ';');
        }
        fclose($out);
        exit;
    }

    public function exportExcel(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $reports = (new FinancialEnterpriseReportRepository())->reports($companyId, $this->filters($request));
        $rows = [];
        foreach (($reports['receivables'] ?? []) as $row) {
            $rows[] = [
                (string) ($row['title'] ?? ''),
                (string) ($row['client_company'] ?? ''),
                (string) ($row['project_title'] ?? ''),
                (string) ($row['due_date'] ?? ''),
                (float) ($row['original_amount'] ?? 0),
                (float) ($row['remaining_amount'] ?? 0),
                (string) ($row['status'] ?? ''),
            ];
        }
        $bytes = (new XlsxBuilder())->build(['Titulo', 'Cliente', 'Projeto', 'Vencimento', 'Valor', 'Saldo', 'Status'], $rows, 'Recebiveis');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="financial-reports.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    public function exportPdf(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $reports = (new FinancialEnterpriseReportRepository())->reports($companyId, $this->filters($request));
        $pdf = new ProfessionalPdf();
        $pdf->addPage();
        $pdf->setFont('F2', 12);
        $pdf->text(40, 800, 'Relatório Financeiro Enterprise');
        $pdf->setFont('F1', 11);
        $y = 780;
        foreach (array_slice((array) ($reports['receivables'] ?? []), 0, 30) as $row) {
            if ($y < 70) {
                $pdf->addPage();
                $y = 800;
            }
            $line = (string) ($row['due_date'] ?? '') . ' | ' . (string) ($row['title'] ?? '') . ' | R$ ' . number_format((float) ($row['remaining_amount'] ?? 0), 2, ',', '.');
            $pdf->text(40, $y, mb_strlen($line) > 95 ? mb_substr($line, 0, 95) . '...' : $line);
            $y -= 14;
        }
        $bytes = $pdf->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="financial-reports.pdf"');
        echo $bytes;
        exit;
    }

    public function printable(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $receivable = (new FinancialReceivableRepository())->find($companyId, $id);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }
        View::render('financial/receivables/print', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'receivable' => $receivable,
            'receipts' => (new FinancialReceiptRepository())->listByReceivable($id),
        ], null);
    }

    public function receiptPreview(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $receivableId = (int) ($params['id'] ?? 0);
        $receiptId = (int) ($params['receiptId'] ?? 0);

        $receivable = (new FinancialReceivableRepository())->find($companyId, $receivableId);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }

        $receipt = $this->guardReceipt($receivableId, $receiptId);
        if (!is_array($receipt)) {
            return;
        }

        View::render('financial/receipts/preview', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'receivable' => $receivable,
            'receipt' => $receipt,
            'branding' => $this->receiptBranding(),
        ], null);
    }

    public function receiptPdf(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $receivableId = (int) ($params['id'] ?? 0);
        $receiptId = (int) ($params['receiptId'] ?? 0);

        $receivable = (new FinancialReceivableRepository())->find($companyId, $receivableId);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }

        $receipt = $this->guardReceipt($receivableId, $receiptId);
        if (!is_array($receipt)) {
            return;
        }

        $bytes = (new FinancialReceiptPdfGenerator())->build($this->receiptBranding(), $receivable, $receipt);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="recibo-pagamento-' . $receivableId . '-' . $receiptId . '.pdf"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    public function pdf(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $receivable = (new FinancialReceivableRepository())->find($companyId, $id);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }
        $pdf = new ProfessionalPdf();
        $pdf->addPage();
        $pdf->setFont('F2', 12);
        $pdf->text(40, 800, 'Conta a Receber #' . $id);
        $pdf->setFont('F1', 11);
        $lines = [
            'Titulo: ' . (string) ($receivable['title'] ?? ''),
            'Cliente: ' . (string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? '')),
            'Projeto: ' . (string) ($receivable['project_title'] ?? '—'),
            'Status: ' . (string) ($receivable['status'] ?? ''),
            'Vencimento: ' . (string) ($receivable['due_date'] ?? ''),
            'Valor original: R$ ' . number_format((float) ($receivable['original_amount'] ?? 0), 2, ',', '.'),
            'Saldo: R$ ' . number_format((float) ($receivable['remaining_amount'] ?? 0), 2, ',', '.'),
        ];
        $y = 780;
        foreach ($lines as $line) {
            $pdf->text(40, $y, $line);
            $y -= 16;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="receivable-' . $id . '.pdf"');
        echo $pdf->output();
        exit;
    }

    public function receiptFile(Request $request, array $params): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $id = (int) ($params['id'] ?? 0);
        $receiptId = (int) ($params['receiptId'] ?? 0);

        $receivable = (new FinancialReceivableRepository())->find($companyId, $id);
        if ($receivable === null) {
            http_response_code(404);
            echo 'Conta a receber não encontrada.';
            return;
        }

        $receipt = (new FinancialReceiptRepository())->find($receiptId);
        if ($receipt === null || (int) ($receipt['receivable_id'] ?? 0) !== $id) {
            http_response_code(404);
            echo 'Comprovante não encontrado.';
            return;
        }

        $path = trim((string) ($receipt['receipt_file_path'] ?? ''));
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            echo 'Arquivo de comprovante não encontrado.';
            return;
        }

        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : 'application/octet-stream';
        $name = basename($path);
        header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
        readfile($path);
        exit;
    }

    private function payloadFromRequest(Request $request, int $companyId, int $actorId): array
    {
        $clientId = (int) $request->input('client_id', 0);
        $projectId = $this->validatedProjectId($clientId, (int) $request->input('project_id', 0));

        return [
            'company_id' => $companyId,
            'project_id' => $projectId,
            'client_id' => $clientId,
            'contract_id' => (int) $request->input('contract_id', 0),
            'installment_number' => (int) $request->input('installment_number', 1),
            'total_installments' => (int) $request->input('total_installments', 1),
            'title' => trim((string) $request->input('title', '')),
            'description' => trim((string) $request->input('description', '')),
            'original_amount' => Money::parseBRL((string) $request->input('original_amount', '0')),
            'discount_amount' => Money::parseBRL((string) $request->input('discount_amount', '0')),
            'interest_amount' => Money::parseBRL((string) $request->input('interest_amount', '0')),
            'fine_amount' => Money::parseBRL((string) $request->input('fine_amount', '0')),
            'due_date' => trim((string) $request->input('due_date', '')),
            'issue_date' => trim((string) $request->input('issue_date', '')),
            'payment_date' => trim((string) $request->input('payment_date', '')),
            'competence_date' => trim((string) $request->input('competence_date', '')),
            'status' => trim((string) $request->input('status', 'pending')),
            'payment_method' => trim((string) $request->input('payment_method', '')),
            'payment_channel' => trim((string) $request->input('payment_channel', '')),
            'bank_account_id' => (int) $request->input('bank_account_id', 0),
            'category_id' => (int) $request->input('category_id', 0),
            'cost_center_id' => (int) $request->input('cost_center_id', 0),
            'invoice_number' => trim((string) $request->input('invoice_number', '')),
            'external_reference' => trim((string) $request->input('external_reference', '')),
            'notes' => trim((string) $request->input('notes', '')),
            'recurrence_interval_months' => (int) $request->input('recurrence_interval_months', 0),
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ];
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

    private function catalogData(int $companyId): array
    {
        $repo = new FinancialCatalogRepository();
        return [
            'categories' => $repo->categories($companyId),
            'cost_centers' => $repo->costCenters($companyId),
            'bank_accounts' => $repo->bankAccounts($companyId),
        ];
    }

    private function contracts(): array
    {
        return DB::pdo()->query('SELECT id, proposal_id FROM contracts ORDER BY id DESC')->fetchAll();
    }

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function canManage(): bool
    {
        $role = trim((string) Session::get('user_role', ''));
        $isAdmin = (int) Session::get('is_admin', 0) === 1;
        return $isAdmin || in_array($role, ['admin', 'finance'], true);
    }

    private function approvedProjects(int $clientId): array
    {
        return (new ProjectRepository())->approvedByClient($clientId);
    }

    private function projectHelpMessage(int $clientId, array $projects): string
    {
        if ($clientId <= 0) {
            return 'Selecione um cliente para listar apenas projetos vinculados a propostas aprovadas.';
        }

        if (count($projects) === 0) {
            return 'Este cliente não possui projetos vinculados a propostas aprovadas.';
        }

        return 'Foram encontrados ' . count($projects) . ' projeto(s) aprovado(s) para este cliente.';
    }

    private function validatedProjectId(int $clientId, int $projectId): int
    {
        if ($projectId <= 0) {
            return 0;
        }

        if ($clientId <= 0) {
            throw new \RuntimeException('Selecione um cliente válido antes de vincular um projeto.');
        }

        $repo = new ProjectRepository();
        if (!$repo->belongsToClientApprovedProposal($projectId, $clientId)) {
            throw new \RuntimeException('O projeto selecionado não pertence ao cliente informado ou não está vinculado a proposta aprovada.');
        }

        return $projectId;
    }

    private function handleReceiptUpload(int $receivableId): ?string
    {
        if (!isset($_FILES['receipt_file']) || !is_array($_FILES['receipt_file'])) {
            return null;
        }
        $file = $_FILES['receipt_file'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload do comprovante.');
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new \RuntimeException('Arquivo de comprovante inválido.');
        }
        $dir = __DIR__ . '/../../storage/uploads/financial_receipts/' . $receivableId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar diretório do comprovante.');
        }
        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $ext = $ext !== '' ? $ext : 'bin';
        $target = $dir . '/receipt_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new \RuntimeException('Não foi possível salvar o comprovante.');
        }
        return $target;
    }

    private function guardReceipt(int $receivableId, int $receiptId): ?array
    {
        if ($receiptId <= 0) {
            http_response_code(422);
            echo 'Recibo de pagamento não encontrado.';
            return null;
        }

        $receipt = (new FinancialReceiptRepository())->find($receiptId);
        if ($receipt === null || (int) ($receipt['receivable_id'] ?? 0) !== $receivableId) {
            http_response_code(404);
            echo 'Recibo de pagamento não encontrado.';
            return null;
        }

        if (($receipt['reversed_at'] ?? null) !== null) {
            http_response_code(422);
            echo 'Não é possível emitir recibo para uma baixa estornada.';
            return null;
        }

        if ((float) ($receipt['amount_received'] ?? 0) <= 0 && (float) ($receipt['interest_amount'] ?? 0) <= 0 && (float) ($receipt['fine_amount'] ?? 0) <= 0) {
            http_response_code(422);
            echo 'Dados insuficientes para emitir o recibo de pagamento.';
            return null;
        }

        return $receipt;
    }

    private function receiptBranding(): array
    {
        $branding = (new CompanyProfileService())->branding();
        $companyName = trim((string) ($branding['company_name'] ?? ''));
        $logoPath = trim((string) ($branding['logo_path'] ?? ''));
        $logoMime = trim((string) ($branding['logo_dark_mime'] ?? $branding['logo_light_mime'] ?? ''));
        $logoOriginalName = $logoPath !== '' ? basename($logoPath) : '';

        return [
            'company_name' => $companyName !== '' ? $companyName : 'TRAXTER',
            'logo_path' => $logoPath,
            'logo_mime' => $logoMime,
            'logo_original_name' => $logoOriginalName,
            'logo_preview_src' => $this->inlineImageSrc($logoPath, $logoMime),
            'primary_color' => (string) ($branding['primary_color'] ?? '#293241'),
            'accent_color' => (string) ($branding['accent_color'] ?? '#0ea5a4'),
        ];
    }

    private function detectLogoMime(string $path): string
    {
        $path = trim($path);
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
        if ($mime !== '') {
            return $mime;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => '',
        };
    }

    private function inlineImageSrc(string $path, string $mime): string
    {
        $path = trim($path);
        if ($path === '' || !is_file($path)) {
            return '';
        }

        $resolvedMime = trim($mime) !== '' ? trim($mime) : $this->detectLogoMime($path);
        if ($resolvedMime === '' || !str_starts_with($resolvedMime, 'image/')) {
            return '';
        }

        $bytes = @file_get_contents($path);
        if (!is_string($bytes) || $bytes === '') {
            return '';
        }

        return 'data:' . $resolvedMime . ';base64,' . base64_encode($bytes);
    }
}
