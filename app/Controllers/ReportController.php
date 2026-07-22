<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use App\Repositories\FinanceRevenueRepository;
use App\Repositories\FinancialReceiptRepository;
use App\Repositories\FinancialReceivableRepository;
use App\Services\CompanyContext;
use App\Services\CompanyProfileService;
use App\Services\PdfStandardTheme;
use App\Services\ProfessionalPdf;
use App\Services\FinanceTrace;
use App\Services\XlsxBuilder;

/**
 * Fonte de verdade financeira deste relatório: financial_accounts_receivable /
 * financial_receipts (módulo "enterprise"), a mesma usada por /financeiro/dashboard.
 *
 * Até 2026-07, este relatório lia exclusivamente finance_installments (módulo
 * "legado", ligado a proposta -> projeto), o que o tornava estruturalmente incapaz de
 * exibir qualquer título nascido de Ordem de Serviço, renegociação ou lançamento
 * manual do módulo enterprise — o Dashboard Financeiro (/financeiro/dashboard) já
 * lia a fonte correta e por isso mostrava valores que este relatório sempre omitiu.
 * Ver CRM_AUDIT.md, achado P02, e SPRINT_FINANCE_REPORT_FIX.md para o diagnóstico completo.
 */
final class ReportController
{
    public function finance(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $filters = $this->reportFilters($request);
        $installmentsPage = max(1, (int) $request->input('ins_page', 1));
        $paymentsPage = max(1, (int) $request->input('pay_page', 1));
        $perPage = (int) $request->input('per_page', 30);
        $perPage = max(5, min(100, $perPage));

        $filters = $this->normalizedRange($filters);
        $farFilters = $this->toReceivableFilters($filters);

        $receivableRepo = new FinancialReceivableRepository();
        $receiptRepo = new FinancialReceiptRepository();

        $totals = $receivableRepo->totals($companyId, $farFilters);
        $cashflow = $receivableRepo->cashflowBuckets($companyId, $farFilters);
        $installments = $receivableRepo->paginate($companyId, $farFilters, $installmentsPage, $perPage);
        $payments = $receiptRepo->listByPeriod($companyId, $farFilters, $paymentsPage, $perPage);

        $installments['rows'] = $this->withOrigin($receivableRepo, $installments['rows']);

        $insRows = $installments['rows'];
        $payRows = $payments['rows'];
        FinanceTrace::log('reports.finance', [
            'filters' => $filters,
            'totals' => $totals,
            'cashflow_count' => count($cashflow),
            'installments_count' => count($insRows),
            'payments_count' => count($payRows),
        ]);

        $report = [
            'totals' => $totals,
            'cashflow' => $cashflow,
            'installments' => $installments,
            'payments' => $payments,
        ];
        View::render('reports/finance', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'report' => $report,
        ]);
    }

    public function financeExportExcel(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $filters = $this->normalizedRange($this->reportFilters($request));
        $farFilters = $this->toReceivableFilters($filters);

        $receivableRepo = new FinancialReceivableRepository();
        $report = $receivableRepo->reportRows($companyId, $farFilters, 2000);
        $rows = $this->withOrigin($receivableRepo, $report['rows']);

        $xlsxRows = [];
        foreach ($rows as $row) {
            $xlsxRows[] = [
                $this->displayDate((string) ($row['due_date'] ?? '')),
                (string) ($row['title'] ?? ''),
                (string) ($row['origin'] ?? ''),
                trim((string) ($row['project_title'] ?? '')) !== '' ? (string) $row['project_title'] : '—',
                trim((string) ($row['client_company'] ?? '')) !== '' ? (string) $row['client_company'] : '—',
                $this->statusLabel((string) ($row['status'] ?? '')),
                (float) ($row['original_amount'] ?? 0),
                (float) ($row['received_amount'] ?? 0),
                (float) ($row['remaining_amount'] ?? 0),
            ];
        }

        $bytes = (new XlsxBuilder())->build(
            ['Vencimento', 'Título', 'Origem', 'Projeto', 'Cliente', 'Status', 'Valor original', 'Recebido', 'Saldo'],
            $xlsxRows,
            'Relatorio Financeiro'
        );

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="relatorio-financeiro.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    public function financeExportPdf(Request $request): void
    {
        $companyId = CompanyContext::currentCompanyId();
        $filters = $this->normalizedRange($this->reportFilters($request));
        $farFilters = $this->toReceivableFilters($filters);

        $receivableRepo = new FinancialReceivableRepository();
        $totals = $receivableRepo->totals($companyId, $farFilters);
        $report = $receivableRepo->reportRows($companyId, $farFilters, 1000);
        $exportRows = $this->withOrigin($receivableRepo, $report['rows']);

        $branding = [];
        try {
            $branding = (new CompanyProfileService())->branding();
        } catch (\Throwable) {
            $branding = [];
        }

        $pdf = new ProfessionalPdf();
        $pdf->addPage();
        $y = PdfStandardTheme::renderHeaderMinimal($pdf, $branding, 595, 842, 50, 54, 4, 28, 200, 32);
        $pdf->setFillColor(41, 50, 65);
        $pdf->rect(50, $y - 94, 495, 94, 'F');
        $pdf->setFillColor(255, 255, 255);
        $pdf->setFont('F2', 18);
        $pdf->text(68, $y - 28, 'Relatório Financeiro');
        $pdf->setFont('F1', 11);
        $pdf->text(68, $y - 46, 'Visão consolidada dos títulos, recebimentos e inadimplência no período filtrado.');
        $y -= 114;

        $pdf->setFillColor(26, 26, 26);
        $pdf->setFont('F1', 11);
        $range = 'Período: ' . (($filters['from'] !== '' && $filters['to'] !== '') ? ($filters['from'] . ' até ' . $filters['to']) : 'todos');
        $pdf->text(50, $y, $range);
        $y -= 14;
        $sortLabel = 'Ordenação: ' . $this->sortLabel($filters);
        $pdf->text(50, $y, $sortLabel);
        $y -= 14;

        if ($report['truncated']) {
            $pdf->setFillColor(180, 83, 9);
            $pdf->text(50, $y, 'Aviso: total de ' . $report['total'] . ' títulos no período; exibindo os primeiros ' . count($exportRows) . '. Refine os filtros para ver o restante.');
            $pdf->setFillColor(26, 26, 26);
            $y -= 14;
        }

        $kpiLine = 'A receber: R$ ' . number_format((float) ($totals['receivable'] ?? 0), 2, ',', '.') . '  |  Recebido: R$ ' . number_format((float) ($totals['received'] ?? 0), 2, ',', '.') . '  |  Vencido: R$ ' . number_format((float) ($totals['overdue'] ?? 0), 2, ',', '.');
        $pdf->text(50, $y, $kpiLine);
        $y -= 26;
        $pdf->setFillColor(241, 245, 249);
        $pdf->setStrokeColor(203, 213, 225);
        $pdf->rect(50, $y - 24, 495, 24, 'DF');
        $pdf->setFillColor(41, 50, 65);
        $pdf->setFont('F2', 11);
        $pdf->text(58, $y - 16, 'Venc.');
        $pdf->text(108, $y - 16, 'Origem');
        $pdf->text(178, $y - 16, 'Projeto/Título');
        $pdf->text(318, $y - 16, 'Cliente');
        $pdf->text(418, $y - 16, 'Status');
        $pdf->text(468, $y - 16, 'Saldo');
        $y -= 34;
        $pdf->setFont('F1', 11);

        foreach ($exportRows as $r) {
            if ($y < 70) {
                $pdf->addPage();
                $y = PdfStandardTheme::renderHeaderMinimal($pdf, $branding, 595, 842, 50, 54, 4, 28, 200, 32);
                $pdf->setFillColor(41, 50, 65);
                $pdf->setFont('F2', 12);
                $pdf->text(50, $y, 'Relatório Financeiro (continuação)');
                $y -= 18;
                $pdf->setFillColor(241, 245, 249);
                $pdf->setStrokeColor(203, 213, 225);
                $pdf->rect(50, $y - 24, 495, 24, 'DF');
                $pdf->setFillColor(41, 50, 65);
                $pdf->setFont('F2', 11);
                $pdf->text(58, $y - 16, 'Venc.');
                $pdf->text(108, $y - 16, 'Origem');
                $pdf->text(178, $y - 16, 'Projeto/Título');
                $pdf->text(318, $y - 16, 'Cliente');
                $pdf->text(418, $y - 16, 'Status');
                $pdf->text(468, $y - 16, 'Saldo');
                $y -= 34;
                $pdf->setFont('F1', 11);
            }

            $project = trim((string) ($r['project_title'] ?? '')) !== '' ? (string) $r['project_title'] : (string) ($r['title'] ?? '');
            $client = trim((string) ($r['client_company'] ?? '')) !== '' ? (string) $r['client_company'] : '—';
            $origin = (string) ($r['origin'] ?? '');
            $status = $this->statusLabel((string) ($r['status'] ?? ''));
            $pdf->text(50, $y, $this->displayDate((string) ($r['due_date'] ?? '')));
            $pdf->text(100, $y, mb_strlen($origin) > 12 ? (mb_substr($origin, 0, 12) . '…') : $origin);
            $pdf->text(170, $y, mb_strlen($project) > 24 ? (mb_substr($project, 0, 24) . '…') : $project);
            $pdf->text(310, $y, mb_strlen($client) > 16 ? (mb_substr($client, 0, 16) . '…') : $client);
            $pdf->text(410, $y, $status);
            $pdf->text(460, $y, 'R$ ' . number_format((float) ($r['remaining_amount'] ?? 0), 2, ',', '.'));
            $y -= 14;
        }

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, 595, '+5567993256260 • comercial@traxter.com.br', 20, [71, 85, 105], 10);

        $bytes = $pdf->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="relatorio-financeiro.pdf"');
        echo $bytes;
        exit;
    }

    private function reportFilters(Request $request): array
    {
        return [
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
            'project_id' => (int) $request->input('project_id', 0),
            'client_id' => (int) $request->input('client_id', 0),
            'status' => trim((string) $request->input('status', '')),
            'sort' => trim((string) $request->input('sort', 'due_date')),
            'direction' => trim((string) $request->input('direction', 'asc')),
        ];
    }

    /**
     * Aplica o mesmo default de período (mês corrente quando nenhuma data é informada)
     * já usado historicamente por esta tela, reutilizando a lógica pura de datas de
     * FinanceRevenueRepository (nenhuma tabela legada é consultada por ela).
     */
    private function normalizedRange(array $filters): array
    {
        [$from, $to] = (new FinanceRevenueRepository())->effectiveRange(
            (string) ($filters['from'] ?? ''),
            (string) ($filters['to'] ?? '')
        );
        $filters['from'] = $from;
        $filters['to'] = $to;
        return $filters;
    }

    /**
     * Traduz os filtros desta tela (from/to/project_id/client_id/status/sort/direction)
     * para o contrato esperado por FinancialReceivableRepository/FinancialReceiptRepository
     * (due_from/due_to + status do enum enterprise), evitando duplicar a validação de
     * filtros já implementada nesses repositórios.
     */
    private function toReceivableFilters(array $filters): array
    {
        $status = trim((string) ($filters['status'] ?? ''));
        $allowed = ['pending', 'partially_paid', 'paid', 'overdue', 'canceled', 'renegotiated'];
        if (!in_array($status, $allowed, true)) {
            $status = '';
        }

        $sort = trim((string) ($filters['sort'] ?? 'due_date'));
        $sortAllowed = ['due_date', 'client', 'project', 'amount', 'remaining', 'status', 'days_overdue', 'created_at'];
        if (!in_array($sort, $sortAllowed, true)) {
            $sort = 'due_date';
        }

        $direction = strtolower(trim((string) ($filters['direction'] ?? 'asc'))) === 'desc' ? 'desc' : 'asc';

        return [
            'client_id' => (int) ($filters['client_id'] ?? 0),
            'project_id' => (int) ($filters['project_id'] ?? 0),
            'status' => $status,
            'due_from' => (string) ($filters['from'] ?? ''),
            'due_to' => (string) ($filters['to'] ?? ''),
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * Marca a origem de cada título (Ordem de Serviço, Proposta/Projeto, Contrato ou
     * Manual) sem duplicar a leitura de servicos_avulsos em mais de um lugar.
     */
    private function withOrigin(FinancialReceivableRepository $repo, array $rows): array
    {
        $origins = $repo->originsForIds(array_column($rows, 'id'));
        foreach ($rows as &$row) {
            $id = (int) ($row['id'] ?? 0);
            if (isset($origins[$id])) {
                $row['origin'] = 'Ordem de serviço';
            } elseif ((int) ($row['source_installment_id'] ?? 0) > 0) {
                $row['origin'] = 'Proposta/Projeto';
            } elseif ((int) ($row['contract_id'] ?? 0) > 0) {
                $row['origin'] = 'Contrato';
            } else {
                $row['origin'] = 'Manual';
            }
        }
        unset($row);
        return $rows;
    }

    private function displayDate(string $date): string
    {
        return $date !== '' ? date('d/m/Y', strtotime($date)) : '—';
    }

    private function statusLabel(string $status): string
    {
        $map = [
            'pending' => 'Pendente',
            'partially_paid' => 'Parcialmente pago',
            'paid' => 'Pago',
            'overdue' => 'Vencido',
            'canceled' => 'Cancelado',
            'renegotiated' => 'Renegociado',
        ];
        return $map[$status] ?? $status;
    }

    private function sortLabel(array $filters): string
    {
        $sort = (string) ($filters['sort'] ?? 'due_date');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'decrescente' : 'crescente';
        $map = [
            'due_date' => 'vencimento',
            'client' => 'cliente',
            'project' => 'projeto',
            'status' => 'status',
            'amount' => 'valor original',
            'remaining' => 'saldo em aberto',
            'days_overdue' => 'dias em atraso',
            'created_at' => 'criado em',
        ];

        return ($map[$sort] ?? 'vencimento') . ' (' . $direction . ')';
    }
}
