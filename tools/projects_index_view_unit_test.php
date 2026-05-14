<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\View;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/gestor/projetos';
    $_SERVER['SCRIPT_NAME'] = '/gestor/index.php';

    ob_start();
    View::render('projects/index', [
        'base' => '/gestor',
        'filters' => [
            'status' => 'ativo',
            'workflow_phase' => 'execucao',
            'owner_user_id' => 2,
        ],
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ],
        'projects' => [[
            'id' => 10,
            'title' => '<script>alert(1)</script>',
            'client_name' => 'ACME',
            'status' => 'ativo',
            'workflow_phase' => 'execucao',
            'progress_percent' => 12.3456,
            'total' => 123.45,
        ]],
    ], null);
    $html = (string) ob_get_clean();

    if (strpos($html, '<script>') !== false) {
        fail('XSS: HTML contém tag <script> não escapada');
    }
    if (strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') === false) {
        fail('Escape: título não foi escapado como esperado');
    }
    if (substr_count($html, 'selected') < 3) {
        fail('Filtro: opções selecionadas esperadas não foram renderizadas');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}

