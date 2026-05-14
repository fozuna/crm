<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

function out(string $label, $value): void
{
    echo str_pad($label, 38) . ': ' . (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
}

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

try {
    $pdo = DB::pdo();

    $today = date('Y-m-d');
    $monthFrom = date('Y-m-01');
    $monthTo = date('Y-m-t');
    $yearFrom = date('Y-m-01', strtotime('-11 months'));

    out('Hoje', $today);
    out('Mês atual (from)', $monthFrom);
    out('Mês atual (to)', $monthTo);
    out('Últimos 12 meses (from)', $yearFrom);

    $counts = [
        'finance_installments_total' => "SELECT COUNT(*) FROM finance_installments",
        'finance_payments_total' => "SELECT COUNT(*) FROM finance_payments",
        'proposals_total' => "SELECT COUNT(*) FROM proposals",
        'proposals_aprovada_total' => "SELECT COUNT(*) FROM proposals WHERE status='aprovada'",
        'installments_join_aprovada_total' => "SELECT COUNT(*) FROM finance_installments fi INNER JOIN proposals pr ON pr.id=fi.proposal_id WHERE pr.status='aprovada'",
        'payments_join_aprovada_total' => "SELECT COUNT(*) FROM finance_payments fp INNER JOIN finance_installments fi ON fi.id=fp.installment_id INNER JOIN proposals pr ON pr.id=fi.proposal_id WHERE pr.status='aprovada'",
    ];

    foreach ($counts as $label => $sql) {
        $v = (int) $pdo->query($sql)->fetchColumn();
        out($label, $v);
    }

    $rangeSql = "SELECT
        MIN(fi.due_date) AS min_due,
        MAX(fi.due_date) AS max_due
      FROM finance_installments fi
      INNER JOIN proposals pr ON pr.id = fi.proposal_id
      WHERE pr.status='aprovada'";
    $range = $pdo->query($rangeSql)->fetch();
    $range = is_array($range) ? $range : [];
    out('Aprovadas: min due_date', (string) ($range['min_due'] ?? ''));
    out('Aprovadas: max due_date', (string) ($range['max_due'] ?? ''));

    $monthCountSql = "SELECT COUNT(*) FROM finance_installments fi
      INNER JOIN proposals pr ON pr.id = fi.proposal_id
      WHERE pr.status='aprovada' AND fi.due_date >= :from AND fi.due_date <= :to";
    $stmt = $pdo->prepare($monthCountSql);
    $stmt->bindValue(':from', $monthFrom);
    $stmt->bindValue(':to', $monthTo);
    $stmt->execute();
    out('Aprovadas: parcelas no mês atual', (int) $stmt->fetchColumn());

    $yearCountSql = "SELECT COUNT(*) FROM finance_installments fi
      INNER JOIN proposals pr ON pr.id = fi.proposal_id
      WHERE pr.status='aprovada' AND fi.due_date >= :from AND fi.due_date <= :to";
    $stmt2 = $pdo->prepare($yearCountSql);
    $stmt2->bindValue(':from', $yearFrom);
    $stmt2->bindValue(':to', $monthTo);
    $stmt2->execute();
    out('Aprovadas: parcelas em 12 meses', (int) $stmt2->fetchColumn());

    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}

