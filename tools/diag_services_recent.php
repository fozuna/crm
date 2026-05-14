<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

try {
    $pdo = DB::pdo();
    $rows = $pdo->query('SELECT id, name, default_price, active, is_bonus, created_at, updated_at FROM services ORDER BY id DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

