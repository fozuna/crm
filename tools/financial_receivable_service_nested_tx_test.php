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

    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Cliente Financeiro', 'Empresa Fluxo', 'financeiro@test.com', '11999999999', NOW())");
    $clientId = (int) $pdo->lastInsertId();

    $service = new FinancialReceivableService();
    $created = $service->create([
        'company_id' => 1,
        'client_id' => $clientId,
        'title' => 'Titulo transacional',
        'description' => 'Teste de transacao externa',
        'original_amount' => 1000.00,
        'discount_amount' => 0,
        'interest_amount' => 0,
        'fine_amount' => 0,
        'due_date' => date('Y-m-15'),
        'issue_date' => date('Y-m-d'),
        'competence_date' => date('Y-m-d'),
        'status' => 'pending',
        'total_installments' => 2,
        'recurrence_interval_months' => 1,
        'created_by' => 1,
        'updated_by' => 1,
    ], 1);

    if (count($created) !== 2) {
        throw new RuntimeException('Parcelamento em transacao externa nao gerou a quantidade esperada.');
    }

    $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientId}, 'Projeto gerado', 'aprovada', 1200.00, NOW())");
    $proposalId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
                VALUES ({$proposalId}, {$clientId}, 'Projeto gerado', 'ativo', 'planejamento', 1200.00, 0, NOW(), NOW())");
    $projectId = (int) $pdo->lastInsertId();

    $dueDate = date('Y-m-d', strtotime('+10 days'));
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
                VALUES ({$proposalId}, {$projectId}, 1, 1200.00, 0.00, '{$dueDate}', 'pendente', NOW(), NOW())");
    $installmentId = (int) $pdo->lastInsertId();

    $service->generateFromProject($projectId, 1);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM financial_accounts_receivable WHERE source_installment_id = :installment_id AND deleted_at IS NULL');
    $stmt->bindValue(':installment_id', $installmentId, PDO::PARAM_INT);
    $stmt->execute();
    $count = (int) $stmt->fetchColumn();
    if ($count !== 1) {
        throw new RuntimeException('Geracao automatica de recebivel a partir do projeto nao criou exatamente um titulo.');
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
