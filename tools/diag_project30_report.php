<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$projectId = (int) ($argv[1] ?? 30);
$from = (string) ($argv[2] ?? '2026-04-01');
$to = (string) ($argv[3] ?? '2026-06-30');

try {
    $pdo = DB::pdo();

    $proj = $pdo->prepare('SELECT id, title, proposal_id, client_id, status, created_at FROM projects WHERE id = :id');
    $proj->bindValue(':id', $projectId, PDO::PARAM_INT);
    $proj->execute();
    $p = $proj->fetch(PDO::FETCH_ASSOC);
    if (!is_array($p)) {
        echo "Projeto {$projectId} não encontrado.\n";
        exit(0);
    }

    echo "PROJECT\n";
    foreach ($p as $k => $v) {
        echo $k . ': ' . (string) $v . "\n";
    }

    $propId = (int) ($p['proposal_id'] ?? 0);
    if ($propId > 0) {
        $prop = $pdo->prepare('SELECT id, client_id, status, total, created_at FROM proposals WHERE id = :id');
        $prop->bindValue(':id', $propId, PDO::PARAM_INT);
        $prop->execute();
        $pr = $prop->fetch(PDO::FETCH_ASSOC);
        echo "\nPROPOSAL\n";
        if (is_array($pr)) {
            foreach ($pr as $k => $v) {
                echo $k . ': ' . (string) $v . "\n";
            }
        } else {
            echo "proposal {$propId} não encontrada\n";
        }
    }

    echo "\nINSTALLMENTS (by project_id)\n";
    $st = $pdo->prepare('SELECT id, proposal_id, project_id, installment_no, amount, paid_amount, due_date, status FROM finance_installments WHERE project_id = :pid ORDER BY due_date ASC, installment_no ASC');
    $st->bindValue(':pid', $projectId, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    echo 'count: ' . count($rows) . "\n";
    foreach ($rows as $r) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\nREPORT JOIN (proposal_id->proposals approved + date range)\n";
    $q = $pdo->prepare(
        "SELECT COUNT(*)
         FROM finance_installments fi
         INNER JOIN proposals pr ON pr.id = fi.proposal_id
         WHERE fi.project_id = :pid
           AND pr.status = 'aprovada'
           AND fi.due_date BETWEEN :f AND :t"
    );
    $q->bindValue(':pid', $projectId, PDO::PARAM_INT);
    $q->bindValue(':f', $from);
    $q->bindValue(':t', $to);
    $q->execute();
    echo 'count: ' . (string) $q->fetchColumn() . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

