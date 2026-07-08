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
use App\Services\DbUpgradeRunner;

$environment = 'production';
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--env=(development|homolog|production)$/', (string) $arg, $matches) === 1) {
        $environment = (string) $matches[1];
    }
}

$logger = new DbLifecycleLogger();
$logger->write('deploy_preflight_start', ['environment' => $environment]);

try {
    $sync = (new DbSyncRunner(logger: $logger))->run(DB::pdo(), [
        'environment' => $environment,
    ]);

    $inspect = (new DbUpgradeRunner())->inspect(DB::pdo());
    if ($inspect['pending'] ?? true) {
        throw new RuntimeException('Ainda existem pendências estruturais após a sincronização.');
    }

    $testFailures = (int) require __DIR__ . '/../tests/database_structure.php';
    if ($testFailures > 0) {
        throw new RuntimeException('Os testes de validação estrutural do banco falharam.');
    }

    $logger->write('deploy_preflight_finish', [
        'environment' => $environment,
        'sync' => $sync,
        'inspect' => $inspect,
        'test_failures' => $testFailures,
    ]);

    echo "Preflight de deploy concluído com sucesso.\n";
    echo '- Ambiente: ' . $environment . "\n";
    echo '- Pendências estruturais: 0' . "\n";
    echo '- Testes estruturais: OK' . "\n";
    echo '- Log: ' . $logger->path() . "\n";
    exit(0);
} catch (Throwable $e) {
    $logger->write('deploy_preflight_error', [
        'environment' => $environment,
    ], $e);
    fwrite(STDERR, "Falha no preflight de deploy: {$e->getMessage()}\n");
    fwrite(STDERR, 'Consulte o log em: ' . $logger->path() . "\n");
    exit(1);
}
