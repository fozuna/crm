<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use App\Repositories\FinanceReportRepository;
use App\Repositories\FinanceRevenueRepository;
use App\Services\CompanyProfileService;
use App\Services\InstallmentCharges;
use App\Services\PdfStandardTheme;
use App\Services\ProfessionalPdf;
use App\Services\FinanceTrace;
use App\Services\XlsxBuilder;

final class ReportController
{
    public function finance(Request $request): void
    {
        $months = 1;
        $filters = $this->reportFilters($request);
        $installmentsPage = max(1, (int) $request->input('ins_page', 1));
        $paymentsPage = max(1, (int) $request->input('pay_page', 1));
        $perPage = (int) $request->input('per_page', 30);
        $perPage = max(5, min(200, $perPage));

        $report = (new FinanceReportRepository())->summary($months);
        $rev = new FinanceRevenueRepository();
        [$from, $to] = $rev->effectiveRange((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''));
        $filters['from'] = $from;
        $filters['to'] = $to;
        $metrics = $rev->metrics($filters);
        $cashflow = $rev->cashflowBuckets($filters, $months);
        $installments = $rev->listInstallments($filters, $installmentsPage, $perPage);
        $payments = $rev->listPayments($filters, $paymentsPage, $perPage);

        $insRows = is_array($installments['rows'] ?? null) ? $installments['rows'] : [];
        $payRows = is_array($payments['rows'] ?? null) ? $payments['rows'] : [];
        FinanceTrace::log('reports.finance', [
            'filters' => $filters,
            'months' => $months,
            'totals' => $metrics['totals'] ?? null,
            'cashflow_count' => is_array($cashflow) ? count($cashflow) : null,
            'installments_count' => count($insRows),
            'payments_count' => count($payRows),
        ]);

        $data = [
            'legacy' => $report,
            'metrics' => $metrics,
            'cashflow' => $cashflow,
            'installments' => $installments,
            'payments' => $payments,
        ];
        View::render('reports/finance', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'months' => $months,
            'filters' => $filters,
            'data' => $data,
        ]);
    }

    public function financeExportExcel(Request $request): void
    {
        $filters = $this->reportFilters($request);

        $rev = new FinanceRevenueRepository();
        [$from, $to] = $rev->effectiveRange((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''));
        $filters['from'] = $from;
        $filters['to'] = $to;
        $res = $rev->listInstallments($filters, 1, 2000);
        $rows = is_array($res['rows'] ?? null) ? $res['rows'] : [];
        $exportRows = $this->installmentExportRows($rows);
        $xlsxRows = [];
        foreach ($exportRows as $row) {
            $xlsxRows[] = [
                $row['due_date'],
                $row['installment_no'],
                $row['project_title'],
                $row['client_company'],
                $row['status'],
                $row['amount'],
                $row['paid_amount'],
                $row['open_amount'],
                $row['penalty'],
                $row['interest'],
                $row['total'],
            ];
        }

        $bytes = (new XlsxBuilder())->build(
            ['Vencimento','Parcela','Projeto','Cliente','Status','Valor','Pago','Aberto','Multa','Juros','Total'],
            $xlsxRows,
            'Relatorio Financeiro'
        );

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="relatorio-financeiro-parcelas.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    public function financeExportPdf(Request $request): void
    {
        $filters = $this->reportFilters($request);

        $rev = new FinanceRevenueRepository();
        [$from, $to] = $rev->effectiveRange((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''));
        $filters['from'] = $from;
        $filters['to'] = $to;
        $metrics = $rev->metrics($filters);
        $res = $rev->listInstallments($filters, 1, 1000);
        $rows = is_array($res['rows'] ?? null) ? $res['rows'] : [];
        $exportRows = $this->installmentExportRows($rows);

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
        $pdf->text(68, $y - 28, 'Relatório Financeiro - Parcelas');
        $pdf->setFont('F1', 11);
        $pdf->text(68, $y - 46, 'Visão consolidada das parcelas geradas, recebidas e vencidas no período filtrado.');
        $y -= 114;

        $pdf->setFillColor(26, 26, 26);
        $pdf->setFont('F1', 11);
        $range = 'Período: ' . (($filters['from'] !== '' && $filters['to'] !== '') ? ($filters['from'] . ' até ' . $filters['to']) : 'todos');
        $pdf->text(50, $y, $range);
        $y -= 14;
        $sortLabel = 'Ordenação: ' . $this->sortLabel($filters);
        $pdf->text(50, $y, $sortLabel);
        $y -= 14;

        $totals = is_array(($metrics['totals'] ?? null)) ? $metrics['totals'] : [];
        $kpiLine = 'A receber: R$ ' . number_format((float) ($totals['receivable'] ?? 0), 2, ',', '.') . '  |  Recebido: R$ ' . number_format((float) ($totals['received'] ?? 0), 2, ',', '.') . '  |  Vencido: R$ ' . number_format((float) ($totals['overdue'] ?? 0), 2, ',', '.');
        $pdf->text(50, $y, $kpiLine);
        $y -= 26;
        $pdf->setFillColor(241, 245, 249);
        $pdf->setStrokeColor(203, 213, 225);
        $pdf->rect(50, $y - 24, 495, 24, 'DF');
        $pdf->setFillColor(41, 50, 65);
        $pdf->setFont('F2', 11);
        $pdf->text(58, $y - 16, 'Venc.');
        $pdf->text(108, $y - 16, 'N');
        $pdf->text(138, $y - 16, 'Projeto');
        $pdf->text(268, $y - 16, 'Cliente');
        $pdf->text(388, $y - 16, 'Status');
        $pdf->text(458, $y - 16, 'Total');
        $y -= 34;
        $pdf->setFont('F1', 11);

        foreach ($exportRows as $r) {
            if ($y < 70) {
                $pdf->addPage();
                $y = PdfStandardTheme::renderHeaderMinimal($pdf, $branding, 595, 842, 50, 54, 4, 28, 200, 32);
                $pdf->setFillColor(41, 50, 65);
                $pdf->setFont('F2', 12);
                $pdf->text(50, $y, 'Relatório Financeiro - Parcelas (continuação)');
                $y -= 18;
                $pdf->setFillColor(241, 245, 249);
                $pdf->setStrokeColor(203, 213, 225);
                $pdf->rect(50, $y - 24, 495, 24, 'DF');
                $pdf->setFillColor(41, 50, 65);
                $pdf->setFont('F2', 11);
                $pdf->text(58, $y - 16, 'Venc.');
                $pdf->text(108, $y - 16, 'N');
                $pdf->text(138, $y - 16, 'Projeto');
                $pdf->text(268, $y - 16, 'Cliente');
                $pdf->text(388, $y - 16, 'Status');
                $pdf->text(458, $y - 16, 'Total');
                $y -= 34;
                $pdf->setFont('F1', 11);
            }

            $project = (string) ($r['project_title'] ?? '');
            $client = (string) ($r['client_company'] ?? '—');
            $status = (string) ($r['status'] ?? '');
            $pdf->text(50, $y, (string) ($r['due_date'] ?? '—'));
            $pdf->text(100, $y, (string) ($r['installment_no'] ?? '0'));
            $pdf->text(130, $y, mb_strlen($project) > 20 ? (mb_substr($project, 0, 20) . '…') : $project);
            $pdf->text(260, $y, mb_strlen($client) > 18 ? (mb_substr($client, 0, 18) . '…') : $client);
            $pdf->text(380, $y, $status);
            $pdf->text(450, $y, 'R$ ' . number_format((float) ($r['total'] ?? 0), 2, ',', '.'));
            $y -= 14;
        }

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, 595, '+5567993256260 • comercial@traxter.com.br', 20, [71, 85, 105], 10);

        $bytes = $pdf->output();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="relatorio-financeiro-parcelas.pdf"');
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

    private function installmentExportRows(array $rows): array
    {
        $today = date('Y-m-d');
        $items = [];

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }

            $due = (string) ($r['due_date'] ?? '');
            $st = (string) ($r['status'] ?? '');
            $paidAt = (string) ($r['paid_at'] ?? '');
            $open = (float) ($r['open_amount'] ?? 0);
            $effective = $st;
            if ($st === 'atrasado') {
                $effective = 'vencida';
            }
            if (($st === 'pendente' || $st === 'reaberto') && $due !== '' && $due < $today) {
                $effective = 'vencida';
            }
            if ($st === 'pago' && $paidAt !== '' && $due !== '' && substr($paidAt, 0, 10) < $due) {
                $effective = 'adiantada';
            }
            $charges = InstallmentCharges::compute($open, $due, $today);

            $items[] = [
                'due_date' => $due !== '' ? date('d/m/Y', strtotime($due)) : '—',
                'installment_no' => (int) ($r['installment_no'] ?? 0),
                'project_title' => (string) ($r['project_title'] ?? ''),
                'client_company' => trim((string) ($r['client_company'] ?? '')) !== '' ? trim((string) ($r['client_company'] ?? '')) : '—',
                'status' => $effective,
                'amount' => (float) ($r['amount'] ?? 0),
                'paid_amount' => (float) ($r['paid_amount'] ?? 0),
                'open_amount' => $open,
                'penalty' => (float) ($charges['penalty'] ?? 0),
                'interest' => (float) ($charges['interest'] ?? 0),
                'total' => (float) ($charges['total'] ?? $open),
            ];
        }

        return $items;
    }

    private function sortLabel(array $filters): string
    {
        $sort = (string) ($filters['sort'] ?? 'due_date');
        $direction = strtolower((string) ($filters['direction'] ?? 'asc')) === 'desc' ? 'decrescente' : 'crescente';
        $map = [
            'due_date' => 'vencimento',
            'installment_no' => 'número da parcela',
            'project' => 'projeto',
            'client' => 'cliente',
            'status' => 'status',
            'amount' => 'valor',
            'paid_amount' => 'valor pago',
            'open_amount' => 'saldo em aberto',
        ];

        return ($map[$sort] ?? 'vencimento') . ' (' . $direction . ')';
    }
}
