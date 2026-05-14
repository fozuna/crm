<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$table = (string) ($argv[1] ?? '');
if ($table === '') {
    fwrite(STDERR, "Uso: php gestor/tools/diag_columns.php <tabela>\n");
    exit(1);
}

$pdo = DB::pdo();
$st = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
$cols = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
foreach ($cols as $c) {
    echo ($c['Field'] ?? ''), "\t", ($c['Type'] ?? ''), "\t", ($c['Null'] ?? ''), "\t", ($c['Default'] ?? ''), "\n";
}

