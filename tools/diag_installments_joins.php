<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;

try {
    $pdo = DB::pdo();
    $a = (int) $pdo->query("SELECT COUNT(*) FROM finance_installments fi JOIN projects p ON p.id = fi.project_id JOIN proposals pr ON pr.id = p.proposal_id WHERE pr.status = 'aprovada'")->fetchColumn();
    $b = (int) $pdo->query("SELECT COUNT(*) FROM finance_installments fi JOIN projects p ON p.id = fi.project_id JOIN proposals pr ON pr.id = p.proposal_id JOIN clients c ON c.id = p.client_id WHERE pr.status = 'aprovada'")->fetchColumn();
    echo "join projects+proposals: {$a}\n";
    echo "join + clients: {$b}\n";

    $bad = $pdo->query("SELECT p.id, p.title, p.client_id, p.proposal_id FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE c.id IS NULL ORDER BY p.id ASC")->fetchAll();
    echo "projects sem client válido: " . count($bad) . "\n";
    foreach ($bad as $r) {
        echo "- project_id=" . (string)($r['id'] ?? '') . " client_id=" . (string)($r['client_id'] ?? '') . " proposal_id=" . (string)($r['proposal_id'] ?? '') . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

