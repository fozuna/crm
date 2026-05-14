<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Services\FinanceService;
use App\Services\ProjectAutomationService;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

function execStmt(string $sql, array $params = []): int
{
    $pdo = DB::pdo();
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    return (int) $pdo->lastInsertId();
}

function fetchOne(string $sql, array $params = []): ?array
{
    $pdo = DB::pdo();
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

try {
    $pdo = DB::pdo();

    $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($db === '') {
        echo "SKIP\n";
        exit(0);
    }
    $need = [
        ['users', 'role'],
        ['projects', 'workflow_phase'],
        ['finance_installments', 'paid_amount'],
    ];
    foreach ($need as $n) {
        [$t, $c] = $n;
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c');
        $stmt->bindValue(':db', $db);
        $stmt->bindValue(':t', $t);
        $stmt->bindValue(':c', $c);
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            echo "SKIP\n";
            exit(0);
        }
    }
    foreach (['project_tasks', 'project_milestones', 'audit_log', 'finance_cancellation_requests', 'finance_payments'] as $t) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t');
        $stmt->bindValue(':db', $db);
        $stmt->bindValue(':t', $t);
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            echo "SKIP\n";
            exit(0);
        }
    }
    $suffix = (string) mt_rand(10000, 99999);

    $adminId = execStmt(
        "INSERT INTO users (name, email, password_hash, is_admin, role, created_at) VALUES ('Admin Teste', :e, :h, 1, 'admin', NOW())",
        [':e' => 'admin.teste.' . $suffix . '@example.com', ':h' => password_hash('SenhaForte#1', PASSWORD_DEFAULT)]
    );
    $financeId = execStmt(
        "INSERT INTO users (name, email, password_hash, is_admin, role, created_at) VALUES ('Finance Teste', :e, :h, 0, 'finance', NOW())",
        [':e' => 'finance.teste.' . $suffix . '@example.com', ':h' => password_hash('SenhaForte#1', PASSWORD_DEFAULT)]
    );

    $clientId = execStmt(
        "INSERT INTO clients (name, email, phone, company, contact_person, status, project_reference, logo_path, logo_mime, logo_original_name, created_at)
         VALUES (:n, NULL, NULL, NULL, NULL, 'ativo', NULL, NULL, NULL, NULL, NOW())",
        [':n' => 'Cliente Teste ' . $suffix]
    );

    $schedule = [
        ['no' => 1, 'kind' => 'parcela', 'due_date' => date('Y-m-d', strtotime('+7 days')), 'amount' => 500.00],
        ['no' => 2, 'kind' => 'parcela', 'due_date' => date('Y-m-d', strtotime('+37 days')), 'amount' => 500.00],
    ];
    $paymentOptions = [[
        'label' => 'Parcelado 2x',
        'total' => 1000.00,
        'snapshot' => [
            'method_id' => 0,
            'method_name' => 'Parcelado 2x',
            'type' => 'parcelado',
            'installments_count' => 2,
            'interval_days' => 30,
            'has_down_payment' => 0,
            'down_payment_percent' => 0,
            'special_terms' => '',
            'schedule' => $schedule,
        ],
    ]];
    $proposalId = execStmt(
        "INSERT INTO proposals (client_id, title, description, notes, status, subtotal, discount_percent, discount_amount, total, payment_method_id, payment_snapshot, payment_options, payment_selected_index, delivery_start, delivery_end, penalty_terms, terms, converted_project, created_at)
         VALUES (:cid, :t, :d, NULL, 'aprovada', 1000, 0, 0, 1000, NULL, NULL, :po, 0, :ds, :de, NULL, NULL, 0, NOW())",
        [
            ':cid' => $clientId,
            ':t' => 'Proposta Teste ' . $suffix,
            ':d' => 'Escopo teste',
            ':po' => json_encode($paymentOptions, JSON_UNESCAPED_UNICODE),
            ':ds' => date('Y-m-d'),
            ':de' => date('Y-m-d', strtotime('+30 days')),
        ]
    );
    execStmt(
        "INSERT INTO proposal_items (proposal_id, description, qty, unit_price, total) VALUES (:pid, 'Serviço A', 1, 600, 600)",
        [':pid' => $proposalId]
    );
    execStmt(
        "INSERT INTO proposal_items (proposal_id, description, qty, unit_price, total) VALUES (:pid, 'Serviço B', 1, 400, 400)",
        [':pid' => $proposalId]
    );
    execStmt(
        "INSERT INTO proposal_milestones (proposal_id, title, due_date, notes, penalty_terms, created_at) VALUES (:pid, 'Marco 1', :d, NULL, NULL, NOW())",
        [':pid' => $proposalId, ':d' => date('Y-m-d', strtotime('+10 days'))]
    );

    $projectId = (new ProjectAutomationService())->createFromApprovedProposal($proposalId, $adminId);
    $project = fetchOne('SELECT id, proposal_id, workflow_phase, total FROM projects WHERE id = :id', [':id' => $projectId]);
    if ($project === null) {
        fail('Projeto não foi criado.');
    }
    if ((int) $project['proposal_id'] !== $proposalId) {
        fail('Projeto não referencia a proposta.');
    }
    if ((string) $project['workflow_phase'] !== 'planejamento') {
        fail('Workflow inicial incorreto.');
    }

    $tasksCount = (int) $pdo->query('SELECT COUNT(*) FROM project_tasks WHERE project_id = ' . (int) $projectId)->fetchColumn();
    if ($tasksCount < 5) {
        fail('Tarefas não foram geradas.');
    }

    $instCount = (int) $pdo->query('SELECT COUNT(*) FROM finance_installments WHERE project_id = ' . (int) $projectId)->fetchColumn();
    if ($instCount !== 2) {
        fail('Parcelas não foram geradas corretamente.');
    }
    $inst1 = fetchOne('SELECT * FROM finance_installments WHERE project_id = :pid ORDER BY installment_no ASC LIMIT 1', [':pid' => $projectId]);
    if ($inst1 === null) {
        fail('Parcela 1 não encontrada.');
    }

    (new FinanceService())->addPayment((int) $inst1['id'], 250.00, 'PIX', null, null, $financeId);
    $inst1b = fetchOne('SELECT paid_amount, status FROM finance_installments WHERE id = :id', [':id' => (int) $inst1['id']]);
    if ($inst1b === null || (float) $inst1b['paid_amount'] < 250.0) {
        fail('Pagamento não foi registrado.');
    }
    if ((string) $inst1b['status'] === 'pago') {
        fail('Parcela não deveria estar paga com pagamento parcial.');
    }

    $cancelErr = '';
    try {
        (new FinanceService())->cancelInstallment((int) $inst1['id'], '', $financeId, false);
    } catch (Throwable $e) {
        $cancelErr = $e->getMessage();
    }
    if ($cancelErr === '') {
        fail('Cancelamento sem motivo deveria falhar.');
    }

    (new FinanceService())->cancelInstallment((int) $inst1['id'], 'Cliente solicitou', $financeId, false);
    $req = fetchOne("SELECT id, status FROM finance_cancellation_requests WHERE installment_id = :iid ORDER BY id DESC LIMIT 1", [':iid' => (int) $inst1['id']]);
    if ($req === null || (string) $req['status'] !== 'pendente') {
        fail('Solicitação de cancelamento não foi criada.');
    }

    $inst1c = fetchOne('SELECT status FROM finance_installments WHERE id = :id', [':id' => (int) $inst1['id']]);
    if ($inst1c === null || (string) $inst1c['status'] === 'cancelado') {
        fail('Parcela foi cancelada sem aprovação.');
    }

    (new FinanceService())->approveCancellationRequest((int) $req['id'], $adminId);
    $inst1d = fetchOne('SELECT status FROM finance_installments WHERE id = :id', [':id' => (int) $inst1['id']]);
    if ($inst1d === null || (string) $inst1d['status'] !== 'cancelado') {
        fail('Cancelamento não foi aplicado após aprovação.');
    }

    $reopenErr = '';
    try {
        (new FinanceService())->reopenInstallment((int) $inst1['id'], $financeId);
    } catch (Throwable $e) {
        $reopenErr = $e->getMessage();
    }
    if ($reopenErr !== '') {
        fail('Reabertura de parcela cancelada deveria funcionar.');
    }

    $inst1e = fetchOne('SELECT status FROM finance_installments WHERE id = :id', [':id' => (int) $inst1['id']]);
    if ($inst1e === null || (string) $inst1e['status'] !== 'reaberto') {
        fail('Reabertura falhou.');
    }

    $auditCount = (int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE (entity_type = 'proposal' AND entity_id = " . (int) $proposalId . ") OR (entity_type = 'project' AND entity_id = " . (int) $projectId . ")")->fetchColumn();
    if ($auditCount < 2) {
        fail('Auditoria não registrou eventos mínimos.');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
} finally {
    try {
        $pdo = DB::pdo();
        if (isset($projectId) && is_int($projectId) && $projectId > 0) {
            $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'project' AND entity_id = " . (int) $projectId);
            $pdo->exec('DELETE FROM projects WHERE id = ' . (int) $projectId);
        }
        if (isset($proposalId) && is_int($proposalId) && $proposalId > 0) {
            $pdo->exec("DELETE FROM audit_log WHERE entity_type = 'proposal' AND entity_id = " . (int) $proposalId);
            $pdo->exec('DELETE FROM proposal_items WHERE proposal_id = ' . (int) $proposalId);
            $pdo->exec('DELETE FROM proposal_milestones WHERE proposal_id = ' . (int) $proposalId);
            $pdo->exec('DELETE FROM proposals WHERE id = ' . (int) $proposalId);
        }
        if (isset($clientId) && is_int($clientId) && $clientId > 0) {
            $pdo->exec('DELETE FROM clients WHERE id = ' . (int) $clientId);
        }
        if (isset($adminId) && is_int($adminId) && $adminId > 0) {
            $pdo->exec('DELETE FROM users WHERE id = ' . (int) $adminId);
        }
        if (isset($financeId) && is_int($financeId) && $financeId > 0) {
            $pdo->exec('DELETE FROM users WHERE id = ' . (int) $financeId);
        }
    } catch (Throwable) {
    }
}
