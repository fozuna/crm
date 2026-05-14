<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

try {
    $pdo = DB::pdo();
    $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM proposals GROUP BY status ORDER BY c DESC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo (string) ($r['status'] ?? ''), ': ', (string) ($r['c'] ?? ''), "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

