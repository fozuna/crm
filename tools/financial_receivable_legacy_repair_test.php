<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\FinancialReceivableService;

function hasTable(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return $stmt && $stmt->fetch(PDO::FETCH_NUM) !== false;
}

function fail(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

try {
    $pdo = DB::pdo();
    foreach (['clients', 'proposals', 'projects', 'finance_installments', 'financial_accounts_receivable', 'financial_categories', 'financial_cost_centers'] as $table) {
        if (!hasTable($pdo, $table)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO financial_categories (id, company_id, name, type, color, active, created_at, updated_at)
                VALUES (2, 1, 'Receita de Projetos', 'receivable', '#10b981', 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()");
    $pdo->exec("INSERT INTO financial_cost_centers (id, company_id, name, code, active, created_at, updated_at)
                VALUES (3, 1, 'Operacoes', 'OPS', 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = NOW()");

    $suffix = (string) random_int(10000, 99999);
    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Cliente Reparo {$suffix}', 'Empresa {$suffix}', 'reparo{$suffix}@test.com', '11999999999', NOW())");
    $clientId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO proposals (client_id, title, status, subtotal, total, created_at) VALUES ({$clientId}, 'Proposta Reparo {$suffix}', 'aprovada', 1000.00, 1000.00, NOW())");
    $proposalId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
                VALUES ({$proposalId}, {$clientId}, 'Projeto Reparo {$suffix}', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");
    $projectId = (int) $pdo->lastInsertId();

    $due1 = date('Y-m-d', strtotime('+5 days'));
    $due2 = date('Y-m-d', strtotime('+35 days'));
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
                VALUES ({$proposalId}, {$projectId}, 1, 400.00, 0.00, '{$due1}', 'pendente', NOW(), NOW())");
    $inst1 = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
                VALUES ({$proposalId}, {$projectId}, 2, 600.00, 0.00, '{$due2}', 'pendente', NOW(), NOW())");
    $inst2 = (int) $pdo->lastInsertId();

    $service = new FinancialReceivableService();
    $service->generateFromProject($projectId, 1);

    $service->create([
        'company_id' => 1,
        'project_id' => $projectId,
        'client_id' => $clientId,
        'source_installment_id' => $inst1,
        'installment_number' => 1,
        'total_installments' => 2,
        'title' => 'Duplicado proposital 1/2',
        'description' => 'Recebivel corrompido',
        'original_amount' => 200.00,
        'discount_amount' => 0,
        'interest_amount' => 0,
        'fine_amount' => 0,
        'due_date' => $due1,
        'issue_date' => date('Y-m-d'),
        'competence_date' => $due1,
        'status' => 'pending',
        'category_id' => 2,
        'cost_center_id' => 3,
        'created_by' => 1,
        'updated_by' => 1,
    ], 1);

    $service->create([
        'company_id' => 1,
        'project_id' => $projectId,
        'client_id' => $clientId,
        'source_installment_id' => $inst2,
        'installment_number' => 2,
        'total_installments' => 2,
        'title' => 'Duplicado proposital 2/2',
        'description' => 'Recebivel corrompido',
        'original_amount' => 300.00,
        'discount_amount' => 0,
        'interest_amount' => 0,
        'fine_amount' => 0,
        'due_date' => $due2,
        'issue_date' => date('Y-m-d'),
        'competence_date' => $due2,
        'status' => 'pending',
        'category_id' => 2,
        'cost_center_id' => 3,
        'created_by' => 1,
        'updated_by' => 1,
    ], 1);

    $repair = $service->repairProjectReceivables($projectId, 1, '127.0.0.1');
    if (($repair['deleted'] ?? 0) < 2) {
        fail('Reparo nao removeu os duplicados esperados.');
    }

    $stmt = $pdo->prepare('SELECT source_installment_id, COUNT(*) AS qty, MIN(original_amount) AS min_amount, MAX(original_amount) AS max_amount, MIN(installment_number) AS min_installment, MAX(installment_number) AS max_installment
                           FROM financial_accounts_receivable
                           WHERE project_id = :project_id AND deleted_at IS NULL
                           GROUP BY source_installment_id
                           ORDER BY source_installment_id ASC');
    $stmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if (count($rows) !== 2) {
        fail('Projeto reparado deveria possuir exatamente dois recebiveis ativos.');
    }

    foreach ($rows as $row) {
        if ((int) ($row['qty'] ?? 0) !== 1) {
            fail('Cada parcela legada deve ficar associada a apenas um recebivel ativo.');
        }
    }

    if ((float) ($rows[0]['min_amount'] ?? 0) !== 400.0 || (float) ($rows[1]['min_amount'] ?? 0) !== 600.0) {
        fail('Os valores dos recebiveis reparados nao refletem as parcelas legadas.');
    }

    if ((int) ($rows[0]['min_installment'] ?? 0) !== 1 || (int) ($rows[1]['min_installment'] ?? 0) !== 2) {
        fail('Os numeros das parcelas reparadas ficaram incorretos.');
    }

    $pdo->rollBack();
    echo "OK\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fail($e->getMessage());
}
