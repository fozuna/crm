<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI apenas.\n";
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\DbLifecycleLogger;
use App\Services\DbSyncRunner;

$environment = 'development';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--env=(development|homolog|production|install|manual)$/', (string) $arg, $matches) === 1) {
        $environment = (string) $matches[1];
    }
}

$logger = new DbLifecycleLogger();
$runner = new DbSyncRunner(logger: $logger);

try {
    $result = $runner->run(DB::pdo(), [
        'environment' => $environment,
    ]);

    echo "Sincronização concluída com sucesso.\n";
    echo '- Ambiente: ' . $environment . "\n";
    echo '- Schema aplicado: ' . (int) ($result['schema']['applied'] ?? 0) . "\n";
    echo '- Schema ignorado: ' . (int) ($result['schema']['skipped'] ?? 0) . "\n";
    echo '- Upgrade aplicado: ' . (int) ($result['upgrade']['applied'] ?? 0) . "\n";
    echo '- Upgrade ignorado: ' . (int) ($result['upgrade']['skipped'] ?? 0) . "\n";
    echo '- Log: ' . $logger->path() . "\n";
    exit(0);
} catch (Throwable $e) {
    $logger->write('db_sync_cli_error', [
        'environment' => $environment,
    ], $e);
    fwrite(STDERR, "Falha na sincronização do banco: {$e->getMessage()}\n");
    fwrite(STDERR, 'Consulte o log em: ' . $logger->path() . "\n");
    exit(1);
}
