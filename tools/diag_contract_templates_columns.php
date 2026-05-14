<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

$pdo = App\Core\DB::pdo();
$stmt = $pdo->query('SHOW COLUMNS FROM contract_templates');
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
