<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\FinanceRevenueRepository;

function hasTable(PDO $pdo, string $t): bool
{
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
    return $st && $st->fetch(PDO::FETCH_NUM) !== false;
}

try {
    $pdo = DB::pdo();
    foreach (['clients','proposals','projects','finance_installments'] as $t) {
        if (!hasTable($pdo, $t)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();
    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato', 'Cliente Datas', 'd@d.com', '0', NOW())");
    $clientId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientId}, 'Prop', 'aprovada', 100.00, NOW())");
    $proposalId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
               VALUES ({$proposalId}, {$clientId}, 'Proj', 'ativo', 'planejamento', 100.00, 0, NOW(), NOW())");
    $projectId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 1, 100.00, 0.00, '2026-04-28', 'pendente', NOW(), NOW())");

    $repo = new FinanceRevenueRepository();
    $rows = $repo->listInstallments(['from' => '01/04/2026', 'to' => '31/05/2026'], 1, 50);
    $list = is_array($rows['rows'] ?? null) ? $rows['rows'] : [];
    if (count($list) < 1) {
        throw new RuntimeException('Filtro dd/mm/yyyy deveria retornar parcelas.');
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

