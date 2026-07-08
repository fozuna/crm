<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;
use App\Services\DatabaseStructureOutOfSyncException;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = Config::load(__DIR__ . '/../.env', __DIR__ . '/../config/config.php');
Config::setAll($config);

if (function_exists('opcache_invalidate')) {
    $targets = [
        __DIR__ . '/Controllers/ReportController.php',
        __DIR__ . '/Repositories/FinanceRevenueRepository.php',
        __DIR__ . '/Core/View.php',
        __DIR__ . '/../resources/views/reports/finance.php',
    ];
    foreach ($targets as $t) {
        if (is_file($t)) {
            @opcache_invalidate($t, true);
        }
    }
}

date_default_timezone_set(Config::get('APP_TIMEZONE', 'UTC'));

foreach ([
    __DIR__ . '/../storage/cache',
    __DIR__ . '/../storage/jobs',
    __DIR__ . '/../storage/logs',
    __DIR__ . '/../storage/pdfs/contracts',
    __DIR__ . '/../storage/pdfs/proposals',
    __DIR__ . '/../storage/pdfs/service_orders/approvals',
    __DIR__ . '/../storage/sessions',
    __DIR__ . '/../storage/uploads/clients',
    __DIR__ . '/../storage/uploads/company_profile',
    __DIR__ . '/../storage/uploads/company_profile/branding',
] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

Session::start();

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$logDir = __DIR__ . '/../storage/logs';

$logException = static function (Throwable $e, string $id) use ($logDir): void {
    $when = date('Y-m-d H:i:s');
    $uri = (string) ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . (string) ($_SERVER['REQUEST_URI'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $userId = (string) Session::get('user_id', '');
    $line = "[$when] [$id] [$ip] [$userId] [$uri]\n" . (string) $e . "\n\n";
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
};

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function () use ($logException): void {
    $err = error_get_last();
    if (!is_array($err) || !isset($err['type'], $err['message'], $err['file'], $err['line'])) {
        return;
    }
    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array((int) $err['type'], $fatal, true)) {
        return;
    }
    $id = bin2hex(random_bytes(6));
    $e = new ErrorException((string) $err['message'], 0, (int) $err['type'], (string) $err['file'], (int) $err['line']);
    $logException($e, $id);
});

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    $debug = (bool) Config::get('APP_DEBUG', false);
    $id = bin2hex(random_bytes(6));
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $when = date('Y-m-d H:i:s');
    $uri = (string) ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . (string) ($_SERVER['REQUEST_URI'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $userId = (string) Session::get('user_id', '');
    $line = "[$when] [$id] [$ip] [$userId] [$uri]\n" . (string) $e . "\n\n";
    $wrote = false;
    if (is_dir($logDir)) {
        $wrote = @file_put_contents($logDir . '/app.log', $line, FILE_APPEND) !== false;
    }
    if (!$wrote) {
        @error_log($line);
    }
    if ($debug) {
        echo '<pre style="white-space: pre-wrap;">' . htmlspecialchars((string) $e) . '</pre>';
        return;
    }

    if ($e instanceof DatabaseStructureOutOfSyncException) {
        echo htmlspecialchars($e->getMessage()) . ' Execute o sincronizador oficial (`php tools/db_sync.php --env=development`) antes de liberar o acesso. Ref: ' . htmlspecialchars($e->referenceId());
        return;
    }

    $hint = null;
    if ($e instanceof \PDOException || $e->getPrevious() instanceof \PDOException) {
        $m = (string) ($e instanceof \PDOException ? $e->getMessage() : (string) $e->getPrevious()?->getMessage());
        if (stripos($m, 'SQLSTATE[42S02]') !== false || stripos($m, 'Base table or view not found') !== false) {
            $hint = 'Banco não inicializado ou estrutura incompleta. Importe o database/schema.sql e execute o upgrade.';
        } elseif (stripos($m, 'SQLSTATE[HY000] [1045]') !== false || stripos($m, 'Access denied') !== false) {
            $hint = 'Falha de autenticação no banco. Verifique DB_USER/DB_PASS e permissões.';
        } elseif (stripos($m, 'SQLSTATE[HY000] [2002]') !== false || stripos($m, 'Connection refused') !== false) {
            $hint = 'Falha de conexão com o banco. Verifique DB_HOST/porta e liberação no firewall.';
        } elseif (stripos($m, 'Unknown database') !== false || stripos($m, 'SQLSTATE[HY000] [1049]') !== false) {
            $hint = 'Banco inexistente. Verifique DB_NAME e se o banco foi criado.';
        }
    }

    if (is_string($hint) && $hint !== '') {
        echo htmlspecialchars($hint) . ' Ref: ' . htmlspecialchars($id);
        return;
    }

    echo 'Erro interno. Ref: ' . htmlspecialchars($id);
});
