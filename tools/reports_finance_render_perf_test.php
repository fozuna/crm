<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ReportController;
use App\Core\Request;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/gestor/relatorios/financeiro';
    $_SERVER['SCRIPT_NAME'] = '/gestor/index.php';
    $_GET = [];
    $_POST = [];

    $t0 = microtime(true);
    ob_start();
    (new ReportController())->finance(new Request());
    ob_end_clean();
    $ms = (microtime(true) - $t0) * 1000;

    echo "OK render_ms=" . number_format($ms, 2, '.', '') . "\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}

