<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\FinanceRevenueRepository;

function out(string $label, $value): void
{
    echo str_pad($label, 36) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
}

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

try {
    $pdo = DB::pdo();
    $dbTotal = (int) $pdo->query('SELECT COUNT(*) FROM finance_installments')->fetchColumn();

    $repo = new FinanceRevenueRepository();
    $res = $repo->listInstallments(['from' => '', 'to' => '', 'project_id' => 0, 'client_id' => 0, 'status' => '', 'sort' => 'due_date', 'direction' => 'asc'], 1, 30);
    $reportTotal = (int) ($res['total'] ?? 0);

    out('finance_installments (db_total)', $dbTotal);
    out('report_query (total)', $reportTotal);
    out('report_query (page_rows)', is_array($res['rows'] ?? null) ? count($res['rows']) : 0);

    if ($reportTotal !== $dbTotal) {
        fail('Divergência: total do relatório não bate com DB');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}

