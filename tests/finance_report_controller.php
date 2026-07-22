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
use App\Repositories\FinancialEnterpriseDashboardRepository;

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
 * Regressão do bug real que mantinha /relatorios/financeiro divergente do Dashboard
 * Financeiro mesmo depois de FinancialReceivableRepository/FinancialReceiptRepository
 * já estarem corretos: View::render(string $view, array $data = [], ...) usa $data
 * como nome do próprio parâmetro, e ReportController::finance() empacotava o
 * view-model dentro de uma chave também chamada 'data'. extract($data, EXTR_SKIP)
 * não sobrescreve uma variável já existente no escopo — então a chave 'data' era
 * descartada silenciosamente e a view sempre recebia o array de parâmetros externo
 * (sem 'totals'/'installments'), renderizando tudo zerado. Os testes de repositório
 * (finance_report_repository.php) não pegavam isso por chamarem o repositório
 * diretamente, sem passar por Controller -> View::render. Este teste dispara a rota
 * real via Router::dispatch (mesma cadeia que Apache executa) e inspeciona o HTML
 * renderizado, não apenas o retorno do repositório.
 */

function runReportRequest(string $path, string $query = ''): string
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/gestor' . $path . ($query !== '' ? ('?' . $query) : '');
    $_SERVER['SCRIPT_NAME'] = '/gestor/index.php';
    parse_str($query, $_GET);
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
    $companyId = (int) ($pdo->query('SELECT id FROM company_profile ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    $assert($companyId > 0 && $clientId > 0, 'Pré-requisitos de fixture disponíveis (company_profile e clients)');
    $_SESSION['company_id'] = $companyId;

    $marker = 'TESTE_CTRL_' . bin2hex(random_bytes(4));
    $due = '2026-03-12';
    $from = '2026-01-01';
    $to = '2026-07-17';

    $insertReceivable = $pdo->prepare(
        'INSERT INTO financial_accounts_receivable (
            company_id, project_id, client_id, contract_id, source_installment_id,
            installment_number, total_installments, title, original_amount, discount_amount,
            interest_amount, fine_amount, received_amount, remaining_amount, due_date,
            status, created_at, updated_at
        ) VALUES (
            :company_id, NULL, :client_id, NULL, NULL,
            1, 1, :title, :original_amount, 0, 0, 0, 0, :remaining_amount, :due_date,
            :status, NOW(), NOW()
        )'
    );
    $insertReceivable->execute([
        ':company_id' => $companyId,
        ':client_id' => $clientId,
        ':title' => $marker,
        ':original_amount' => 842.00,
        ':remaining_amount' => 842.00,
        ':due_date' => $due,
        ':status' => 'overdue',
    ]);
    $receivableId = (int) $pdo->lastInsertId();

    $query = 'from=' . $from . '&to=' . $to . '&client_id=' . $clientId;

    // --- Rota real do relatório: o HTML renderizado precisa refletir o total real ---
    $reportHtml = runReportRequest('/relatorios/financeiro', $query);
    $assert(!str_contains($reportHtml, 'Sem títulos para os filtros informados.'), 'Relatório real (via rota/Controller/View) não mostra "sem títulos" quando existem títulos no período');
    $assert(str_contains($reportHtml, (string) $receivableId), 'Relatório real (via rota) lista o ID do título recém-criado');

    $recRepo = new \App\Repositories\FinancialReceivableRepository();
    $expectedTotals = $recRepo->totals($companyId, ['client_id' => $clientId, 'project_id' => 0, 'status' => '', 'due_from' => $from, 'due_to' => $to, 'sort' => 'due_date', 'direction' => 'asc']);
    $expectedReceivableFormatted = number_format((float) $expectedTotals['receivable'], 2, ',', '.');
    $assert(str_contains($reportHtml, $expectedReceivableFormatted), "Relatório real exibe o total a receber correto no HTML (R$ {$expectedReceivableFormatted})");

    // --- Rota real do dashboard, mesmo período: deve bater com o relatório ---
    $dashboardHtml = runReportRequest('/financeiro/dashboard', $query);
    $assert(str_contains($dashboardHtml, $expectedReceivableFormatted), 'Dashboard real (via rota) exibe o mesmo total a receber que o Relatório real para o mesmo período/cliente');

    $dashboardRepo = new FinancialEnterpriseDashboardRepository();
    $dashboardData = $dashboardRepo->data($companyId, ['client_id' => $clientId, 'from' => $from, 'to' => $to]);
    $assert(
        abs((float) $dashboardData['totals']['total_receivable'] - (float) $expectedTotals['receivable']) < 0.001,
        'Dashboard e Relatório usam exatamente a mesma fonte/regra de cálculo para "a receber" (mesmo filtro)'
    );

    // Nota: financeExportPdf()/financeExportExcel() encerram o processo com exit()
    // após escrever os bytes (padrão de download direto), o que impediria os demais
    // testes de tests/run.php de rodar caso fossem chamados neste mesmo processo.
    // Ambos foram validados manualmente via CLI (dispatch real do Controller, PHP do
    // Apache do Laragon) durante esta sprint: PDF retornou bytes válidos (%PDF-1.4,
    // 3103 bytes) para from=2026-01-01&to=2026-07-17; Excel falhou por extensão
    // ZipArchive desabilitada neste ambiente local (achado registrado à parte,
    // não relacionado à divergência Dashboard x Relatório).
} finally {
    $pdo->rollBack();
}

return $failures;
