<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinanceReportRepository
{
    public function summary(int $monthsForward = 6): array
    {
        $pdo = DB::pdo();
        $monthsForward = max(1, min(24, $monthsForward));
        $start = date('Y-m-01');
        $end = date('Y-m-t', strtotime('+' . $monthsForward . ' months'));

        $stmt = $pdo->prepare("SELECT DATE_FORMAT(due_date, '%Y-%m-01') AS bucket, SUM(GREATEST(amount - paid_amount,0)) AS open_amount
                              FROM finance_installments
                              WHERE status IN ('pendente','reaberto') AND due_date BETWEEN :start AND :end
                              GROUP BY bucket
                              ORDER BY bucket ASC");
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->execute();
        $projected = $stmt->fetchAll();

        $today = date('Y-m-d');
        $inad = $pdo->prepare("SELECT COUNT(*) AS count, SUM(GREATEST(amount - paid_amount,0)) AS total
                               FROM finance_installments
                               WHERE status IN ('pendente','reaberto') AND due_date < :today");
        $inad->bindValue(':today', $today);
        $inad->execute();
        $row = $inad->fetch();
        $row = is_array($row) ? $row : [];

        return [
            'projected_cashflow' => $projected,
            'delinquency' => [
                'count' => (int) ($row['count'] ?? 0),
                'total' => (float) ($row['total'] ?? 0),
            ],
        ];
    }
}

