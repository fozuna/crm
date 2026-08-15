<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Core\DB;
use App\Repositories\FinancialReceivableRepository;
use App\Repositories\ServiceOrderRepository;
use App\Services\CompanyContext;
use App\Services\FinancialReceivableService;
use App\Services\ServiceOrderBillingService;
use App\Services\ServiceOrderStatus;

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
 * Regra de negócio definitiva (SPRINT_OS_BILLING_AND_FLOW.md, seção 20): a hora
 * técnica (`servicos_avulsos.base_amount`, um valor de referência por hora/unidade,
 * usado apenas para CALCULAR `final_amount` em ServiceOrderValidator::
 * calculateFinalAmount() — `estimated_hours * base_amount - discount_amount +
 * surcharge_amount`) nunca é o valor faturável. Somente `final_amount` alimenta o
 * financeiro. Esta regra já estava implementada em ServiceOrderBillingService desde
 * a sprint anterior (commits 0ac8bd8/2212b4e) — auditoria via agente confirmou, com
 * uma consulta somente-leitura real contra o banco de desenvolvimento, que nenhum
 * caminho de código atual usa base_amount para criar um recebível de OS, e que os
 * únicos registros divergentes existentes são títulos legados anteriores a ambos os
 * commits (service_order_id NULL, criados em julho/2026). Este arquivo formaliza essa
 * garantia com casos específicos (incluindo um valor não múltiplo da hora técnica,
 * para descartar falso positivo por coincidência matemática) e uma trava estrutural
 * que falha caso o código volte a ler base_amount/surcharge_amount/hourly_rate/
 * default_price para faturar uma OS.
 */

// === Trava estrutural: o código de faturamento nunca pode voltar a usar a hora
// === técnica, o preço unitário do catálogo ou qualquer campo equivalente.
$billingServiceSource = (string) file_get_contents(__DIR__ . '/../app/Services/ServiceOrderBillingService.php');
$forbiddenNeedles = ['base_amount', 'hourly_rate', 'default_price', "\$order['surcharge_amount']", '$order["surcharge_amount"]'];
$foundForbidden = [];
foreach ($forbiddenNeedles as $needle) {
    if (str_contains($billingServiceSource, $needle)) {
        $foundForbidden[] = $needle;
    }
}
$assert($foundForbidden === [], 'ServiceOrderBillingService.php nunca referencia hora técnica/preço unitário/catálogo (encontrado: ' . implode(', ', $foundForbidden) . ')');
$assert(str_contains($billingServiceSource, "\$order['final_amount']"), 'ServiceOrderBillingService.php lê o valor faturável exclusivamente de final_amount');

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId <= 0) {
        $pdo->exec("INSERT INTO clients (name, created_at) VALUES ('Cliente Teste Valor Final', NOW())");
        $clientId = (int) $pdo->lastInsertId();
    }

    $companyId = CompanyContext::currentCompanyId();
    $marker = 'TESTE_VALOR_FINAL_' . bin2hex(random_bytes(4));
    $orderRepo = new ServiceOrderRepository();
    $receivableRepo = new FinancialReceivableRepository();
    $billingService = new ServiceOrderBillingService();
    $financialService = new FinancialReceivableService();
    $actorId = 1;

    $makeOrder = static function (float $finalAmount, float $baseAmount) use ($orderRepo, $clientId, $marker, $actorId): int {
        return $orderRepo->create([
            'service_name' => $marker . ' - serviço',
            'client_id' => $clientId,
            'contact_name' => null,
            'assigned_user_id' => null,
            'type' => 'suporte',
            'type_other_description' => null,
            'status' => ServiceOrderStatus::CONCLUIDO,
            'request_description' => null,
            'executed_activities' => null,
            'technical_notes' => null,
            'internal_notes' => null,
            'opened_at' => '2026-07-01 08:00:00',
            'due_at' => null,
            'completed_at' => '2026-07-05 12:00:00',
            'estimated_hours' => 4,
            'executed_hours' => null,
            'billable' => 1,
            'base_service_id' => null,
            // Hora técnica de referência — nunca deve aparecer em nenhuma parcela.
            'base_amount' => $baseAmount,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'final_amount' => $finalAmount,
            'financial_receivable_id' => null,
        ], $actorId);
    };

    // === Caso obrigatório do exemplo da sprint: hora técnica R$ 120, 4h, final R$ 480 ===
    $os480 = $makeOrder(480.00, 120.00);
    $created480 = $billingService->invoice($os480, ['mode' => 'unico', 'due_date' => '2026-09-01'], $actorId);
    $assert(count($created480) === 1, 'OS de R$ 480,00 em pagamento único gera exatamente 1 título');
    $assert(abs((float) $created480[0]['original_amount'] - 480.00) < 0.01, 'Título gerado usa o valor final da OS (R$ 480,00), nunca a hora técnica (R$ 120,00)');
    $assert(abs((float) $created480[0]['remaining_amount'] - 480.00) < 0.01, 'Saldo inicial = valor do título (R$ 480,00), sem recebimento');

    $partial = $financialService->registerReceipt($companyId, (int) $created480[0]['id'], [
        'amount_received' => 180.00,
        'payment_date' => '2026-09-05',
        'payment_method' => 'pix',
        'observation' => 'Pagamento parcial de teste',
    ], $actorId);
    $assert(abs((float) $partial['remaining_amount'] - 300.00) < 0.01, 'Recebimento parcial de R$ 180,00 reduz o saldo para R$ 300,00 (nunca baseado em R$ 120,00)');

    $full = $financialService->registerReceipt($companyId, (int) $created480[0]['id'], [
        'amount_received' => 300.00,
        'payment_date' => '2026-09-10',
        'payment_method' => 'pix',
        'observation' => 'Quitação de teste',
    ], $actorId);
    $assert(abs((float) $full['remaining_amount']) < 0.01, 'Recebimento do restante zera o saldo (R$ 480,00 totalmente quitado)');
    $assert((string) $full['status'] === 'paid', 'Título fica com status "paid" após quitação total');

    // === Caso anti-falso-positivo: valor NÃO múltiplo da hora técnica ===
    // Hora técnica R$ 120,00, mas valor final R$ 550,00 (não é múltiplo de 120) em 2
    // parcelas — se o código estivesse usando base_amount por engano, o resultado
    // jamais seria 275,00; qualquer coincidência matemática fica descartada aqui.
    $os550 = $makeOrder(550.00, 120.00);
    $created550 = $billingService->invoice($os550, [
        'mode' => 'parcelado',
        'installments_count' => 2,
        'first_due_date' => '2026-09-10',
        'periodicity' => 'mensal',
    ], $actorId);
    $amounts550 = array_map(static fn (array $r): float => round((float) $r['original_amount'], 2), $created550);
    $assert($amounts550 === [275.0, 275.0], 'OS de R$ 550,00 em 2 parcelas gera 2 × R$ 275,00 (valor não múltiplo da hora técnica de R$ 120,00, descarta coincidência)');
    $assert(!in_array(120.0, $amounts550, true), 'Nenhuma parcela do cenário R$ 550,00 usa o valor de hora técnica (R$ 120,00)');

    // === OS/Financeiro/Relatório enxergam o mesmo valor ===
    $osRow = $orderRepo->find($os480);
    $assert(abs((float) $osRow['final_amount'] - 480.00) < 0.01, 'A própria OS mantém final_amount=480,00 (não foi alterado pelo faturamento)');
    $viaReceivableList = $receivableRepo->listByServiceOrder($companyId, $os480);
    $assert(count($viaReceivableList) === 1 && abs((float) $viaReceivableList[0]['original_amount'] - 480.00) < 0.01, 'A seção Financeiro da OS (mesma consulta usada pela tela) exibe R$ 480,00');
    $reportRows = $receivableRepo->reportRows($companyId, ['client_id' => $clientId])['rows'];
    $reportRow480 = null;
    foreach ($reportRows as $row) {
        if ((int) $row['id'] === (int) $created480[0]['id']) {
            $reportRow480 = $row;
        }
    }
    $assert($reportRow480 !== null && abs((float) $reportRow480['original_amount'] - 480.00) < 0.01, 'Relatório Financeiro (mesma fonte do Dashboard) exibe R$ 480,00 para o mesmo título');

    // === Alterar base_amount/estimated_hours após o faturamento não retroage sobre o título já criado ===
    $orderRepo->update($os480, array_merge($osRow, ['base_amount' => 999.00, 'estimated_hours' => 1]), $actorId);
    $afterFieldChange = $receivableRepo->find($companyId, (int) $created480[0]['id']);
    $assert(abs((float) $afterFieldChange['original_amount'] - 480.00) < 0.01, 'Alterar base_amount da OS depois de faturada não altera o título já criado (valor final é fixado no momento do faturamento)');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

return $failures;
