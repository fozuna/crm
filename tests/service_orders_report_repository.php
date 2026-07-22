<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Core\DB;
use App\Repositories\ServiceOrderRepository;

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
 * Regressão do P03 (CRM_AUDIT.md): ServiceOrderService::report() pedia 500 linhas,
 * mas ServiceOrderRepository::paginate() capava silenciosamente em 100. Este teste
 * escreve registros reais dentro de uma transação nunca commitada (rollback no
 * finally) para provar, contra o banco de verdade, que o relatório deixou de
 * truncar sem depender de dados reais existentes no ambiente.
 */

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId <= 0) {
        $pdo->exec("INSERT INTO clients (name, created_at) VALUES ('Cliente Teste P03', NOW())");
        $clientId = (int) $pdo->lastInsertId();
    }

    $marker = 'TESTE_P03_' . bin2hex(random_bytes(4));
    $total = 120;
    $sequenceBase = (int) ($pdo->query('SELECT COALESCE(MAX(numero_sequencial), 0) FROM servicos_avulsos')->fetchColumn());
    $statuses = ['aberto', 'em_andamento', 'concluido', 'faturado'];

    $insert = $pdo->prepare(
        'INSERT INTO servicos_avulsos (
            numero_sequencial, numero_os, service_name, client_id, type, status,
            opened_at, billable, base_amount, discount_amount, surcharge_amount, final_amount,
            created_at, updated_at
        ) VALUES (
            :seq, :numero_os, :service_name, :client_id, :type, :status,
            :opened_at, :billable, 0, 0, 0, 0, NOW(), NOW()
        )'
    );

    $expectedConcluido = 0;
    $expectedBillable = 0;
    for ($i = 1; $i <= $total; $i++) {
        $seq = $sequenceBase + $i;
        $status = $statuses[$i % count($statuses)];
        $billable = ($i % 3 === 0) ? 1 : 0;
        if ($status === 'concluido') {
            $expectedConcluido++;
        }
        if ($billable === 1) {
            $expectedBillable++;
        }
        $insert->execute([
            ':seq' => $seq,
            ':numero_os' => 'OS-TESTE-' . $seq,
            ':service_name' => $marker . ' #' . $i,
            ':client_id' => $clientId,
            ':type' => 'suporte',
            ':status' => $status,
            ':opened_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
            ':billable' => $billable,
        ]);
    }

    $repo = new ServiceOrderRepository();

    $report = $repo->reportRows(['q' => $marker]);
    $assert((int) $report['total'] === $total, "reportRows() conta o total real de OS filtradas ({$report['total']} de {$total})");
    $assert(count($report['rows']) === $total, 'reportRows() retorna todas as OS filtradas, sem truncar em 100 (' . count($report['rows']) . ' retornadas)');

    $statusReport = $repo->reportRows(['q' => $marker, 'status' => 'concluido']);
    $assert(count($statusReport['rows']) === $expectedConcluido, 'Filtro de status do relatório retorna exatamente o subconjunto esperado');

    $billableReport = $repo->reportRows(['q' => $marker, 'billable' => '1']);
    $assert(count($billableReport['rows']) === $expectedBillable, 'Filtro de faturável do relatório retorna exatamente o subconjunto esperado');

    $notBillableReport = $repo->reportRows(['q' => $marker, 'billable' => '0']);
    $assert(count($notBillableReport['rows']) === $total - $expectedBillable, 'Filtro de não faturável não elimina OS sem lançamento financeiro nem sem anexos');

    $clientReport = $repo->reportRows(['q' => $marker, 'client_id' => $clientId]);
    $assert(count($clientReport['rows']) === $total, 'Filtro de cliente mantém todas as OS compatíveis (join opcional não elimina registros)');

    $emptyFilterReport = $repo->reportRows(['q' => $marker, 'client_id' => 0, 'status' => '', 'billable' => '']);
    $assert(count($emptyFilterReport['rows']) === $total, 'Filtros vazios não excluem registros do relatório');

    $listing = $repo->paginate(['q' => $marker], 1, 500);
    $assert((int) $listing['total'] === $total, 'Listagem operacional (paginate) contabiliza o total real de OS');
    $assert(count($listing['rows']) === 100, 'Listagem operacional permanece intencionalmente limitada a 100 por página (não é o bug do P03 — o bug era o relatório herdar esse teto)');
} finally {
    $pdo->rollBack();
}

return $failures;
