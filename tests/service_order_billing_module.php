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
use App\Services\ServiceOrderService;
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
 * Regressão do fluxo "Definir cobrança" (SPRINT_OS_BILLING_AND_FLOW.md): prova, contra
 * o banco de verdade (rollback garantido no finally, nunca comitado), que o faturamento
 * de uma Ordem de Serviço cria os títulos corretos em financial_accounts_receivable
 * (pagamento único, parcelado com arredondamento e personalizado), nunca duplica
 * cobrança, nunca marca a OS como Faturado sem lançamento financeiro, e que a
 * salvaguarda de redução de valor abaixo do recebido está em vigor.
 */

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId <= 0) {
        $pdo->exec("INSERT INTO clients (name, created_at) VALUES ('Cliente Teste Faturamento', NOW())");
        $clientId = (int) $pdo->lastInsertId();
    }

    $companyId = CompanyContext::currentCompanyId();
    $marker = 'TESTE_OS_BILLING_' . bin2hex(random_bytes(4));
    $orderRepo = new ServiceOrderRepository();
    $receivableRepo = new FinancialReceivableRepository();
    $billingService = new ServiceOrderBillingService();
    $orderService = new ServiceOrderService();
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
            'estimated_hours' => null,
            'executed_hours' => null,
            'billable' => 1,
            'base_service_id' => null,
            'base_amount' => 0,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'final_amount' => 0,
            'financial_receivable_id' => null,
        ], $overrides);
        return $orderRepo->create($data, $actorId);
    };

    // 1) OS sem cobrança não gera financeiro
    $idNonBillable = $makeOrder(['billable' => 0, 'final_amount' => 0]);
    $threw = false;
    try {
        $billingService->invoice($idNonBillable, ['mode' => 'unico', 'due_date' => '2026-08-01'], $actorId);
    } catch (\Throwable $e) {
        $threw = str_contains($e->getMessage(), 'não possui cobrança');
    }
    $assert($threw, 'OS não faturável rejeita o faturamento');
    $assert($receivableRepo->listByServiceOrder($companyId, $idNonBillable) === [], 'OS não faturável não gera nenhum título');

    // 2) OS não concluída rejeita o faturamento
    $idNotDone = $makeOrder(['status' => ServiceOrderStatus::ABERTO, 'billable' => 1, 'final_amount' => 500]);
    $threw = false;
    try {
        $billingService->invoice($idNotDone, ['mode' => 'unico', 'due_date' => '2026-08-01'], $actorId);
    } catch (\Throwable $e) {
        $threw = str_contains($e->getMessage(), 'Conclua a Ordem de Serviço');
    }
    $assert($threw, 'OS ainda não concluída rejeita o faturamento');
    $assert((string) $orderRepo->find($idNotDone)['status'] === ServiceOrderStatus::ABERTO, 'OS ainda não concluída permanece com o status original após tentativa de faturamento');

    // 3) Pagamento único
    $idUnico = $makeOrder(['final_amount' => 1500.00]);
    $createdUnico = $billingService->invoice($idUnico, [
        'mode' => 'unico',
        'due_date' => '2026-08-10',
        'description' => 'Serviço prestado',
    ], $actorId);
    $assert(count($createdUnico) === 1, 'Pagamento único gera exatamente 1 título');
    $assert(abs((float) $createdUnico[0]['original_amount'] - 1500.00) < 0.01, 'Pagamento único usa o valor final da OS recalculado no servidor');
    $assert((string) $createdUnico[0]['due_date'] === '2026-08-10', 'Pagamento único usa o vencimento informado');
    $assert((int) $createdUnico[0]['service_order_id'] === $idUnico, 'Título vinculado à Ordem de Serviço via service_order_id');
    $assert((int) $createdUnico[0]['client_id'] === $clientId, 'Título vinculado ao cliente correto');
    $assert((string) $orderRepo->find($idUnico)['status'] === ServiceOrderStatus::FATURADO, 'OS é marcada como Faturado somente após o lançamento financeiro');
    $assert((int) $orderRepo->find($idUnico)['financial_receivable_id'] === (int) $createdUnico[0]['id'], 'OS mantém o vínculo legado financial_receivable_id apontando para a 1ª parcela');

    // Não permite faturar duas vezes
    $threwDuplicate = false;
    try {
        $billingService->invoice($idUnico, ['mode' => 'unico', 'due_date' => '2026-08-10'], $actorId);
    } catch (\Throwable $e) {
        $threwDuplicate = str_contains($e->getMessage(), 'já possui cobrança gerada');
    }
    $assert($threwDuplicate, 'Faturar uma OS já cobrada é bloqueado');
    $assert(count($receivableRepo->listByServiceOrder($companyId, $idUnico)) === 1, 'Tentativa de faturamento duplicado não cria títulos extras');

    // 4) Parcelado com arredondamento (R$ 1.000,00 / 3)
    $idParcelado = $makeOrder(['final_amount' => 1000.00]);
    $createdParcelado = $billingService->invoice($idParcelado, [
        'mode' => 'parcelado',
        'installments_count' => 3,
        'first_due_date' => '2026-09-15',
        'periodicity' => 'mensal',
    ], $actorId);
    $assert(count($createdParcelado) === 3, 'Parcelado gera a quantidade de parcelas solicitada');
    $amounts = array_map(static fn (array $r): float => round((float) $r['original_amount'], 2), $createdParcelado);
    $assert($amounts === [333.33, 333.33, 333.34], 'Arredondamento distribui a diferença de centavos na última parcela');
    $assert(abs(array_sum($amounts) - 1000.00) < 0.001, 'Soma das parcelas nunca perde nem cria centavos');
    $dueDates = array_map(static fn (array $r): string => (string) $r['due_date'], $createdParcelado);
    $assert($dueDates === ['2026-09-15', '2026-10-15', '2026-11-15'], 'Parcelado mensal gera vencimentos mensais a partir do primeiro informado');
    $assert((int) $createdParcelado[0]['total_installments'] === 3 && (int) $createdParcelado[2]['installment_number'] === 3, 'Parcelas armazenam número/total corretos');

    // 5) Parcelamento personalizado válido
    $idCustomOk = $makeOrder(['final_amount' => 6000.00]);
    $createdCustom = $billingService->invoice($idCustomOk, [
        'mode' => 'personalizado',
        'installments' => [
            ['amount' => 2000.00, 'due_date' => '2026-09-10', 'description' => 'Entrada'],
            ['amount' => 1500.00, 'due_date' => '2026-10-10', 'description' => 'Parcela 2'],
            ['amount' => 2500.00, 'due_date' => '2026-11-10', 'description' => 'Parcela 3'],
        ],
    ], $actorId);
    $assert(count($createdCustom) === 3, 'Parcelamento personalizado cria uma linha por parcela informada');
    $assert(abs(array_sum(array_map(static fn (array $r): float => (float) $r['original_amount'], $createdCustom)) - 6000.00) < 0.01, 'Soma do parcelamento personalizado bate com o valor da OS');

    // Parcelamento personalizado com soma divergente é bloqueado e não cria nada
    $idCustomBad = $makeOrder(['final_amount' => 6000.00]);
    $threwSum = false;
    $sumErrorMessage = '';
    try {
        $billingService->invoice($idCustomBad, [
            'mode' => 'personalizado',
            'installments' => [
                ['amount' => 2000.00, 'due_date' => '2026-09-10', 'description' => 'Entrada'],
                ['amount' => 1500.00, 'due_date' => '2026-10-10', 'description' => 'Parcela 2'],
                ['amount' => 2400.00, 'due_date' => '2026-11-10', 'description' => 'Parcela 3'],
            ],
        ], $actorId);
    } catch (\Throwable $e) {
        $threwSum = true;
        $sumErrorMessage = $e->getMessage();
    }
    $assert($threwSum && str_contains($sumErrorMessage, 'Diferença: R$ 100,00'), 'Soma divergente das parcelas é rejeitada com a diferença exata');
    $assert($receivableRepo->listByServiceOrder($companyId, $idCustomBad) === [], 'Faturamento rejeitado não deixa títulos parciais (nunca comita parte da transação)');
    $assert((string) $orderRepo->find($idCustomBad)['status'] === ServiceOrderStatus::CONCLUIDO, 'OS com faturamento rejeitado nunca é marcada como Faturado');

    // 6) updateStatus() direto para Faturado é bloqueado sem passar pelo faturamento
    $idDirectStatus = $makeOrder(['final_amount' => 900.00]);
    $threwDirectStatus = false;
    try {
        $orderService->updateStatus($idDirectStatus, ServiceOrderStatus::FATURADO, $actorId);
    } catch (\Throwable $e) {
        $threwDirectStatus = str_contains($e->getMessage(), 'fluxo de faturamento');
    }
    $assert($threwDirectStatus, 'Alterar status diretamente para Faturado sem cobrança definida é bloqueado');
    $billingService->invoice($idDirectStatus, ['mode' => 'unico', 'due_date' => '2026-08-20'], $actorId);
    $assert((string) $orderRepo->find($idDirectStatus)['status'] === ServiceOrderStatus::FATURADO, 'Após o faturamento, a OS fica Faturado normalmente');

    // 7) Salvaguarda financeira: não reduzir valor abaixo do já recebido
    $financialService = new FinancialReceivableService();
    $receivableId = (int) $createdUnico[0]['id'];
    $financialService->registerReceipt($companyId, $receivableId, [
        'amount_received' => 900.00,
        'payment_date' => '2026-08-05',
        'payment_method' => 'pix',
        'observation' => 'Pagamento parcial de teste',
    ], $actorId);
    $afterReceipt = $receivableRepo->find($companyId, $receivableId);
    $assert(abs((float) $afterReceipt['received_amount'] - 900.00) < 0.01, 'Baixa parcial registra o valor recebido corretamente');
    $assert(abs((float) $afterReceipt['remaining_amount'] - 600.00) < 0.01, 'Baixa parcial calcula o saldo restante corretamente');

    $threwReduce = false;
    try {
        $financialService->update($companyId, $receivableId, ['original_amount' => 800.00], $actorId);
    } catch (\Throwable $e) {
        $threwReduce = str_contains($e->getMessage(), 'não pode ser menor que o valor já recebido');
    }
    $assert($threwReduce, 'Reduzir o valor do título abaixo do já recebido é bloqueado');

    $financialService->update($companyId, $receivableId, ['original_amount' => 1500.00], $actorId);
    $afterKeep = $receivableRepo->find($companyId, $receivableId);
    $assert(abs((float) $afterKeep['original_amount'] - 1500.00) < 0.01, 'Editar o título mantendo valor acima do recebido continua permitido');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

return $failures;
