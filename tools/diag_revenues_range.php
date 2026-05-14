<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\FinanceRevenueRepository;

$from = (string) ($argv[1] ?? '');
$to = (string) ($argv[2] ?? '');

try {
    $repo = new FinanceRevenueRepository();
    $filters = ['from' => $from, 'to' => $to, 'project_id' => 0, 'client_id' => 0, 'status' => ''];
    $m = $repo->metrics($filters);
    $c = $repo->cashflowBuckets($filters, 6);
    $i = $repo->listInstallments($filters, 1, 50);
    echo "METRICS\n";
    var_export($m);
    echo "\n\nCASHFLOW\n";
    var_export($c);
    echo "\n\nINSTALLMENTS\n";
    var_export($i);
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

