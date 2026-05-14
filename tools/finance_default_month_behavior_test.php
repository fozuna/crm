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
    foreach (['clients','proposals','projects','finance_installments','payment_methods'] as $t) {
        if (!hasTable($pdo, $t)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato', 'Cliente Mes Atual', 'm@t.com', '0', NOW())");
    $clientId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientId}, 'Prop Mes', 'aprovada', 300.00, NOW())");
    $proposalId = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
               VALUES ({$proposalId}, {$clientId}, 'Proj Mes', 'ativo', 'planejamento', 300.00, 0, NOW(), NOW())");
    $projectId = (int) $pdo->lastInsertId();

    $curStart = date('Y-m-01');
    $curEnd = date('Y-m-t');
    $curMid = date('Y-m-15');
    $nextMonth = date('Y-m-15', strtotime('+1 month', strtotime($curMid)));

    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 1, 100.00, 0.00, '{$curMid}', 'pendente', NOW(), NOW())");
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 2, 100.00, 50.00, '{$curMid}', 'pendente', NOW(), NOW())");
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, paid_at, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 3, 100.00, 100.00, '{$curMid}', 'pago', NOW(), NOW(), NOW())");
    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 4, 100.00, 0.00, '{$nextMonth}', 'pendente', NOW(), NOW())");

    $repo = new FinanceRevenueRepository();

    $default = $repo->listInstallments(['client_id' => $clientId], 1, 200);
    $rows = is_array($default['rows'] ?? null) ? $default['rows'] : [];
    if (count($rows) !== 3) {
        throw new RuntimeException('Default deve retornar apenas parcelas do mês atual. Obtido: ' . count($rows));
    }

    $filtered = $repo->listInstallments(['client_id' => $clientId, 'from' => $curStart, 'to' => $curEnd], 1, 200);
    $rows2 = is_array($filtered['rows'] ?? null) ? $filtered['rows'] : [];
    if (count($rows2) !== 3) {
        throw new RuntimeException('Filtro mês atual deve excluir mês futuro. Obtido: ' . count($rows2));
    }

    $manual = $repo->listInstallments(['client_id' => $clientId, 'from' => $curStart, 'to' => date('Y-m-t', strtotime($nextMonth))], 1, 200);
    $rows3 = is_array($manual['rows'] ?? null) ? $manual['rows'] : [];
    if (count($rows3) !== 4) {
        throw new RuntimeException('Filtro manual deve respeitar período e incluir mês futuro. Obtido: ' . count($rows3));
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
