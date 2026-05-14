<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\FinanceRevenueRepository;

function needTable(PDO $pdo, string $t): bool
{
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
    return $st && $st->fetch(PDO::FETCH_NUM) !== false;
}

try {
    $pdo = DB::pdo();
    foreach (['clients','proposals','projects','finance_installments','finance_payments'] as $t) {
        if (!needTable($pdo, $t)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $pdo->beginTransaction();

    $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato', 'Cliente Teste', 'x@x.com', '0', NOW())");
    $clientId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientId}, 'Prop', 'aprovada', 1000.00, NOW())");
    $proposalId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
               VALUES ({$proposalId}, {$clientId}, 'Proj', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");
    $projectId = (int) $pdo->lastInsertId();

    $today = date('Y-m-d');
    $past = date('Y-m-d', strtotime('-10 days'));
    $future = date('Y-m-d', strtotime('+10 days'));

    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 1, 500.00, 0.00, '{$past}', 'pendente', NOW(), NOW())");
    $inst1 = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
               VALUES ({$proposalId}, {$projectId}, 2, 500.00, 0.00, '{$future}', 'pendente', NOW(), NOW())");
    $inst2 = (int) $pdo->lastInsertId();

    $pdo->exec("UPDATE finance_installments SET paid_amount = 300.00 WHERE id = {$inst2}");
    $pdo->exec("INSERT INTO finance_payments (installment_id, amount, method, reference, note, paid_at, created_by, created_at)
               VALUES ({$inst2}, 300.00, 'pix', 'ref', NULL, NOW(), 1, NOW())");

    $repo = new FinanceRevenueRepository();
    $from = date('Y-m-01', strtotime($past));
    $to = date('Y-m-t', strtotime($future));
    $res = $repo->metrics(['client_id' => $clientId, 'from' => $from, 'to' => $to]);
    $tot = $res['totals'] ?? [];
    $open = (float) ($tot['receivable'] ?? -1);
    $overdue = (float) ($tot['overdue'] ?? -1);
    $received = (float) ($tot['received'] ?? -1);

    if (abs($open - 700.0) > 0.001) {
        throw new RuntimeException('Receivable esperado 700.00, obtido ' . $open);
    }
    if (abs($overdue - 500.0) > 0.001) {
        throw new RuntimeException('Overdue esperado 500.00, obtido ' . $overdue);
    }
    if (abs($received - 300.0) > 0.001) {
        throw new RuntimeException('Received esperado 300.00, obtido ' . $received);
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
