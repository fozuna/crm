<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;
use App\Services\FinanceTrace;

final class FinanceRevenueRepository
{
    public function metrics(array $filters): array
    {
        $pdo = DB::pdo();
        $where = $this->buildWhere($filters);

        FinanceTrace::log('revenues.metrics', ['filters' => $filters]);

        $today = date('Y-m-d');

        $openSql = "SELECT
              COALESCE(SUM(GREATEST(fi.amount - fi.paid_amount,0)),0) AS open_total,
              COALESCE(SUM(CASE WHEN fi.due_date < :today_overdue AND fi.status IN ('pendente','reaberto') THEN GREATEST(fi.amount - fi.paid_amount,0) ELSE 0 END),0) AS overdue_total,
              COALESCE(SUM(CASE WHEN fi.due_date >= :today_notdue AND fi.status IN ('pendente','reaberto') THEN GREATEST(fi.amount - fi.paid_amount,0) ELSE 0 END),0) AS not_due_total,
              COALESCE(SUM(CASE WHEN fi.status = 'pago' THEN fi.amount ELSE 0 END),0) AS installments_paid_total
            FROM finance_installments fi
            INNER JOIN proposals pr ON pr.id = fi.proposal_id
            INNER JOIN clients c ON c.id = pr.client_id
            LEFT JOIN projects p ON p.id = fi.project_id
            WHERE fi.status IN ('pendente','reaberto','pago','cancelado')";

        $openSql .= $where['sql'];

        $stmt = $pdo->prepare($openSql);
        $stmt->bindValue(':today_overdue', $today);
        $stmt->bindValue(':today_notdue', $today);
        $this->bindAll($stmt, $where['params']);
        $stmt->execute();
        $row = $stmt->fetch();
        $row = is_array($row) ? $row : [];

        $paymentsSql = "SELECT COALESCE(SUM(fp.amount),0) AS received_total
                        FROM finance_payments fp
                        INNER JOIN finance_installments fi ON fi.id = fp.installment_id
                        INNER JOIN proposals pr ON pr.id = fi.proposal_id
                        INNER JOIN clients c ON c.id = pr.client_id
                        LEFT JOIN projects p ON p.id = fi.project_id
                        WHERE 1=1";
        $paymentsSql .= $where['payments_sql'];

        $stmt2 = $pdo->prepare($paymentsSql);
        $this->bindAll($stmt2, $where['payments_params']);
        $stmt2->execute();
        $row2 = $stmt2->fetch();
        $row2 = is_array($row2) ? $row2 : [];

        $openTotal = (float) ($row['open_total'] ?? 0);
        $overdueTotal = (float) ($row['overdue_total'] ?? 0);
        $notDueTotal = (float) ($row['not_due_total'] ?? 0);
        $receivedTotal = (float) ($row2['received_total'] ?? 0);

        $delinquencyRate = 0.0;
        if ($openTotal > 0) {
            $delinquencyRate = $overdueTotal / $openTotal;
        }

        return [
            'totals' => [
                'receivable' => $openTotal,
                'overdue' => $overdueTotal,
                'to_receive' => $notDueTotal,
                'received' => $receivedTotal,
            ],
            'metrics' => [
                'delinquency_rate' => $delinquencyRate,
            ],
        ];
    }

    public function cashflowBuckets(array $filters, int $monthsForward = 6): array
    {
        $pdo = DB::pdo();
        $monthsForward = max(1, min(24, $monthsForward));

        FinanceTrace::log('revenues.cashflow', ['filters' => $filters, 'months' => $monthsForward]);

        $where = $this->buildWhere($filters);
        $sql = "SELECT DATE_FORMAT(fi.due_date, '%Y-%m-01') AS bucket,
                       SUM(GREATEST(fi.amount - fi.paid_amount,0)) AS open_amount
                FROM finance_installments fi
                INNER JOIN proposals pr ON pr.id = fi.proposal_id
                INNER JOIN clients c ON c.id = pr.client_id
                LEFT JOIN projects p ON p.id = fi.project_id
                WHERE fi.status IN ('pendente','reaberto')";
        $sql .= $where['sql'];
        $sql .= " GROUP BY bucket ORDER BY bucket ASC";

        $stmt = $pdo->prepare($sql);
        $this->bindAll($stmt, $where['params']);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listInstallments(array $filters, int $page = 1, int $perPage = 50): array
    {
        $pdo = DB::pdo();
        $page = max(1, $page);
        $perPage = max(1, min(5000, $perPage));
        $offset = ($page - 1) * $perPage;
        $order = $this->installmentOrderBy($filters);

        $where = $this->buildWhere($filters);
        $sql = "SELECT
                  fi.id,
                  fi.project_id,
                  fi.proposal_id,
                  COALESCE(p.title, pr.title) AS project_title,
                  COALESCE(p.workflow_phase, 'planejamento') AS workflow_phase,
                  COALESCE(p.status, 'ativo') AS project_status,
                  pr.client_id,
                  c.company AS client_company,
                  pr.id AS proposal_id,
                  pr.status AS proposal_status,
                  fi.installment_no,
                  fi.amount,
                  fi.paid_amount,
                  GREATEST(fi.amount - fi.paid_amount,0) AS open_amount,
                  fi.due_date,
                  fi.status,
                  fi.paid_at,
                  fi.canceled_at,
                  fi.canceled_by,
                  fi.cancel_reason,
                  fi.reopened_at,
                  fi.reopened_by,
                  fi.created_at,
                  fi.updated_at
                FROM finance_installments fi
                INNER JOIN proposals pr ON pr.id = fi.proposal_id
                INNER JOIN clients c ON c.id = pr.client_id
                LEFT JOIN projects p ON p.id = fi.project_id
                WHERE 1=1";
        $sql .= $where['sql'];
        $sql .= ' ORDER BY ' . $order . " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $this->bindAll($stmt, $where['params']);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        FinanceTrace::log('revenues.installments', ['filters' => $filters, 'page' => $page, 'per_page' => $perPage, 'count' => is_array($rows) ? count($rows) : 0]);

        $countSql = "SELECT COUNT(*) FROM finance_installments fi
                INNER JOIN proposals pr ON pr.id = fi.proposal_id
                INNER JOIN clients c ON c.id = pr.client_id
                LEFT JOIN projects p ON p.id = fi.project_id
                WHERE 1=1";
        $countSql .= $where['sql'];
        $stmt2 = $pdo->prepare($countSql);
        $this->bindAll($stmt2, $where['params']);
        $stmt2->execute();
        $total = (int) $stmt2->fetchColumn();

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'rows' => $rows,
        ];
    }

    public function listPayments(array $filters, int $page = 1, int $perPage = 50): array
    {
        $pdo = DB::pdo();
        $page = max(1, $page);
        $perPage = max(1, min(5000, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = $this->buildWhere($filters);
        $sql = "SELECT
                  fp.id,
                  fp.installment_id,
                  fi.project_id,
                  fi.proposal_id,
                  COALESCE(p.title, pr.title) AS project_title,
                  pr.client_id,
                  c.company AS client_company,
                  pr.status AS proposal_status,
                  fp.amount,
                  fp.method,
                  fp.reference,
                  fp.paid_at,
                  fp.created_by
                FROM finance_payments fp
                INNER JOIN finance_installments fi ON fi.id = fp.installment_id
                INNER JOIN proposals pr ON pr.id = fi.proposal_id
                INNER JOIN clients c ON c.id = pr.client_id
                LEFT JOIN projects p ON p.id = fi.project_id
                WHERE 1=1";
        $sql .= $where['payments_sql'];
        $sql .= " ORDER BY fp.paid_at DESC, fp.id DESC LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $this->bindAll($stmt, $where['payments_params']);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        FinanceTrace::log('revenues.payments', ['filters' => $filters, 'page' => $page, 'per_page' => $perPage, 'count' => is_array($rows) ? count($rows) : 0]);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'rows' => $rows,
        ];
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];
        $paymentsWhere = [];
        $paymentsParams = [];

        $fromRaw = isset($filters['from']) ? trim((string) $filters['from']) : '';
        $toRaw = isset($filters['to']) ? trim((string) $filters['to']) : '';
        [$from, $to] = $this->effectiveRange($fromRaw, $toRaw);
        if ($from !== '' && $to !== '') {
            $where[] = ' AND fi.due_date >= :from';
            $params[':from'] = $from;
            $paymentsWhere[] = ' AND fp.paid_at >= :p_from';
            $paymentsParams[':p_from'] = $from . ' 00:00:00';
            $where[] = ' AND fi.due_date <= :to';
            $params[':to'] = $to;
            $paymentsWhere[] = ' AND fp.paid_at <= :p_to';
            $paymentsParams[':p_to'] = $to . ' 23:59:59';
        }

        $projectId = (int) ($filters['project_id'] ?? 0);
        if ($projectId > 0) {
            $where[] = ' AND fi.project_id = :pid';
            $params[':pid'] = $projectId;
            $paymentsWhere[] = ' AND fi.project_id = :p_pid';
            $paymentsParams[':p_pid'] = $projectId;
        }

        $clientId = (int) ($filters['client_id'] ?? 0);
        if ($clientId > 0) {
            $where[] = ' AND pr.client_id = :cid';
            $params[':cid'] = $clientId;
            $paymentsWhere[] = ' AND pr.client_id = :p_cid';
            $paymentsParams[':p_cid'] = $clientId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        $allowed = ['pendente', 'pago', 'adiantado', 'cancelado', 'reaberto', 'vencida'];
        if ($status !== '' && in_array($status, $allowed, true)) {
            if ($status === 'vencida') {
                $where[] = ' AND fi.due_date < :today_due AND fi.status IN (\'pendente\',\'reaberto\')';
                $params[':today_due'] = date('Y-m-d');
            } elseif ($status === 'adiantado') {
                $where[] = ' AND fi.status = \'pago\' AND fi.paid_at IS NOT NULL AND fi.paid_at <> \'\' AND SUBSTRING(fi.paid_at,1,10) < fi.due_date';
            } else {
                $where[] = ' AND fi.status = :st';
                $params[':st'] = $status;
            }
        }

        return [
            'sql' => implode('', $where),
            'params' => $params,
            'payments_sql' => implode('', $paymentsWhere),
            'payments_params' => $paymentsParams,
        ];
    }

    private function installmentOrderBy(array $filters): string
    {
        $sort = trim((string) ($filters['sort'] ?? 'due_date'));
        $direction = strtolower(trim((string) ($filters['direction'] ?? 'asc')));
        $direction = $direction === 'desc' ? 'DESC' : 'ASC';

        $map = [
            'due_date' => 'fi.due_date',
            'installment_no' => 'fi.installment_no',
            'project' => 'project_title',
            'client' => 'client_company',
            'status' => 'fi.status',
            'amount' => 'fi.amount',
            'paid_amount' => 'fi.paid_amount',
            'open_amount' => 'open_amount',
        ];

        $column = $map[$sort] ?? $map['due_date'];
        return $column . ' ' . $direction . ', fi.id ' . $direction;
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

    public function effectiveRange(string $fromRaw, string $toRaw): array
    {
        $from = $this->normalizeDate($fromRaw);
        $to = $this->normalizeDate($toRaw);

        if ($from === '' && $to === '') {
            return ['', ''];
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
}
