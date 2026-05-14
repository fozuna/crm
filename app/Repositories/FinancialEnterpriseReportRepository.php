<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinancialEnterpriseReportRepository
{
    public function reports(int $companyId, array $filters): array
    {
        [$where, $params] = $this->filters($companyId, $filters);
        $pdo = DB::pdo();

        $summary = $this->fetchAll($pdo, "SELECT far.id, far.title, far.due_date, far.status, far.original_amount, far.remaining_amount, c.company AS client_company, p.title AS project_title
                                          FROM financial_accounts_receivable far
                                          INNER JOIN clients c ON c.id = far.client_id
                                          LEFT JOIN projects p ON p.id = far.project_id
                                          WHERE far.deleted_at IS NULL {$where}
                                          ORDER BY far.due_date ASC, far.id ASC", $params);

        $delinquency = $this->fetchAll($pdo, "SELECT c.company AS client_company, COUNT(*) AS qty, COALESCE(SUM(far.remaining_amount),0) AS total
                                              FROM financial_accounts_receivable far
                                              INNER JOIN clients c ON c.id = far.client_id
                                              WHERE far.deleted_at IS NULL AND far.status = 'overdue' {$where}
                                              GROUP BY far.client_id, c.company
                                              ORDER BY total DESC", $params);

        $cashflow = $this->fetchAll($pdo, "SELECT DATE_FORMAT(far.due_date, '%Y-%m-01') AS bucket, COALESCE(SUM(far.remaining_amount),0) AS total
                                           FROM financial_accounts_receivable far
                                           WHERE far.deleted_at IS NULL AND far.status IN ('pending','partially_paid','overdue') {$where}
                                           GROUP BY bucket ORDER BY bucket ASC", $params);

        $receiptsByPeriod = $this->fetchAll($pdo, "SELECT DATE(fr.payment_date) AS period, COALESCE(SUM((fr.amount_received + fr.interest_amount + fr.fine_amount) - fr.discount_amount),0) AS total
                                                   FROM financial_receipts fr
                                                   INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                                                   WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$where}
                                                   GROUP BY DATE(fr.payment_date) ORDER BY DATE(fr.payment_date) ASC", $params);

        $receiptsByClient = $this->fetchAll($pdo, "SELECT c.company AS client_company, COALESCE(SUM((fr.amount_received + fr.interest_amount + fr.fine_amount) - fr.discount_amount),0) AS total
                                                   FROM financial_receipts fr
                                                   INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                                                   INNER JOIN clients c ON c.id = far.client_id
                                                   WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$where}
                                                   GROUP BY far.client_id, c.company
                                                   ORDER BY total DESC", $params);

        $projectPerf = $this->fetchAll($pdo, "SELECT COALESCE(p.title, '(sem projeto)') AS project_title, COALESCE(SUM(far.received_amount),0) AS received, COALESCE(SUM(far.remaining_amount),0) AS open_total
                                              FROM financial_accounts_receivable far
                                              LEFT JOIN projects p ON p.id = far.project_id
                                              WHERE far.deleted_at IS NULL {$where}
                                              GROUP BY far.project_id, p.title
                                              ORDER BY received DESC, open_total DESC", $params);

        $aging = $this->fetchAll($pdo, "SELECT
                                          SUM(CASE WHEN far.status IN ('pending','partially_paid') AND far.due_date >= CURDATE() THEN far.remaining_amount ELSE 0 END) AS current_bucket,
                                          SUM(CASE WHEN far.status = 'overdue' AND DATEDIFF(CURDATE(), far.due_date) BETWEEN 1 AND 30 THEN far.remaining_amount ELSE 0 END) AS bucket_30,
                                          SUM(CASE WHEN far.status = 'overdue' AND DATEDIFF(CURDATE(), far.due_date) BETWEEN 31 AND 60 THEN far.remaining_amount ELSE 0 END) AS bucket_60,
                                          SUM(CASE WHEN far.status = 'overdue' AND DATEDIFF(CURDATE(), far.due_date) BETWEEN 61 AND 90 THEN far.remaining_amount ELSE 0 END) AS bucket_90,
                                          SUM(CASE WHEN far.status = 'overdue' AND DATEDIFF(CURDATE(), far.due_date) > 90 THEN far.remaining_amount ELSE 0 END) AS bucket_90_plus
                                        FROM financial_accounts_receivable far
                                        WHERE far.deleted_at IS NULL {$where}", $params);

        $dre = $this->fetchAll($pdo, "SELECT
                                        COALESCE(SUM((fr.amount_received + fr.interest_amount + fr.fine_amount) - fr.discount_amount),0) AS gross_revenue,
                                        COALESCE(SUM(fr.discount_amount),0) AS discounts,
                                        COALESCE(SUM(fr.interest_amount + fr.fine_amount),0) AS financial_result
                                      FROM financial_receipts fr
                                      INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                                      WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$where}", $params);

        return [
            'receivables' => $summary,
            'delinquency' => $delinquency,
            'projected_cashflow' => $cashflow,
            'receipts_by_period' => $receiptsByPeriod,
            'receipts_by_client' => $receiptsByClient,
            'project_performance' => $projectPerf,
            'aging_list' => $aging[0] ?? [],
            'dre_simplified' => $dre[0] ?? [],
        ];
    }

    private function filters(int $companyId, array $filters): array
    {
        $where = [' AND far.company_id = :company_id'];
        $params = [':company_id' => $companyId];
        foreach (['client_id', 'project_id'] as $field) {
            $v = (int) ($filters[$field] ?? 0);
            if ($v > 0) {
                $where[] = ' AND far.' . $field . ' = :' . $field;
                $params[':' . $field] = $v;
            }
        }
        $from = trim((string) ($filters['from'] ?? ''));
        if ($from !== '') {
            $where[] = ' AND far.due_date >= :from';
            $params[':from'] = $from;
        }
        $to = trim((string) ($filters['to'] ?? ''));
        if ($to !== '') {
            $where[] = ' AND far.due_date <= :to';
            $params[':to'] = $to;
        }
        return [implode('', $where), $params];
    }

    private function fetchAll(\PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
