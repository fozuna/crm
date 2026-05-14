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

    $expectedFrom = '';
    $expectedTo = '';

    ob_start();
    (new ReportController())->finance(new Request());
    $html = (string) ob_get_clean();

    if (strpos($html, 'name="from"') === false) {
        fail('Render: input "from" não encontrado');
    }
    if (strpos($html, 'value="' . $expectedFrom . '"') === false) {
        fail('Render: período default (from) esperado vazio não encontrado no HTML');
    }
    if (strpos($html, 'value="' . $expectedTo . '"') === false) {
        fail('Render: período default (to) esperado vazio não encontrado no HTML');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}
