<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinancialEnterpriseDashboardRepository
{
    public function data(int $companyId, array $filters): array
    {
        [$where, $params] = $this->filters($companyId, $filters);
        $pdo = DB::pdo();

        $totalsSql = "SELECT
                        COALESCE(SUM(CASE WHEN far.status IN ('pending','partially_paid','overdue') THEN far.remaining_amount ELSE 0 END),0) AS total_receivable,
                        COALESCE(SUM(CASE WHEN far.status = 'overdue' THEN far.remaining_amount ELSE 0 END),0) AS total_overdue,
                        COALESCE(SUM(CASE WHEN far.payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND far.status = 'paid' THEN far.received_amount ELSE 0 END),0) AS total_received_month
                      FROM financial_accounts_receivable far
                      WHERE far.deleted_at IS NULL {$where}";
        $stmt = $pdo->prepare($totalsSql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        $totals = $stmt->fetch() ?: [];

        $projectionSql = "SELECT DATE_FORMAT(far.due_date, '%Y-%m-01') AS bucket, COALESCE(SUM(far.remaining_amount),0) AS total
                          FROM financial_accounts_receivable far
                          WHERE far.deleted_at IS NULL
                            AND far.status IN ('pending','partially_paid','overdue')
                            {$where}
                          GROUP BY bucket
                          ORDER BY bucket ASC";
        $stmt2 = $pdo->prepare($projectionSql);
        $this->bindAll($stmt2, $params);
        $stmt2->execute();
        $projection = $stmt2->fetchAll();

        $inadSql = "SELECT c.company AS client_company, c.name AS client_name, COALESCE(SUM(far.remaining_amount),0) AS total
                    FROM financial_accounts_receivable far
                    INNER JOIN clients c ON c.id = far.client_id
                    WHERE far.deleted_at IS NULL
                      AND far.status = 'overdue'
                      {$where}
                    GROUP BY far.client_id, c.company, c.name
                    ORDER BY total DESC
                    LIMIT 10";
        $stmt3 = $pdo->prepare($inadSql);
        $this->bindAll($stmt3, $params);
        $stmt3->execute();

        $upcomingSql = "SELECT far.id, far.title, far.due_date, far.remaining_amount, c.company AS client_company, p.title AS project_title
                        FROM financial_accounts_receivable far
                        INNER JOIN clients c ON c.id = far.client_id
                        LEFT JOIN projects p ON p.id = far.project_id
                        WHERE far.deleted_at IS NULL
                          AND far.status IN ('pending','partially_paid')
                          AND far.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)
                          {$where}
                        ORDER BY far.due_date ASC
                        LIMIT 10";
        $stmt4 = $pdo->prepare($upcomingSql);
        $this->bindAll($stmt4, $params);
        $stmt4->execute();

        $receiptsSql = "SELECT DATE_FORMAT(fr.payment_date, '%Y-%m-01') AS bucket,
                               COALESCE(SUM((fr.amount_received + fr.interest_amount + fr.fine_amount) - fr.discount_amount),0) AS total
                        FROM financial_receipts fr
                        INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                        WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$where}
                        GROUP BY bucket ORDER BY bucket ASC";
        $stmt5 = $pdo->prepare($receiptsSql);
        $this->bindAll($stmt5, $params);
        $stmt5->execute();

        return [
            'totals' => $totals,
            'projection' => $projection,
            'top_overdue_clients' => $stmt3->fetchAll(),
            'upcoming' => $stmt4->fetchAll(),
            'receipts_series' => $stmt5->fetchAll(),
        ];
    }

    private function filters(int $companyId, array $filters): array
    {
        $where = [' AND far.company_id = :company_id'];
        $params = [':company_id' => $companyId];
        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if ($from !== '') {
            $where[] = ' AND far.due_date >= :from';
            $params[':from'] = $from;
        }
        if ($to !== '') {
            $where[] = ' AND far.due_date <= :to';
            $params[':to'] = $to;
        }
        $clientId = (int) ($filters['client_id'] ?? 0);
        if ($clientId > 0) {
            $where[] = ' AND far.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }
        return [implode('', $where), $params];
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    }
}
