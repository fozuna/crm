<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\DbUpgradeRunner;

try {
    $runner = new DbUpgradeRunner();
    $res = $runner->run(DB::pdo());
    echo "OK\n";
    echo 'Applied: ' . (int) ($res['applied'] ?? 0) . "\n";
    echo 'Skipped: ' . (int) ($res['skipped'] ?? 0) . "\n";
    echo 'Ensured columns (added): ' . (int) ($res['ensured_added'] ?? 0) . "\n";
    echo 'Ensured columns (skipped): ' . (int) ($res['ensured_skipped'] ?? 0) . "\n";
    echo 'Adjusted schema (applied): ' . (int) ($res['adjusted_applied'] ?? 0) . "\n";
    echo 'Adjusted schema (skipped): ' . (int) ($res['adjusted_skipped'] ?? 0) . "\n";
    if (!($res['ok'] ?? false)) {
        $inspect = is_array($res['inspect'] ?? null) ? $res['inspect'] : [];
        $missingTables = is_array($inspect['missing_tables'] ?? null) ? $inspect['missing_tables'] : [];
        echo 'Missing tables: ' . implode(', ', $missingTables) . "\n";
        exit(2);
    }
    echo "All required tables present.\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}
