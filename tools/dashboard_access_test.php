<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\DashboardRepository;

try {
    $pdo = DB::pdo();
    $col = $pdo->query("SHOW COLUMNS FROM finance_installments LIKE 'paid_amount'")->fetchColumn();
    if (!$col) {
        throw new RuntimeException('Coluna finance_installments.paid_amount ausente. Rode apply_upgrade.php.');
    }

    $stats = (new DashboardRepository())->stats();
    foreach (['approved_proposals', 'active_projects', 'receivable'] as $k) {
        if (!array_key_exists($k, $stats)) {
            throw new RuntimeException('Chave ausente em stats: ' . $k);
        }
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

