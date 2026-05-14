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

    $projects = [];
    for ($i = 1; $i <= 500; $i++) {
        $projects[] = [
            'id' => $i,
            'title' => 'Projeto ' . $i,
            'client_name' => 'Cliente ' . $i,
            'status' => $i % 2 === 0 ? 'ativo' : 'pausado',
            'workflow_phase' => $i % 2 === 0 ? 'execucao' : 'planejamento',
            'progress_percent' => $i % 101,
            'total' => $i * 10.25,
        ];
    }

    $t0 = microtime(true);
    ob_start();
    View::render('projects/index', [
        'base' => '/gestor',
        'filters' => [],
        'users' => [],
        'projects' => $projects,
    ], null);
    $html = (string) ob_get_clean();
    $ms = (microtime(true) - $t0) * 1000;

    if (strpos($html, 'Projeto 500') === false) {
        fail('Render: conteúdo esperado não encontrado');
    }

    echo "OK render_ms=" . number_format($ms, 2, '.', '') . "\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}

