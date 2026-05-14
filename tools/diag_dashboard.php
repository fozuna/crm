<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\DashboardRepository;

try {
    $stats = (new DashboardRepository())->stats();
    var_export($stats);
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

