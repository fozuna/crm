<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\InstallmentCharges;

try {
    $c1 = InstallmentCharges::compute(100.0, '2099-01-01', '2026-05-01');
    if ((int) ($c1['days_overdue'] ?? 0) !== 0) {
        throw new RuntimeException('Esperado dias_overdue=0 para parcela não vencida.');
    }
    if (abs((float) ($c1['total'] ?? 0) - 100.0) > 0.001) {
        throw new RuntimeException('Total inesperado para parcela não vencida.');
    }

    $c2 = InstallmentCharges::compute(200.0, '2026-04-01', '2026-05-01');
    if ((int) ($c2['days_overdue'] ?? 0) !== 30) {
        throw new RuntimeException('Dias em atraso incorreto.');
    }
    $penalty = (float) ($c2['penalty'] ?? 0);
    if (abs($penalty - 4.0) > 0.01) {
        throw new RuntimeException('Multa esperada 4.00.');
    }
    $interest = (float) ($c2['interest'] ?? 0);
    if ($interest <= 0) {
        throw new RuntimeException('Juros deveria ser > 0.');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

