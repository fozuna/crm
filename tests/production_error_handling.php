<?php
declare(strict_types=1);

$failures = 0;

$assert = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }

    $failures++;
    echo "FAIL- {$message}\n";
};

$root = dirname(__DIR__);
$phpBinary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
$runtimeLog = $root . '/storage/logs/runtime-events.ndjson';
$previousLog = is_file($runtimeLog) ? (string) file_get_contents($runtimeLog) : '';

$tempScript = tempnam(sys_get_temp_dir(), 'crmtraxter-prod-500-');
if ($tempScript === false) {
    $assert(false, 'Cria script temporário para reproduzir erro de produção');
    return $failures;
}

$projectRoot = str_replace('\\', '/', $root);

$script = <<<'PHP'
<?php
declare(strict_types=1);

chdir('__PROJECT_ROOT__');
require '__PROJECT_ROOT__/app/bootstrap.php';

throw new App\Services\DatabaseStructureOutOfSyncException('ref123', ['pending' => true]);
PHP;

$script = str_replace('__PROJECT_ROOT__', $projectRoot, $script);
file_put_contents($tempScript, $script);

$command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($tempScript);
$output = [];
$exitCode = 1;
exec($command, $output, $exitCode);

$joinedOutput = implode("\n", $output);
$newLog = is_file($runtimeLog) ? (string) file_get_contents($runtimeLog) : '';
$deltaLog = substr($newLog, strlen($previousLog));

@unlink($tempScript);

$assert($exitCode === 0, 'Fluxo de erro de produção finaliza sem fatal error');
$assert(
    str_contains($joinedOutput, 'php tools/db_sync.php --env=production'),
    'Fluxo de erro orienta sincronizador com ambiente de produção'
);
$assert(
    str_contains($joinedOutput, 'Estrutura de banco desatualizada. Execute o sincronizador oficial antes de disponibilizar o sistema.'),
    'Fluxo de erro mantém mensagem de bloqueio estrutural'
);
$assert(
    str_contains($deltaLog, '"event":"exception_handler_invoked"'),
    'Fluxo de erro registra evento estruturado de handler'
);
$assert(
    str_contains($deltaLog, '"event":"db_structure_out_of_sync_rendered"'),
    'Fluxo de erro registra evento estruturado da mensagem de sincronização'
);

return $failures;
