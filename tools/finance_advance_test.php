<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\ClientRepository;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProposalRepository;
use App\Services\FinanceService;

$pdo = DB::pdo();

$clientId = 0;
$proposalId = 0;
$projectId = 0;
$installmentIds = [];

try {
    $clientRepo = new ClientRepository();
    $clientId = $clientRepo->create([
        'name' => 'Cliente Teste Financeiro',
        'email' => 'financeiro.teste@exemplo.com',
        'phone' => '000000000',
        'company' => 'Empresa Teste Financeiro',
        'contact_person' => 'Contato',
        'status' => 'ativo',
        'project_reference' => 'TESTE-FIN',
    ]);

    $proposalRepo = new ProposalRepository();
    $proposalId = $proposalRepo->create([
        'client_id' => $clientId,
        'title' => 'Proposta Teste Financeiro',
        'description' => str_repeat('Descrição válida. ', 5),
        'notes' => '',
        'status' => 'aprovada',
        'subtotal' => 300.00,
        'discount_percent' => 0.0,
        'discount_amount' => 0.0,
        'total' => 300.00,
        'payment_method_id' => null,
        'payment_snapshot' => json_encode(['method_id' => null], JSON_UNESCAPED_UNICODE),
        'payment_options' => json_encode([], JSON_UNESCAPED_UNICODE),
        'payment_selected_index' => 0,
        'delivery_start' => date('Y-m-d'),
        'delivery_end' => date('Y-m-d', strtotime('+10 day')),
        'penalty_terms' => '',
        'terms' => 'Termos',
        'items' => [
            ['service_id' => 0, 'is_bonus' => 0, 'catalog_price' => null, 'description' => 'Item', 'qty' => 1, 'unit_price' => 300.00, 'total' => 300.00],
        ],
        'milestones' => [],
    ]);

    $proposal = $proposalRepo->find($proposalId);
    if ($proposal === null) {
        throw new RuntimeException('Falha ao recuperar proposta.');
    }

    $projectRepo = new ProjectRepository();
    $projectId = $projectRepo->createFromProposal($proposal, 1);

    $instRepo = new FinanceInstallmentRepository();
    $due1 = date('Y-m-d', strtotime('+10 day'));
    $due2 = date('Y-m-d', strtotime('+40 day'));
    $due3 = date('Y-m-d', strtotime('+70 day'));
    $due4 = date('Y-m-d', strtotime('+100 day'));
    $i1 = $instRepo->create($proposalId, $projectId, 1, 100.00, $due1);
    $i2 = $instRepo->create($proposalId, $projectId, 2, 100.00, $due2);
    $i3 = $instRepo->create($proposalId, $projectId, 3, 100.00, $due3);
    $i4 = $instRepo->create($proposalId, $projectId, 4, 10.00, $due4);
    $installmentIds = [$i1, $i2, $i3, $i4];

    (new FinanceService())->updateInstallment($i4, [
        'due_date' => date('Y-m-d', strtotime('+101 day')),
        'amount' => 11.11,
        'status' => 'reaberto',
    ], 1);
    $r4 = $instRepo->find($i4);
    if (!$r4) {
        throw new RuntimeException('Falha ao recuperar parcela 4.');
    }
    if ((string) $r4['due_date'] !== date('Y-m-d', strtotime('+101 day'))) {
        throw new RuntimeException('Vencimento da parcela 4 não foi atualizado.');
    }
    if (abs((float) $r4['amount'] - 11.11) > 0.01) {
        throw new RuntimeException('Valor da parcela 4 não foi atualizado.');
    }
    if ((string) $r4['status'] !== 'reaberto') {
        throw new RuntimeException('Status da parcela 4 não foi atualizado.');
    }

    $updateErr = '';
    try {
        (new FinanceService())->updateInstallment($i4, ['status' => 'invalido'], 1);
    } catch (Throwable $e) {
        $updateErr = $e->getMessage();
    }
    if ($updateErr === '') {
        throw new RuntimeException('Atualização com status inválido deveria falhar.');
    }

    (new FinanceService())->deleteInstallment($i4, 1);
    $r4b = $instRepo->find($i4);
    if ($r4b) {
        throw new RuntimeException('Parcela 4 deveria ter sido excluída.');
    }

    $res = (new FinanceService())->advanceAmount($i1, 150.00, 'pix', 'ADV', 'teste', 1);
    if (!is_array($res) || !is_array($res['affected'] ?? null) || count($res['affected']) < 2) {
        throw new RuntimeException('Esperado adiantamento distribuído em 2 parcelas.');
    }

    $r1 = $instRepo->find($i1);
    $r2 = $instRepo->find($i2);
    $r3 = $instRepo->find($i3);
    if (!$r1 || !$r2 || !$r3) {
        throw new RuntimeException('Falha ao recuperar parcelas.');
    }

    if ((float) $r1['paid_amount'] < 99.99 || (string) $r1['status'] !== 'pago') {
        throw new RuntimeException('Parcela 1 deveria ficar paga.');
    }
    if (substr((string) ($r1['paid_at'] ?? ''), 0, 10) >= (string) ($r1['due_date'] ?? '')) {
        throw new RuntimeException('Parcela 1 deveria ter paid_at antes do vencimento (adiantada).');
    }
    if ((float) $r2['paid_amount'] < 49.99 || (float) $r2['paid_amount'] > 50.01) {
        throw new RuntimeException('Parcela 2 deveria ficar parcialmente paga em 50.00.');
    }
    if ((string) $r2['status'] === 'pago' || (string) $r2['status'] === 'adiantado') {
        throw new RuntimeException('Parcela 2 não deveria ficar quitada.');
    }
    if ((float) $r3['paid_amount'] > 0.001) {
        throw new RuntimeException('Parcela 3 não deveria ser afetada.');
    }

    $delErr = '';
    try {
        (new FinanceService())->deleteInstallment($i1, 1);
    } catch (Throwable $e) {
        $delErr = $e->getMessage();
    }
    if ($delErr === '') {
        throw new RuntimeException('Exclusão de parcela paga deveria falhar.');
    }

    $payErr = '';
    try {
        (new FinanceService())->addPayment($i2, 0.0, 'pix', null, null, 1);
    } catch (Throwable $e) {
        $payErr = $e->getMessage();
    }
    if ($payErr === '') {
        throw new RuntimeException('Pagamento com valor inválido deveria falhar.');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
} finally {
    try {
        if (count($installmentIds) > 0) {
            $ids = implode(',', array_map('intval', $installmentIds));
            $pdo->exec('DELETE FROM finance_payments WHERE installment_id IN (' . $ids . ')');
            $pdo->exec('DELETE FROM finance_installments WHERE id IN (' . $ids . ')');
        }
        if ($projectId > 0) {
            $pdo->exec('DELETE FROM project_events WHERE project_id = ' . (int) $projectId);
            $pdo->exec('DELETE FROM projects WHERE id = ' . (int) $projectId);
        }
        if ($proposalId > 0) {
            $pdo->exec('DELETE FROM proposal_items WHERE proposal_id = ' . (int) $proposalId);
            $pdo->exec('DELETE FROM proposal_milestones WHERE proposal_id = ' . (int) $proposalId);
            $pdo->exec('DELETE FROM proposals WHERE id = ' . (int) $proposalId);
        }
        if ($clientId > 0) {
            $pdo->exec('DELETE FROM clients WHERE id = ' . (int) $clientId);
        }
    } catch (Throwable) {
    }
}
