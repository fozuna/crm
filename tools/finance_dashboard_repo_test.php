<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\FinanceDashboardRepository;

try {
    $repo = new FinanceDashboardRepository();
    $data = $repo->dashboard([
        'from' => date('Y-m-01'),
        'to' => date('Y-m-t'),
        'client_id' => 0,
        'status' => '',
    ]);

    if (!is_array($data['kpis'] ?? null)) {
        throw new RuntimeException('KPIs ausentes.');
    }
    if (!is_array($data['series']['months'] ?? null)) {
        throw new RuntimeException('Séries ausentes.');
    }
    if (count($data['series']['months']) !== count($data['series']['revenue'])) {
        throw new RuntimeException('Tamanho de séries inconsistente (revenue).');
    }
    if (count($data['series']['months']) !== count($data['series']['cashflow'])) {
        throw new RuntimeException('Tamanho de séries inconsistente (cashflow).');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

