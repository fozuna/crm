<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Core\Request;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

$failures = 0;
$assert = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL- {$message}\n";
};

/**
 * Regressão da sprint "landing page institucional": /login é a porta de entrada do
 * sistema (visitante -> landing -> login -> dashboard), reaproveitando o AuthController
 * e o CSRF já existentes. Este teste dispara a rota real via Router::dispatch (mesma
 * cadeia que o Apache/PHP built-in server executa) e garante duas coisas ao mesmo tempo:
 * 1) o conteúdo institucional real continua presente (módulos, SEO, formulário);
 * 2) nenhum placeholder fictício (preço inventado, depoimento, "espaço reservado")
 *    volta a ser introduzido em uma alteração futura da view.
 *
 * Nota: GET '/' não é testado aqui porque, para um visitante não autenticado,
 * AuthMiddleware chama Response::redirect(), que encerra o processo com exit() —
 * o mesmo motivo pelo qual finance_report_controller.php não dispara as rotas de
 * exportação PDF/Excel neste processo. O redirecionamento '/' -> '/login' foi
 * validado manualmente (navegador) durante esta sprint.
 */

function runLandingRequest(string $path): string
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/gestor' . $path;
    $_SERVER['SCRIPT_NAME'] = '/gestor/index.php';
    $_GET = [];
    $_POST = [];

    $router = new Router();
    $routes = require __DIR__ . '/../config/routes.php';
    $routes($router, [
        'auth' => new AuthMiddleware(),
        'admin' => new AdminMiddleware(),
        'csrf' => new CsrfMiddleware(),
    ]);

    $request = new Request();
    ob_start();
    $router->dispatch($request);
    return (string) ob_get_clean();
}

unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['is_admin'], $_SESSION['company_id']);

$html = runLandingRequest('/login');

$assert(str_contains($html, '<form method="post" action='), 'Landing renderiza o formulário de login real');
$assert(str_contains($html, 'name="_csrf"'), 'Formulário de login inclui token CSRF');
$assert(str_contains($html, 'Entrar'), 'Landing expõe ao menos um call-to-action "Entrar"');

foreach (['Clientes', 'Propostas', 'Contratos', 'Projetos', 'Financeiro', 'Ordens de Serviço', 'Auditoria', 'Relatórios'] as $module) {
    $assert(str_contains($html, $module), "Landing lista o módulo real \"{$module}\"");
}

$assert(str_contains($html, '<link rel="canonical"'), 'Landing possui URL canônica');
$assert(str_contains($html, 'application/ld+json'), 'Landing possui schema.org (JSON-LD)');
$assert(str_contains($html, 'og:title'), 'Landing possui Open Graph');
$assert(str_contains($html, 'twitter:card'), 'Landing possui Twitter Card');
$assert(str_contains($html, 'skip-link'), 'Landing possui skip-link de acessibilidade');
$assert(str_contains($html, 'prefers-reduced-motion'), 'Landing respeita prefers-reduced-motion');

foreach (['ESPAÇO RESERVADO', 'R$ 97', 'R$ 247', 'Sob consulta', 'depoimento', 'Teste grátis', 'sem cartão de crédito'] as $forbidden) {
    $assert(!str_contains($html, $forbidden), "Landing não contém conteúdo fictício/placeholder (\"{$forbidden}\")");
}

return $failures;
