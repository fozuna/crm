<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\FinanceRevenueRepository;

try {
    $repo = new FinanceRevenueRepository();
    $res = $repo->metrics(['client_id' => (int) ($argv[1] ?? 0)]);
    var_export($res);
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

