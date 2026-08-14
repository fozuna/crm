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
 * Regressão da tela principal de Ordens de Serviço (SPRINT_OS_BILLING_AND_FLOW.md):
 * ServiceOrderController::index() passava a listagem paginada sob a chave 'data',
 * que colide com o parâmetro $data de View::render() e é descartada por
 * extract($data, EXTR_SKIP) — a tela sempre renderizava "0 registros" mesmo com OS
 * cadastradas. A correção renomeou a chave para 'listing' e passou a alimentar
 * indicadores via ServiceOrderRepository::summary(). Este teste prova, contra o
 * banco de verdade (rollback garantido no finally), que paginate() e summary()
 * batem exatamente com o que foi inserido — a mesma consulta que agora alimenta
 * /ordens-servico.
 */

$pdo = DB::pdo();
$pdo->beginTransaction();

try {
    $clientId = (int) ($pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn() ?: 0);
    if ($clientId <= 0) {
        $pdo->exec("INSERT INTO clients (name, created_at) VALUES ('Cliente Teste Listagem', NOW())");
        $clientId = (int) $pdo->lastInsertId();
    }

    $marker = 'TESTE_LISTAGEM_' . bin2hex(random_bytes(4));
    $sequenceBase = (int) ($pdo->query('SELECT COALESCE(MAX(numero_sequencial), 0) FROM servicos_avulsos')->fetchColumn());

    $insert = $pdo->prepare(
        'INSERT INTO servicos_avulsos (
            numero_sequencial, numero_os, service_name, client_id, type, status,
            opened_at, completed_at, billable, base_amount, discount_amount, surcharge_amount, final_amount,
            created_at, updated_at
        ) VALUES (
            :seq, :numero_os, :service_name, :client_id, :type, :status,
            :opened_at, :completed_at, :billable, 0, 0, 0, :final_amount, NOW(), NOW()
        )'
    );

    $rows = [
        ['status' => 'aberto', 'billable' => 0, 'final' => 0, 'completed' => null],
        ['status' => 'em_andamento', 'billable' => 0, 'final' => 0, 'completed' => null],
        ['status' => 'concluido', 'billable' => 1, 'final' => 500.0, 'completed' => '2026-07-02 12:00:00'],
        ['status' => 'faturado', 'billable' => 1, 'final' => 1200.0, 'completed' => '2026-07-03 10:00:00'],
        ['status' => 'faturado', 'billable' => 1, 'final' => 800.0, 'completed' => '2026-07-04 18:00:00'],
    ];

    foreach ($rows as $i => $row) {
        $seq = $sequenceBase + $i + 1;
        $insert->execute([
            ':seq' => $seq,
            ':numero_os' => $marker . '-' . $seq,
            ':service_name' => $marker . ' serviço ' . $i,
            ':client_id' => $clientId,
            ':type' => 'suporte',
            ':status' => $row['status'],
            ':opened_at' => '2026-07-01 08:00:00',
            ':completed_at' => $row['completed'],
            ':billable' => $row['billable'],
            ':final_amount' => $row['final'],
        ]);
    }

    $repo = new ServiceOrderRepository();
    $filters = ['q' => $marker];

    $listing = $repo->paginate($filters, 1, 20);
    $assert((int) $listing['total'] === count($rows), 'paginate() retorna o total real de OS filtradas (mesma chamada usada por /ordens-servico)');
    $assert(count($listing['rows']) === count($rows), 'paginate() retorna as linhas correspondentes ao filtro de busca');

    $summary = $repo->summary($filters);
    $assert((int) $summary['aberto'] === 1, 'summary() contabiliza OS em aberto corretamente');
    $assert((int) $summary['em_andamento'] === 1, 'summary() contabiliza OS em andamento corretamente');
    $assert((int) $summary['concluido'] === 1, 'summary() contabiliza OS concluídas corretamente');
    $assert((int) $summary['faturado'] === 2, 'summary() contabiliza OS faturadas corretamente');
    $assert(abs((float) $summary['valor_faturado'] - 2000.0) < 0.01, 'summary() soma o valor faturado apenas das OS com status faturado');
    $assert((float) $summary['tempo_medio_horas'] > 0, 'summary() calcula tempo médio a partir de abertura/conclusão');

    $emptyFilterSummary = $repo->summary(['q' => $marker, 'status' => 'aberto']);
    $assert((int) $emptyFilterSummary['aberto'] === 1 && (int) $emptyFilterSummary['faturado'] === 0, 'summary() respeita os mesmos filtros aplicados à listagem');
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

return $failures;
