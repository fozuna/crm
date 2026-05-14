<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FinanceRevenueRepository;
use App\Services\InstallmentCharges;

final class FinanceRevenueApiController
{
    public function metrics(Request $request): void
    {
        $filters = $this->filters($request);
        $data = (new FinanceRevenueRepository())->metrics($filters);
        Response::json(['ok' => true, 'filters' => $filters, 'data' => $data]);
    }

    public function cashflow(Request $request): void
    {
        $filters = $this->filters($request);
        $months = (int) $request->input('months', 6);
        $buckets = (new FinanceRevenueRepository())->cashflowBuckets($filters, $months);
        Response::json(['ok' => true, 'filters' => $filters, 'months' => $months, 'buckets' => $buckets]);
    }

    public function installments(Request $request): void
    {
        $filters = $this->filters($request);
        $page = (int) $request->input('page', 1);
        $per = (int) $request->input('per_page', 50);
        $res = (new FinanceRevenueRepository())->listInstallments($filters, $page, $per);

        $rows = is_array($res['rows'] ?? null) ? $res['rows'] : [];
        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            $st = (string) ($r['status'] ?? '');
            $due = (string) ($r['due_date'] ?? '');
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
            $r['status_effective'] = $effective;
            $charges = InstallmentCharges::compute($open, $due, $today);
            $r['charges'] = $charges;
            $r['total_with_charges'] = (float) ($charges['total'] ?? $open);
        }
        unset($r);
        $res['rows'] = $rows;
        Response::json(['ok' => true, 'filters' => $filters, 'data' => $res]);
    }

    public function payments(Request $request): void
    {
        $filters = $this->filters($request);
        $page = (int) $request->input('page', 1);
        $per = (int) $request->input('per_page', 50);
        $res = (new FinanceRevenueRepository())->listPayments($filters, $page, $per);
        Response::json(['ok' => true, 'filters' => $filters, 'data' => $res]);
    }

    private function filters(Request $request): array
    {
        $from = trim((string) $request->input('from', ''));
        $to = trim((string) $request->input('to', ''));
        $projectId = (int) $request->input('project_id', 0);
        $clientId = (int) $request->input('client_id', 0);
        $status = trim((string) $request->input('status', ''));

        return [
            'from' => $from,
            'to' => $to,
            'project_id' => $projectId,
            'client_id' => $clientId,
            'status' => $status,
        ];
    }
}
