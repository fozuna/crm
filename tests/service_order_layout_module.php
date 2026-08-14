<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Core\DB;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\ServiceOrderApprovalService;

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
 * Regressão de dois defeitos reais de produção (storage/logs/app.log, 2026-08-14):
 *
 * 1) TypeError: Cannot access offset of type string on string em
 *    resources/views/service_orders/form.php:427 — $approvalBadge era um array na
 *    definição (linha 41), reatribuído para string dentro do card "Aprovação digital
 *    do cliente" (linha ~362) e depois lido de novo como array por um SEGUNDO card
 *    "Aprovação digital" duplicado/morto (introduzido pelo commit 2eacb3e em
 *    2026-07-08, nunca removido — não é regressão desta sprint). A exceção interrompe
 *    o require do view file DENTRO do ob_start() de View::render(), então o layout
 *    (sidebar/Tailwind) nunca chega a ser aplicado — só o HTML parcial já ecoado antes
 *    do crash aparece na tela, sem nenhum CSS, com ícones SVG sem width/height caindo
 *    no tamanho intrínseco gigante do navegador. Só acontecia para OS com um link de
 *    aprovação já gerado (approvalSummary !== null), por isso nunca foi percebido
 *    antes de existir uma OS faturada com aprovação pendente em produção.
 *
 * 2) ServiceOrderController::show() sempre redirecionava direto para edit() (existe
 *    desde o commit aa221ea, 2026-07-01) — nunca existiu uma tela de detalhes de
 *    verdade, então "Visualizar" na listagem sempre abria "Editar".
 *
 * Dispara as rotas reais via Router::dispatch() (mesma cadeia que o Apache executa)
 * reproduzindo o cenário exato de produção: OS faturável, com recebível vinculado e
 * um link de aprovação real gerado.
 */

function runOsRequest(string $path): string
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

$pdo = DB::pdo();
$pdo->beginTransaction();

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['is_admin'] = 1;

try {
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId <= 0) {
        $pdo->exec("INSERT INTO clients (name, created_at) VALUES ('Cliente Teste Layout', NOW())");
        $clientId = (int) $pdo->lastInsertId();
    }
    $_SESSION['company_id'] = (int) ($pdo->query('SELECT id FROM company_profile ORDER BY id LIMIT 1')->fetchColumn() ?: 1);

    $marker = 'TESTE_OS_LAYOUT_' . bin2hex(random_bytes(4));
    $seq = (int) ($pdo->query('SELECT COALESCE(MAX(numero_sequencial), 0) FROM servicos_avulsos')->fetchColumn()) + 1;
    $numeroOs = 'OS-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);

    $insertReceivable = $pdo->prepare(
        'INSERT INTO financial_accounts_receivable (
            company_id, client_id, title, original_amount, discount_amount, interest_amount,
            fine_amount, received_amount, remaining_amount, due_date, status, created_at, updated_at
        ) VALUES (
            :company_id, :client_id, :title, 150, 10, 0, 0, 0, 140, CURDATE(), :status, NOW(), NOW()
        )'
    );
    $insertReceivable->execute([
        ':company_id' => $_SESSION['company_id'],
        ':client_id' => $clientId,
        ':title' => $marker . ' - recebível',
        ':status' => 'pending',
    ]);
    $receivableId = (int) $pdo->lastInsertId();

    $insertOrder = $pdo->prepare(
        'INSERT INTO servicos_avulsos (
            numero_sequencial, numero_os, service_name, client_id, type, status,
            opened_at, completed_at, billable, base_amount, discount_amount, surcharge_amount,
            final_amount, financial_receivable_id, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :seq, :numero, :service_name, :client_id, "suporte", "faturado",
            NOW(), NOW(), 1, 150, 10, 0, 140, :receivable_id, 1, 1, NOW(), NOW()
        )'
    );
    $insertOrder->execute([
        ':seq' => $seq,
        ':numero' => $numeroOs,
        ':service_name' => $marker . ' - serviço',
        ':client_id' => $clientId,
        ':receivable_id' => $receivableId,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    (new ServiceOrderApprovalService())->generateForServiceOrder($orderId, 1);

    // --- Problema 1: edição de OS faturada com aprovação gerada não pode mais quebrar o layout ---
    $editHtml = runOsRequest('/ordens-servico/' . $orderId . '/editar');
    $assert(str_contains($editHtml, '</html>'), 'Edição de OS faturada com aprovação gerada renderiza a página completa (com layout)');
    $assert(str_contains($editHtml, 'cdn.tailwindcss.com'), 'Tailwind é carregado (a view não escapou do wrapper de layout)');
    $assert(!str_contains($editHtml, 'Erro interno'), 'Edição não dispara mais o TypeError de $approvalBadge (form.php:427)');
    $assert(!str_contains($editHtml, 'Cannot access offset'), 'Nenhum TypeError de acesso a offset vaza para a resposta');
    $assert(substr_count($editHtml, 'Aprovação digital') === 1, 'Existe apenas um card de Aprovação digital (o duplicado/morto foi removido)');
    $assert(str_contains($editHtml, $numeroOs), 'Edição mostra o número da OS');

    // --- Problema 2: Visualizar deve abrir uma tela de detalhes de verdade, não Editar ---
    $showHtml = runOsRequest('/ordens-servico/' . $orderId);
    $assert(str_contains($showHtml, '</html>'), 'Visualização renderiza a página completa (com layout)');
    $assert(str_contains($showHtml, $numeroOs), 'Visualização mostra o número da OS');
    $assert(!str_contains($showHtml, 'Cadastro independente para demandas pontuais'), 'Visualizar não reaproveita mais o formulário de edição (show() deixou de redirecionar para edit())');
    $assert(!str_contains($showHtml, 'id="serviceOrderForm"'), 'Tela de visualização não contém o formulário editável de edição da OS');
    $assert(str_contains($showHtml, 'Financeiro'), 'Visualização exibe a seção Financeiro');
    $assert(str_contains($showHtml, 'Histórico'), 'Visualização exibe a seção Histórico');
    $assert(str_contains($showHtml, 'Anexos'), 'Visualização exibe a seção Anexos');

    // --- OS inexistente: tratamento adequado em ambas as rotas, sem crash ---
    $missingShow = runOsRequest('/ordens-servico/999999999');
    $assert(str_contains($missingShow, 'não encontrada'), 'Visualizar OS inexistente retorna tratamento adequado');
    $missingEdit = runOsRequest('/ordens-servico/999999999/editar');
    $assert(str_contains($missingEdit, 'não encontrada'), 'Editar OS inexistente retorna tratamento adequado');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

return $failures;
