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
 * Regressão do defeito real relatado em produção para OS-000002 (auditoria completa
 * em SPRINT_OS_BILLING_AND_FLOW.md, seção 20): "Valor da OS: R$ 1.500,00 / Parcelas
 * geradas: 1" mas a linha mostrava "1/3 — R$ 120,00 — Saldo R$ 0,00".
 *
 * Causa raiz 1 (R$ 120 em vez de R$ 1.500): confirmada via git log -p -- ServiceOrderService.php
 * — o antigo ServiceOrderService::receivablePayload() (removido em 2026-08-14, commit
 * 0ac8bd8) usava `base_amount + surcharge_amount` como original_amount, SEM multiplicar
 * por estimated_hours (a fórmula real de final_amount, em ServiceOrderValidator::
 * calculateFinalAmount(), é `estimated_hours * base_amount - discount_amount +
 * surcharge_amount`). Título legado, não é mais possível criar um novo dessa forma —
 * o fluxo atual (ServiceOrderBillingService) já usa exclusivamente final_amount. Este
 * teste prova que o valor final da OS (nunca base_amount) é o que alimenta as parcelas.
 *
 * Causa raiz 2 ("Parcelas geradas: 1" com "1/3"): confirmada em
 * FinancialModuleController::payloadFromRequest()/FinancialReceivableService::update() —
 * installment_number/total_installments eram aceitos de um <input type="number"> livre,
 * sem qualquer validação contra a quantidade real de títulos vinculados à OS. Corrigido
 * congelando esses dois campos em update() sempre que o título tiver service_order_id.
 * Este teste prova que a edição não altera mais esses campos para títulos de OS.
 *
 * Causa raiz 3 (saldo R$ 0,00 com recebido R$ 0,00): matematicamente só ocorre quando
 * discount_amount >= original_amount (remaining_amount = original + juros + multa -
 * desconto). Não é bug na fórmula; este teste prova que a fórmula em
 * FinancialReceivableRepository::create() é aplicada corretamente e de forma consistente
 * ao criar as parcelas do reparcelamento.
 */

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId <= 0) {
        $pdo->exec("INSERT INTO clients (name, created_at) VALUES ('Cliente Teste Reparcel', NOW())");
        $clientId = (int) $pdo->lastInsertId();
    }

    $companyId = CompanyContext::currentCompanyId();
    $marker = 'TESTE_REPARCEL_' . bin2hex(random_bytes(4));
    $orderRepo = new ServiceOrderRepository();
    $receivableRepo = new FinancialReceivableRepository();
    $billingService = new ServiceOrderBillingService();
    $financialService = new FinancialReceivableService();
    $actorId = 1;

    $makeOrder = static function (array $overrides) use ($orderRepo, $clientId, $marker, $actorId): int {
        $data = array_merge([
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
            'estimated_hours' => 15,
            'executed_hours' => null,
            'billable' => 1,
            // Propositalmente diferente de final_amount, reproduzindo o cenário real:
            // base_amount=120 é a origem histórica do bug (valor de hora/base), NUNCA
            // deve aparecer em nenhuma parcela criada pelo fluxo atual.
            'base_service_id' => null,
            'base_amount' => 120,
            'discount_amount' => 300,
            'surcharge_amount' => 0,
            'final_amount' => 1500,
            'financial_receivable_id' => null,
        ], $overrides);
        return $orderRepo->create($data, $actorId);
    };

    // === Cenário obrigatório: OS de R$ 1.500,00 faturada em 3 parcelas de R$ 500,00 ===
    $osId = $makeOrder([]);
    $created = $billingService->invoice($osId, [
        'mode' => 'parcelado',
        'installments_count' => 3,
        'first_due_date' => '2026-09-10',
        'periodicity' => 'mensal',
    ], $actorId);

    $assert(count($created) === 3, 'OS de R$ 1.500,00 em 3 parcelas gera exatamente 3 títulos físicos');
    $amounts = array_map(static fn (array $r): float => round((float) $r['original_amount'], 2), $created);
    $assert($amounts === [500.0, 500.0, 500.0], 'As 3 parcelas são de R$ 500,00 cada — nunca R$ 120,00 (base_amount)');
    $assert(abs(array_sum($amounts) - 1500.0) < 0.01, 'A soma das parcelas é exatamente o valor final da OS (R$ 1.500,00), não base_amount');
    $assert(!in_array(120.0, $amounts, true), 'Nenhuma parcela usa o valor de hora/base (R$ 120,00) — regressão direta do bug relatado');

    $linkedNow = $receivableRepo->listByServiceOrder($companyId, $osId);
    $assert(count($linkedNow) === 3, 'A quantidade real de títulos vinculados à OS bate com "Parcelas geradas" (nunca 1 exibido/3 reais)');
    foreach ($linkedNow as $row) {
        $assert((int) $row['total_installments'] === 3, 'Cada título criado tem total_installments = quantidade real de parcelas (' . $row['installment_number'] . '/' . $row['total_installments'] . ')');
    }

    $dueDates = array_map(static fn (array $r): string => (string) $r['due_date'], $created);
    $assert($dueDates === ['2026-09-10', '2026-10-10', '2026-11-10'], 'Vencimentos mensais persistidos corretamente a partir do informado pelo usuário');

    // === Trava: installment_number/total_installments não são editáveis por título de OS ===
    $firstReceivableId = (int) $created[0]['id'];
    $editedSameFields = $financialService->update($companyId, $firstReceivableId, [
        'installment_number' => 1,
        'total_installments' => 99,
        'due_date' => '2026-09-15',
    ], $actorId);
    $assert((int) $editedSameFields['total_installments'] === 3, 'Editar um título de OS ignora total_installments submetido (permanece 3, nunca vira "99")');
    $assert((string) $editedSameFields['due_date'] === '2026-09-15', 'Vencimento de parcela em aberto continua editável normalmente');

    // === Reparcelamento seguro (nenhum recebimento ainda) ===
    $reparceled = $billingService->reparcel($osId, [
        'mode' => 'parcelado',
        'installments_count' => 2,
        'first_due_date' => '2026-10-01',
        'periodicity' => 'mensal',
    ], $actorId);
    $assert(count($reparceled) === 2, 'Reparcelamento sem recebimento gera a nova composição (2 parcelas)');
    $reparceledAmounts = array_map(static fn (array $r): float => round((float) $r['original_amount'], 2), $reparceled);
    $assert(abs(array_sum($reparceledAmounts) - 1500.0) < 0.01, 'Soma do reparcelamento continua batendo com o valor final da OS');

    $afterReparcel = $receivableRepo->listByServiceOrder($companyId, $osId);
    $assert(count($afterReparcel) === 2, 'Após reparcelar, apenas os 2 novos títulos aparecem vinculados à OS (os 3 antigos foram removidos)');

    $oldStillVisible = 0;
    foreach ($created as $old) {
        $check = $receivableRepo->find($companyId, (int) $old['id']);
        if ($check !== null) {
            $oldStillVisible++;
        }
    }
    $assert($oldStillVisible === 0, 'Os 3 títulos antigos substituídos não aparecem mais em nenhuma listagem (soft delete aplicado)');

    $orderAfter = $orderRepo->find($osId);
    $assert((string) $orderAfter['status'] === ServiceOrderStatus::FATURADO, 'OS permanece Faturado após reparcelamento');
    $assert((int) $orderAfter['financial_receivable_id'] === (int) $reparceled[0]['id'], 'Vínculo legado financial_receivable_id passa a apontar para a nova primeira parcela');

    // === Reparcelamento bloqueado quando já existe recebimento ===
    $receivableWithPayment = $reparceled[0];
    $financialService->registerReceipt($companyId, (int) $receivableWithPayment['id'], [
        'amount_received' => 200.00,
        'payment_date' => '2026-10-02',
        'payment_method' => 'pix',
        'observation' => 'Pagamento parcial de teste',
    ], $actorId);

    $threwReparcel = false;
    $reparcelErrorMessage = '';
    try {
        $billingService->reparcel($osId, [
            'mode' => 'unico',
            'due_date' => '2026-12-01',
        ], $actorId);
    } catch (\Throwable $e) {
        $threwReparcel = true;
        $reparcelErrorMessage = $e->getMessage();
    }
    $assert($threwReparcel && str_contains($reparcelErrorMessage, 'recebimento'), 'Reparcelamento é bloqueado quando qualquer título vinculado já possui recebimento');

    $stillTwoTitles = $receivableRepo->listByServiceOrder($companyId, $osId);
    $assert(count($stillTwoTitles) === 2, 'Reparcelamento bloqueado não altera os títulos existentes (permanecem os 2 anteriores)');

    // === Saldo consistente: remaining_amount = original + juros + multa - desconto ===
    $paidTitle = $receivableRepo->find($companyId, (int) $receivableWithPayment['id']);
    $assert(abs((float) $paidTitle['received_amount'] - 200.00) < 0.01, 'Recebimento parcial é refletido no título correto');
    $expectedRemaining = round((float) $paidTitle['original_amount'] - 200.00, 2);
    $assert(abs((float) $paidTitle['remaining_amount'] - $expectedRemaining) < 0.01, 'Saldo = valor do título - total recebido (nunca R$ 0,00 quando há saldo real em aberto)');

    // === OS sem cobrança: reparcelar deve orientar a usar o faturamento normal ===
    $osSemCobranca = $makeOrder(['final_amount' => 500, 'discount_amount' => 0]);
    $threwNoBilling = false;
    try {
        $billingService->reparcel($osSemCobranca, ['mode' => 'unico', 'due_date' => '2026-09-01'], $actorId);
    } catch (\Throwable $e) {
        $threwNoBilling = str_contains($e->getMessage(), 'ainda não possui cobrança');
    }
    $assert($threwNoBilling, 'Reparcelar uma OS que nunca foi faturada orienta a usar o faturamento normal, sem quebrar');

    // === Dashboard/Relatório enxergam exatamente os mesmos títulos pós-reparcelamento ===
    $reportRepo = new \App\Repositories\FinancialReceivableRepository();
    $reportRows = $reportRepo->reportRows($companyId, ['client_id' => $clientId])['rows'];
    $reportIds = array_map(static fn (array $r): int => (int) $r['id'], $reportRows);
    foreach ($reparceled as $row) {
        $assert(in_array((int) $row['id'], $reportIds, true), 'Relatório Financeiro (mesma fonte usada pelo Dashboard) enxerga a parcela reparcelada #' . $row['id']);
    }
    foreach ($created as $old) {
        $assert(!in_array((int) $old['id'], $reportIds, true), 'Relatório Financeiro não lista mais o título antigo substituído #' . $old['id']);
    }
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

return $failures;
