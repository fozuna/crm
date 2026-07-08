<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Core\DB;
use App\Services\DbUpgradeRunner;
use App\Services\SqlScriptParser;

$failures = 0;
$assert = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL- {$message}\n";
};

$schemaPath = __DIR__ . '/../database/schema.sql';
$upgradePath = __DIR__ . '/../database/upgrade.sql';
$schemaSql = @file_get_contents($schemaPath);
$upgradeSql = @file_get_contents($upgradePath);
$runner = new DbUpgradeRunner();
$parser = new SqlScriptParser();

$assert(is_string($schemaSql) && trim($schemaSql) !== '', 'schema.sql disponível para sincronização');
$assert(is_string($upgradeSql) && trim($upgradeSql) !== '', 'upgrade.sql disponível para sincronização');

if (is_string($schemaSql) && trim($schemaSql) !== '') {
    $schemaStatements = $parser->split($schemaSql);
    $assert(count($schemaStatements) > 0, 'Parser extrai statements do schema');
    $assert(count(array_filter($schemaStatements, static fn(string $sql): bool => stripos($sql, 'CREATE TRIGGER tr_servicos_avulsos_aprovacao_eventos_no_update') !== false)) === 1, 'Parser preserva trigger do schema');
}

if (is_string($upgradeSql) && trim($upgradeSql) !== '') {
    $upgradeStatements = $parser->split($upgradeSql);
    $assert(count($upgradeStatements) > 0, 'Parser extrai statements do upgrade');
    $assert(count(array_filter($upgradeStatements, static fn(string $sql): bool => stripos($sql, 'CREATE TRIGGER tr_servicos_avulsos_aprovacao_notificacoes_no_delete') !== false)) === 1, 'Parser preserva trigger do upgrade');
}

$ddl = (string) $schemaSql . "\n" . (string) $upgradeSql;
foreach ($runner->requiredTables() as $table) {
    $patternCreate = '/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?' . preg_quote($table, '/') . '`?/i';
    $patternAlter = '/ALTER\s+TABLE\s+`?' . preg_quote($table, '/') . '`?/i';
    $assert(
        preg_match($patternCreate, $ddl) === 1 || preg_match($patternAlter, (string) $upgradeSql) === 1,
        'DDL cobre a tabela obrigatória ' . $table
    );
}

try {
    $inspect = $runner->inspect(DB::pdo());
    $assert(($inspect['pending'] ?? true) === false, 'Banco conectado está sincronizado com a versão atual do código');
} catch (\Throwable $e) {
    $failures++;
    echo 'FAIL- Validação runtime da estrutura do banco falhou: ' . $e->getMessage() . "\n";
}

return $failures;
