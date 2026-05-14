<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\FinanceRevenueRepository;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

try {
    $repo = new FinanceRevenueRepository();

    [$from, $to] = $repo->effectiveRange('', '');
    $expectedFrom = '';
    $expectedTo = '';

    if ($from !== $expectedFrom) {
        fail('effectiveRange("") from esperado ' . $expectedFrom . ' recebido ' . $from);
    }
    if ($to !== $expectedTo) {
        fail('effectiveRange("") to esperado ' . $expectedTo . ' recebido ' . $to);
    }

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}
