<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

$from = (string) ($argv[1] ?? '');
$to = (string) ($argv[2] ?? '');

try {
    $pdo = DB::pdo();

    $counts = [];
    $counts['finance_installments_total'] = (int) $pdo->query('SELECT COUNT(*) FROM finance_installments')->fetchColumn();
    $counts['projects_total'] = (int) $pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $counts['proposals_total'] = (int) $pdo->query('SELECT COUNT(*) FROM proposals')->fetchColumn();
    $counts['proposals_aprovada'] = (int) $pdo->query("SELECT COUNT(*) FROM proposals WHERE status = 'aprovada'")->fetchColumn();

    $counts['installments_join_aprovada'] = (int) $pdo->query(
        "SELECT COUNT(*)
         FROM finance_installments fi
         JOIN projects p ON p.id = fi.project_id
         JOIN proposals pr ON pr.id = p.proposal_id
         WHERE pr.status = 'aprovada'"
    )->fetchColumn();

    $dates = $pdo->query('SELECT MIN(due_date) AS min_due, MAX(due_date) AS max_due FROM finance_installments')->fetch();
    $dates = is_array($dates) ? $dates : [];

    $statuses = $pdo->query('SELECT status, COUNT(*) AS c FROM finance_installments GROUP BY status')->fetchAll();

    $rangeCount = null;
    if ($from !== '' && $to !== '') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM finance_installments WHERE due_date BETWEEN :f AND :t');
        $stmt->bindValue(':f', $from);
        $stmt->bindValue(':t', $to);
        $stmt->execute();
        $rangeCount = (int) $stmt->fetchColumn();
    }

    echo "COUNTS\n";
    foreach ($counts as $k => $v) {
        echo $k . ': ' . $v . "\n";
    }
    echo "\n";
    echo "DATES\n";
    echo 'min_due: ' . (string) ($dates['min_due'] ?? '') . "\n";
    echo 'max_due: ' . (string) ($dates['max_due'] ?? '') . "\n";
    if ($rangeCount !== null) {
        echo 'range_count(' . $from . ' .. ' . $to . '): ' . $rangeCount . "\n";
    }
    echo "\n";
    echo "STATUSES\n";
    foreach ($statuses as $s) {
        echo (string) ($s['status'] ?? '') . ': ' . (string) ($s['c'] ?? '') . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

