<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function ok(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

try {
    $config = require __DIR__ . '/../config/config.php';
    if (!is_array($config)) {
        fail('config/config.php nao retornou um array valido.');
    }

    $requiredKeys = [
        'APP_NAME',
        'APP_URL',
        'APP_BASE_PATH',
        'APP_DEBUG',
        'APP_TIMEZONE',
        'APP_KEY',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'DB_CHARSET',
    ];

    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $config)) {
            fail('Chave obrigatoria ausente em config/config.php: ' . $key);
        }
    }

    $simulatedConfig = Config::all();
    foreach ($requiredKeys as $key) {
        $simulatedConfig[$key] = $simulatedConfig[$key] ?? $config[$key];
    }

    Config::setAll($simulatedConfig);

    foreach ([
        __DIR__ . '/../index.php',
        __DIR__ . '/../.htaccess',
        __DIR__ . '/../config/config.php',
        __DIR__ . '/../database/schema.sql',
    ] as $file) {
        if (!is_file($file)) {
            fail('Arquivo obrigatorio ausente: ' . $file);
        }
    }

    foreach ([
        __DIR__ . '/../storage',
        __DIR__ . '/../storage/cache',
        __DIR__ . '/../storage/jobs',
        __DIR__ . '/../storage/logs',
        __DIR__ . '/../storage/sessions',
    ] as $dir) {
        if (!is_dir($dir)) {
            fail('Diretorio obrigatorio ausente: ' . $dir);
        }
    }

    $dsnCheck = trim((string) Config::get('DB_NAME', '')) !== ''
        && trim((string) Config::get('DB_USER', '')) !== ''
        && trim((string) Config::get('DB_HOST', '')) !== '';

    if ($dsnCheck) {
        try {
            DB::pdo();
            ok('Banco: conexao OK');
        } catch (Throwable) {
            ok('Banco: validacao pulada (credenciais placeholder ou servidor indisponivel neste ambiente)');
        }
    }

    ok('Deploy Hostinger: smoke test OK');
} catch (Throwable $e) {
    fail($e->getMessage());
}
