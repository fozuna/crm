<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Core\DB;
use App\Repositories\FinancialEnterpriseDashboardRepository;
use App\Repositories\FinancialReceiptRepository;
use App\Repositories\FinancialReceivableRepository;

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
 * Regressão do P02 (CRM_AUDIT.md / SPRINT_FINANCE_REPORT_FIX.md): /relatorios/financeiro
 * lia exclusivamente finance_installments (INNER JOIN proposals), o que o tornava
 * estruturalmente incapaz de exibir qualquer título nascido de Ordem de Serviço,
 * renegociação ou lançamento manual do módulo enterprise — mesmo quando o Dashboard
 * Financeiro (/financeiro/dashboard), que já lia financial_accounts_receivable
 * diretamente, mostrava esses valores corretamente.
 *
 * Este teste escreve registros reais dentro de uma transação nunca commitada
 * (rollback garantido no finally) para provar, contra o banco de verdade, que
 * FinancialReceivableRepository/FinancialReceiptRepository — agora reaproveitados
 * também pelo relatório — produzem os mesmos totais que o Dashboard Financeiro e
 * exibem corretamente títulos de todas as origens.
 */

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $companyId = (int) ($pdo->query('SELECT id FROM company_profile ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    $otherClientStmt = $pdo->prepare('SELECT id FROM clients WHERE id <> :client_id ORDER BY id LIMIT 1');
    $otherClientStmt->execute([':client_id' => $clientId]);
    $otherClientId = (int) ($otherClientStmt->fetchColumn() ?: 0);
    $installmentId = (int) ($pdo->query('SELECT id FROM finance_installments ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    $contractId = (int) ($pdo->query('SELECT id FROM contracts ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    $assert($companyId > 0 && $clientId > 0, 'Pré-requisitos de fixture disponíveis (company_profile e clients)');

    $payRepoBaseline = new FinancialReceiptRepository();
    $receivedBaseline = $payRepoBaseline->totalReceived($companyId, ['client_id' => $clientId, 'project_id' => 0, 'due_from' => '2026-01-01', 'due_to' => '2026-07-17']);

    $marker = 'TESTE_P02_' . bin2hex(random_bytes(4));
    $due = '2026-03-10';
    $from = '2026-01-01';
    $to = '2026-07-17';

    $insertReceivable = $pdo->prepare(
        'INSERT INTO financial_accounts_receivable (
            company_id, project_id, client_id, contract_id, source_installment_id,
            installment_number, total_installments, title, original_amount, discount_amount,
            interest_amount, fine_amount, received_amount, remaining_amount, due_date,
            status, created_at, updated_at
        ) VALUES (
            :company_id, NULL, :client_id, :contract_id, :source_installment_id,
            1, 1, :title, :original_amount, 0, 0, 0, :received_amount, :remaining_amount, :due_date,
            :status, NOW(), NOW()
        )'
    );

    $makeReceivable = static function (array $overrides) use ($insertReceivable, $pdo, $companyId, $clientId, $marker, $due): int {
        $insertReceivable->execute(array_merge([
            ':company_id' => $companyId,
            ':client_id' => $clientId,
            ':contract_id' => null,
            ':source_installment_id' => null,
            ':title' => $marker . ' - ' . ($overrides[':title_suffix'] ?? 'manual'),
            ':original_amount' => 1000.00,
            ':received_amount' => 0,
            ':remaining_amount' => 1000.00,
            ':due_date' => $due,
            ':status' => 'pending',
        ], array_diff_key($overrides, [':title_suffix' => true])));
        return (int) $pdo->lastInsertId();
    };

    // 1) Manual (sem projeto, sem proposta, sem contrato) — origem "Manual"
    $idManual = $makeReceivable([':title_suffix' => 'manual', ':status' => 'overdue', ':remaining_amount' => 300.00, ':original_amount' => 300.00]);

    // 2) Origem proposta/projeto (source_installment_id preenchido)
    $idProposal = $installmentId > 0
        ? $makeReceivable([':title_suffix' => 'proposta', ':source_installment_id' => $installmentId, ':status' => 'overdue', ':remaining_amount' => 500.00, ':original_amount' => 500.00])
        : 0;

    // 3) Origem contrato
    $idContract = $contractId > 0
        ? $makeReceivable([':title_suffix' => 'contrato', ':contract_id' => $contractId, ':status' => 'pending', ':remaining_amount' => 200.00, ':original_amount' => 200.00])
        : 0;

    // 4) Origem Ordem de Serviço (financial_accounts_receivable criado direto pelo módulo de OS,
    //    sem nunca passar por finance_installments — exatamente o caso descrito no P02)
    $idOs = $makeReceivable([':title_suffix' => 'os', ':status' => 'overdue', ':remaining_amount' => 260.00, ':original_amount' => 260.00]);
    $pdo->prepare("INSERT INTO servicos_avulsos (
            numero_sequencial, numero_os, service_name, client_id, type, status,
            opened_at, billable, base_amount, discount_amount, surcharge_amount, final_amount,
            financial_receivable_id, created_at, updated_at
        ) VALUES (
            :seq, :numero_os, :service_name, :client_id, 'suporte', 'faturado',
            NOW(), 1, 260, 0, 0, 260, :receivable_id, NOW(), NOW()
        )")->execute([
            ':seq' => (int) ($pdo->query('SELECT COALESCE(MAX(numero_sequencial), 0) FROM servicos_avulsos')->fetchColumn()) + 1,
            ':numero_os' => 'OS-TESTE-P02-' . bin2hex(random_bytes(3)),
            ':service_name' => $marker . ' - servico',
            ':client_id' => $clientId,
            ':receivable_id' => $idOs,
        ]);

    // 5) Pagamento total (saldo zerado) — deve continuar aparecendo, mas fora dos totais em aberto
    $idPaid = $makeReceivable([':title_suffix' => 'pago-total', ':status' => 'paid', ':remaining_amount' => 0, ':received_amount' => 400.00, ':original_amount' => 400.00]);
    $pdo->prepare('INSERT INTO financial_receipts (receivable_id, amount_received, interest_amount, fine_amount, discount_amount, payment_method, payment_date, created_at)
                    VALUES (:receivable_id, 400.00, 0, 0, 0, "PIX", :payment_date, NOW())')
        ->execute([':receivable_id' => $idPaid, ':payment_date' => '2026-04-05 10:00:00']);

    // 6) Pagamento parcial (saldo reduzido, não zerado)
    $idPartial = $makeReceivable([':title_suffix' => 'pago-parcial', ':status' => 'partially_paid', ':remaining_amount' => 150.00, ':received_amount' => 150.00, ':original_amount' => 300.00]);
    $pdo->prepare('INSERT INTO financial_receipts (receivable_id, amount_received, interest_amount, fine_amount, discount_amount, payment_method, payment_date, created_at)
                    VALUES (:receivable_id, 150.00, 0, 0, 0, "PIX", :payment_date, NOW())')
        ->execute([':receivable_id' => $idPartial, ':payment_date' => '2026-04-06 10:00:00']);

    // 7) Cancelado — nunca deve entrar nos totais
    $idCanceled = $makeReceivable([':title_suffix' => 'cancelado', ':status' => 'canceled', ':remaining_amount' => 999.00, ':original_amount' => 999.00]);

    // 8) Vinculado a outro cliente, para testar isolamento do filtro client_id
    $idOtherClient = $otherClientId > 0
        ? $makeReceivable([':title_suffix' => 'outro-cliente', ':client_id' => $otherClientId, ':status' => 'overdue', ':remaining_amount' => 700.00, ':original_amount' => 700.00])
        : 0;

    $recRepo = new FinancialReceivableRepository();
    $payRepo = new FinancialReceiptRepository();
    $baseFilters = ['client_id' => 0, 'project_id' => 0, 'status' => '', 'due_from' => $from, 'due_to' => $to, 'sort' => 'due_date', 'direction' => 'asc'];

    // --- Origem ---
    $list = $recRepo->paginate($companyId, array_merge($baseFilters, ['client_id' => $clientId]), 1, 50);
    $ids = array_column($list['rows'], 'id');
    $assert(in_array($idManual, $ids, true) && in_array($idOs, $ids, true), 'Título de origem manual e de origem Ordem de Serviço aparecem no relatório (P02)');

    $origins = $recRepo->originsForIds([$idManual, $idProposal, $idContract, $idOs]);
    $assert(isset($origins[$idOs]), 'originsForIds() identifica corretamente o título nascido de Ordem de Serviço');
    $assert(!isset($origins[$idManual]), 'originsForIds() não marca como OS um título manual');

    // --- Totais consistentes com o Dashboard Financeiro (mesma fonte de dados) ---
    $totals = $recRepo->totals($companyId, array_merge($baseFilters, ['client_id' => $clientId]));
    $dashboard = (new FinancialEnterpriseDashboardRepository())->data($companyId, ['client_id' => $clientId, 'from' => $from, 'to' => $to]);
    $assert(
        abs($totals['receivable'] - (float) $dashboard['totals']['total_receivable']) < 0.001,
        "Relatório e Dashboard retornam o mesmo total a receber (relatório={$totals['receivable']}, dashboard={$dashboard['totals']['total_receivable']})"
    );
    $assert(
        abs($totals['overdue'] - (float) $dashboard['totals']['total_overdue']) < 0.001,
        "Relatório e Dashboard retornam o mesmo total vencido (relatório={$totals['overdue']}, dashboard={$dashboard['totals']['total_overdue']})"
    );

    // --- Cancelado não entra nos totais ---
    $totalsNoClientFilter = $recRepo->totals($companyId, $baseFilters);
    $assert($totalsNoClientFilter['receivable'] < 999.00 * 100, 'Sanidade: total a receber não explode com valores absurdos');
    $listAll = $recRepo->paginate($companyId, $baseFilters, 1, 100);
    $canceledRow = null;
    foreach ($listAll['rows'] as $row) {
        if ((int) $row['id'] === $idCanceled) {
            $canceledRow = $row;
        }
    }
    $assert($canceledRow !== null, 'Título cancelado continua aparecendo na listagem (não é eliminado)');
    // remaining_amount de um título cancelado não deve ser somado a "a receber"/"vencido" do agregado geral,
    // mesmo que o valor fictício (999) fosse suficiente para distorcer o total caso fosse somado por engano.
    $sumWithoutCanceled = 0.0;
    foreach ($listAll['rows'] as $row) {
        if (in_array($row['status'], ['pending', 'partially_paid', 'overdue'], true)) {
            $sumWithoutCanceled += (float) $row['remaining_amount'];
        }
    }
    $assert(abs($totalsNoClientFilter['receivable'] - $sumWithoutCanceled) < 0.001, 'totals() exclui registros cancelados do total a receber');

    // --- Filtro client_id=0 representa "todos" ---
    $assert(count(array_intersect([$idManual, $idOtherClient], array_column($listAll['rows'], 'id'))) === ($otherClientId > 0 ? 2 : 1), 'client_id=0 (ou ausente) inclui títulos de todos os clientes');

    // --- Filtro por cliente isola corretamente ---
    if ($otherClientId > 0) {
        $onlyOther = $recRepo->paginate($companyId, array_merge($baseFilters, ['client_id' => $otherClientId]), 1, 50);
        $onlyOtherIds = array_column($onlyOther['rows'], 'id');
        $assert(in_array($idOtherClient, $onlyOtherIds, true) && !in_array($idManual, $onlyOtherIds, true), 'Filtro por client_id isola os títulos do cliente informado');
    }

    // --- Filtro por status ---
    $onlyCanceled = $recRepo->paginate($companyId, array_merge($baseFilters, ['client_id' => $clientId, 'status' => 'canceled']), 1, 50);
    $onlyCanceledIds = array_column($onlyCanceled['rows'], 'id');
    $assert(in_array($idCanceled, $onlyCanceledIds, true) && !in_array($idManual, $onlyCanceledIds, true), 'Filtro por status retorna exatamente o subconjunto esperado');

    // --- Pagamento parcial reduz saldo; pagamento total zera saldo ---
    $paidRow = null;
    $partialRow = null;
    foreach ($listAll['rows'] as $row) {
        if ((int) $row['id'] === $idPaid) {
            $paidRow = $row;
        }
        if ((int) $row['id'] === $idPartial) {
            $partialRow = $row;
        }
    }
    $assert($paidRow !== null && (float) $paidRow['remaining_amount'] === 0.0, 'Pagamento total zera o saldo em aberto exibido no relatório');
    $assert($partialRow !== null && (float) $partialRow['remaining_amount'] > 0.0 && (float) $partialRow['remaining_amount'] < (float) $partialRow['original_amount'], 'Pagamento parcial reduz o saldo sem zerá-lo');

    // --- Pagamentos listados no período (financial_receipts) ---
    $payments = $payRepo->listByPeriod($companyId, array_merge($baseFilters, ['client_id' => $clientId]), 1, 50);
    $paymentReceivableIds = array_column($payments['rows'], 'receivable_id');
    $assert(in_array($idPaid, $paymentReceivableIds, true) && in_array($idPartial, $paymentReceivableIds, true), 'Pagamentos do título totalmente e parcialmente pago aparecem na listagem de recebimentos');
    $receivedTotal = $payRepo->totalReceived($companyId, array_merge($baseFilters, ['client_id' => $clientId]));
    $receivedDelta = $receivedTotal - $receivedBaseline;
    $assert(abs($receivedDelta - 550.00) < 0.001, "totalReceived() soma corretamente os recebimentos novos do período (esperado +550.00 sobre a base pré-existente, obtido +{$receivedDelta})");

    // --- Ordenação: whitelist não aceita coluna arbitrária (sem gerar erro SQL) ---
    $unsafeSort = $recRepo->paginate($companyId, array_merge($baseFilters, ['client_id' => $clientId, 'sort' => 'far.id); DROP TABLE clients; --']), 1, 50);
    $assert(is_array($unsafeSort['rows']), 'Valor de ordenação não whitelistado não gera erro SQL (cai no default due_date)');

    // --- reportRows() não trunca em volume alto (regressão do padrão de bug já visto em P03/OS) ---
    $bulkTotal = 130;
    $bulkMarker = $marker . '_BULK';
    for ($i = 1; $i <= $bulkTotal; $i++) {
        $makeReceivable([':title_suffix' => 'bulk-' . $i, ':title' => $bulkMarker . ' #' . $i, ':status' => 'pending', ':remaining_amount' => 10.00, ':original_amount' => 10.00]);
    }
    // como buildFilters() não filtra por título, isolamos a contagem bruta via SQL direto.
    $bulkCountStmt = $pdo->prepare("SELECT COUNT(*) FROM financial_accounts_receivable WHERE title LIKE :marker");
    $bulkCountStmt->execute([':marker' => $bulkMarker . '%']);
    $assert((int) $bulkCountStmt->fetchColumn() === $bulkTotal, 'Fixture em massa inserida corretamente (' . $bulkTotal . ' títulos)');

    $reportAll = $recRepo->reportRows($companyId, array_merge($baseFilters, ['client_id' => $clientId]), 2000);
    $assert(count($reportAll['rows']) > 100, 'reportRows() retorna mais de 100 linhas quando o total filtrado excede esse valor (não herda o teto de paginate())');
    $assert($reportAll['truncated'] === false, 'reportRows() não sinaliza truncamento quando o limite de segurança comporta todos os registros');

    $reportSmallLimit = $recRepo->reportRows($companyId, array_merge($baseFilters, ['client_id' => $clientId]), 5);
    $assert($reportSmallLimit['truncated'] === true, 'reportRows() sinaliza truncamento explicitamente quando o teto de segurança é atingido');
} finally {
    $pdo->rollBack();
}

return $failures;
