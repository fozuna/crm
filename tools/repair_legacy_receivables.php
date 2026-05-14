<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\FinancialReceivableService;

try {
    $pdo = DB::pdo();
    $service = new FinancialReceivableService();

    $sql = "SELECT DISTINCT far.project_id, pr.proposal_id
            FROM financial_accounts_receivable far
            INNER JOIN projects pr ON pr.id = far.project_id
            WHERE far.source_installment_id IS NOT NULL
              AND far.deleted_at IS NULL
            GROUP BY far.project_id, pr.proposal_id
            HAVING COUNT(*) <> COUNT(DISTINCT far.source_installment_id)
            ORDER BY pr.proposal_id ASC";
    $targets = $pdo->query($sql)->fetchAll();

    $summary = [];
    foreach ($targets as $target) {
        $projectId = (int) ($target['project_id'] ?? 0);
        if ($projectId <= 0) {
            continue;
        }

        $result = $service->repairProjectReceivables($projectId, 1, '127.0.0.1');
        $summary[] = [
            'proposal_id' => (int) ($target['proposal_id'] ?? 0),
            'project_id' => $projectId,
            'result' => $result,
        ];
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
