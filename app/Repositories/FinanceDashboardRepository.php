<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinanceDashboardRepository
{
    public function dashboard(array $filters): array
    {
        $pdo = DB::pdo();
        [$from, $to] = $this->effectiveRange((string) ($filters['from'] ?? ''), (string) ($filters['to'] ?? ''));
        $clientId = (int) ($filters['client_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));
        $today = date('Y-m-d');

        $params = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59', ':today' => $today];
        $paramsTime = [':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59'];
        $whereClient = '';
        if ($clientId > 0) {
            $whereClient = ' AND pr.client_id = :cid';
            $params[':cid'] = $clientId;
            $paramsTime[':cid'] = $clientId;
        }

        $months = $this->monthBuckets($from, $to);

        $paySql = "SELECT DATE_FORMAT(fp.paid_at, '%Y-%m-01') AS bucket, COALESCE(SUM(fp.amount),0) AS total
                  FROM finance_payments fp
                  INNER JOIN finance_installments fi ON fi.id = fp.installment_id
                  INNER JOIN proposals pr ON pr.id = fi.proposal_id
                  WHERE pr.status = 'aprovada'
                    AND fp.paid_at >= :from AND fp.paid_at <= :to";
        $paySql .= $whereClient;
        $paySql .= ' GROUP BY bucket ORDER BY bucket ASC';
        $stmt = $pdo->prepare($paySql);
        $this->bindAll($stmt, $paramsTime);
        $stmt->execute();
        $payRows = $stmt->fetchAll();

        $cancelSql = "SELECT DATE_FORMAT(r.reviewed_at, '%Y-%m-01') AS bucket, COALESCE(SUM(r.total_amount),0) AS total
                     FROM finance_cancellation_requests r
                     INNER JOIN finance_installments fi ON fi.id = r.installment_id
                     INNER JOIN proposals pr ON pr.id = fi.proposal_id
                     WHERE pr.status = 'aprovada'
                       AND r.status = 'aprovada'
                       AND r.reviewed_at >= :from AND r.reviewed_at <= :to";
        $cancelSql .= $whereClient;
        $cancelSql .= ' GROUP BY bucket ORDER BY bucket ASC';
        $stmt2 = $pdo->prepare($cancelSql);
        $this->bindAll($stmt2, $paramsTime);
        $stmt2->execute();
        $cancelRows = $stmt2->fetchAll();

        $pieSql = "SELECT COALESCE(NULLIF(fp.method,''),'(sem método)') AS label, COALESCE(SUM(fp.amount),0) AS total
                  FROM finance_payments fp
                  INNER JOIN finance_installments fi ON fi.id = fp.installment_id
                  INNER JOIN proposals pr ON pr.id = fi.proposal_id
                  WHERE pr.status = 'aprovada'
                    AND fp.paid_at >= :from AND fp.paid_at <= :to";
        $pieSql .= $whereClient;
        $pieSql .= ' GROUP BY label ORDER BY total DESC LIMIT 6';
        $stmt3 = $pdo->prepare($pieSql);
        $this->bindAll($stmt3, $paramsTime);
        $stmt3->execute();
        $pieRows = $stmt3->fetchAll();

        $kpiBase = "FROM finance_installments fi
                    INNER JOIN proposals pr ON pr.id = fi.proposal_id
                    WHERE pr.status = 'aprovada'
                      AND fi.due_date >= :d_from AND fi.due_date <= :d_to";
        $kpiParams = [':d_from' => $from, ':d_to' => $to, ':today' => $today];
        if ($clientId > 0) {
            $kpiBase .= ' AND pr.client_id = :cid';
            $kpiParams[':cid'] = $clientId;
        }

        $statusWhere = '';
        if ($status !== '') {
            $allowed = ['pendente','reaberto','pago','adiantado','cancelado','vencida','atrasado'];
            if (in_array($status, $allowed, true)) {
                if ($status === 'vencida' || $status === 'atrasado') {
                    $statusWhere = " AND fi.due_date < :today AND fi.status IN ('pendente','reaberto')";
                } elseif ($status === 'adiantado') {
                    $statusWhere = " AND fi.status = 'pago' AND fi.paid_at IS NOT NULL AND fi.paid_at <> '' AND SUBSTRING(fi.paid_at,1,10) < fi.due_date";
                } else {
                    $statusWhere = ' AND fi.status = :st';
                    $kpiParams[':st'] = $status;
                }
            }
        }

        $paidCount = (int) $this->scalar($pdo, 'SELECT COUNT(*) ' . $kpiBase . " AND fi.status = 'pago'" . $statusWhere, $kpiParams);
        $pendingCount = (int) $this->scalar($pdo, 'SELECT COUNT(*) ' . $kpiBase . " AND fi.status IN ('pendente','reaberto') AND fi.due_date >= :today" . $statusWhere, $kpiParams);
        $overdueCount = (int) $this->scalar($pdo, 'SELECT COUNT(*) ' . $kpiBase . " AND fi.status IN ('pendente','reaberto') AND fi.due_date < :today" . $statusWhere, $kpiParams);

        $receipts = $this->bucketMap($payRows);
        $expenses = $this->bucketMap($cancelRows);
        $seriesRevenue = [];
        $seriesExpense = [];
        $seriesCash = [];
        $running = 0.0;
        foreach ($months as $b) {
            $r = (float) ($receipts[$b] ?? 0);
            $e = (float) ($expenses[$b] ?? 0);
            $running = round($running + $r - $e, 2);
            $seriesRevenue[] = $r;
            $seriesExpense[] = $e;
            $seriesCash[] = $running;
        }

        $pieLabels = [];
        $pieValues = [];
        foreach ($pieRows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $pieLabels[] = (string) ($r['label'] ?? '');
            $pieValues[] = (float) ($r['total'] ?? 0);
        }

        return [
            'range' => ['from' => $from, 'to' => $to],
            'kpis' => [
                'paid' => $paidCount,
                'pending' => $pendingCount,
                'overdue' => $overdueCount,
            ],
            'series' => [
                'months' => $months,
                'revenue' => $seriesRevenue,
                'expense' => $seriesExpense,
                'cashflow' => $seriesCash,
            ],
            'pie' => [
                'labels' => $pieLabels,
                'values' => $pieValues,
            ],
        ];
    }

    private function bucketMap(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $b = (string) ($r['bucket'] ?? '');
            if ($b === '') {
                continue;
            }
            $out[$b] = (float) ($r['total'] ?? 0);
        }
        return $out;
    }

    private function scalar(\PDO $pdo, string $sql, array $params): mixed
    {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            if (!is_string($k) || $k === '') {
                continue;
            }
            if (strpos($sql, $k) === false) {
                continue;
            }
            $type = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $k => $v) {
            $type = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
    }

    private function normalizeDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m) === 1) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }
        }
        return '';
    }

    private function effectiveRange(string $fromRaw, string $toRaw): array
    {
        $from = $this->normalizeDate($fromRaw);
        $to = $this->normalizeDate($toRaw);

        if ($from === '' && $to === '') {
            $from = date('Y-m-01');
            $to = date('Y-m-t');
            return [$from, $to];
        }
        if ($from !== '' && $to === '') {
            $to = date('Y-m-t', strtotime($from));
            return [$from, $to];
        }
        if ($from === '' && $to !== '') {
            $from = date('Y-m-01', strtotime($to));
            return [$from, $to];
        }
        return [$from, $to];
    }

    private function monthBuckets(string $from, string $to): array
    {
        $out = [];
        $cur = date('Y-m-01', strtotime($from));
        $end = date('Y-m-01', strtotime($to));
        while ($cur <= $end) {
            $out[] = $cur;
            $cur = date('Y-m-01', strtotime($cur . ' +1 month'));
        }
        return $out;
    }
}
