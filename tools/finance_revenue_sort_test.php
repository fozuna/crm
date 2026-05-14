<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\FinanceRevenueRepository;

function hasTableSort(PDO $pdo, string $t): bool
{
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
    return $st && $st->fetch(PDO::FETCH_NUM) !== false;
}

try {
    $pdo = DB::pdo();
    foreach (['clients', 'proposals', 'projects', 'finance_installments'] as $t) {
        if (!hasTableSort($pdo, $t)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato Sort', 'Cliente Zeta', 'sort@test.com', '0', NOW())");
    $clientZ = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato Sort 2', 'Cliente Alfa', 'sort2@test.com', '0', NOW())");
    $clientA = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientZ}, 'Projeto Z', 'aprovada', 1000.00, NOW())");
    $proposalZ = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientA}, 'Projeto A', 'aprovada', 1000.00, NOW())");
    $proposalA = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
               VALUES ({$proposalZ}, {$clientZ}, 'Projeto Z', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");
    $projectZ = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
               VALUES ({$proposalA}, {$clientA}, 'Projeto A', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");
    $projectA = (int) $pdo->lastInsertId();

    $baseDate = date('Y-m-15');
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalZ}, {$projectZ}, 1, 200.00, 0.00, '{$baseDate}', 'pendente', NOW(), NOW())");
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalA}, {$projectA}, 1, 100.00, 0.00, '{$baseDate}', 'pendente', NOW(), NOW())");

    $repo = new FinanceRevenueRepository();

    $byAmount = $repo->listInstallments(['from' => date('Y-m-01'), 'to' => date('Y-m-t'), 'sort' => 'amount', 'direction' => 'desc'], 1, 50);
    $rowsAmount = is_array($byAmount['rows'] ?? null) ? $byAmount['rows'] : [];
    if (count($rowsAmount) < 2 || (float) $rowsAmount[0]['amount'] < (float) $rowsAmount[1]['amount']) {
        throw new RuntimeException('Ordenação por valor desc não respeitada.');
    }

    $byClient = $repo->listInstallments(['from' => date('Y-m-01'), 'to' => date('Y-m-t'), 'sort' => 'client', 'direction' => 'asc'], 1, 50);
    $rowsClient = is_array($byClient['rows'] ?? null) ? $byClient['rows'] : [];
    if (count($rowsClient) < 2 || strcmp((string) $rowsClient[0]['client_company'], (string) $rowsClient[1]['client_company']) > 0) {
        throw new RuntimeException('Ordenação por cliente asc não respeitada.');
    }

    $pdo->rollBack();
    echo "OK\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

