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
    $_SERVER['REQUEST_URI'] = '/gestor/relatorios/financeiro?ins_page=2';
    $_SERVER['SCRIPT_NAME'] = '/gestor/index.php';
    $_GET = ['ins_page' => '2'];
    $_POST = [];

    $req = new Request();
    $got = $req->input('ins_page', 1);
    if ((string) $got !== '2') {
        fail('Request::input(ins_page) esperado 2 recebido ' . var_export($got, true));
    }

    ob_start();
    (new ReportController())->finance($req);
    $html = (string) ob_get_clean();

    if (strpos($html, 'border-t bg-slate-50 text-sm') === false) {
        $anchor = strpos($html, 'Parcelas do relatório');
        $anchor = $anchor !== false ? $anchor : 0;
        fail('Paginação: bloco de paginação não encontrado. Trecho: ' . substr($html, $anchor, 600));
    }

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}
