<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\FinanceService;
use App\Services\Money;

function hasTable(PDO $pdo, string $t): bool
{
    $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($t));
    return $st && $st->fetch(PDO::FETCH_NUM) !== false;
}

try {
    $pdo = DB::pdo();
    foreach (['clients','proposals','projects','finance_installments','finance_payments','payment_methods'] as $t) {
        if (!hasTable($pdo, $t)) {
            echo "SKIP\n";
            exit(0);
        }
    }


    $clientId = 0;
    $proposalId = 0;
    $projectId = 0;
    $installmentId = 0;

    try {
        $pdo->exec("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Contato', 'Cliente Pagamento', 'pay@t.com', '0', NOW())");
        $clientId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientId}, 'Prop', 'aprovada', 2000.00, NOW())");
        $proposalId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at)
                   VALUES ({$proposalId}, {$clientId}, 'Proj', 'ativo', 'planejamento', 2000.00, 0, NOW(), NOW())");
        $projectId = (int) $pdo->lastInsertId();

        $pdo->exec("INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at)
                   VALUES ({$proposalId}, {$projectId}, 1, 2000.00, 0.00, '2026-05-01', 'pendente', NOW(), NOW())");
        $installmentId = (int) $pdo->lastInsertId();

        $amount = Money::parseBRL('R$ 1.500,50');
        (new FinanceService())->addPayment($installmentId, $amount, 'pix', null, null, 1);

        $row = $pdo->query('SELECT amount FROM finance_payments WHERE installment_id = ' . (int) $installmentId . ' ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $saved = is_array($row) ? (float) ($row['amount'] ?? 0) : 0.0;
        if (abs($saved - 1500.50) > 0.0001) {
            throw new RuntimeException('Pagamento salvo incorreto. Esperado 1500.50, obtido ' . $saved);
        }

        $row2 = $pdo->query('SELECT paid_amount FROM finance_installments WHERE id = ' . (int) $installmentId)->fetch(PDO::FETCH_ASSOC);
        $paid = is_array($row2) ? (float) ($row2['paid_amount'] ?? 0) : 0.0;
        if (abs($paid - 1500.50) > 0.0001) {
            throw new RuntimeException('paid_amount incorreto. Esperado 1500.50, obtido ' . $paid);
        }
    } finally {
        if ($installmentId > 0) {
            $pdo->exec('DELETE FROM finance_payments WHERE installment_id = ' . (int) $installmentId);
            $pdo->exec('DELETE FROM finance_installments WHERE id = ' . (int) $installmentId);
        }
        if ($projectId > 0) {
            $pdo->exec('DELETE FROM projects WHERE id = ' . (int) $projectId);
        }
        if ($proposalId > 0) {
            $pdo->exec('DELETE FROM proposals WHERE id = ' . (int) $proposalId);
        }
        if ($clientId > 0) {
            $pdo->exec('DELETE FROM clients WHERE id = ' . (int) $clientId);
        }
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
