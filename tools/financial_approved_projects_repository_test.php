<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\ProjectRepository;

function hasTable(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return $stmt && $stmt->fetch(PDO::FETCH_NUM) !== false;
}

function insertRow(string $sql): int
{
    $pdo = DB::pdo();
    $pdo->exec($sql);
    return (int) $pdo->lastInsertId();
}

try {
    $pdo = DB::pdo();
    foreach (['clients', 'proposals', 'projects'] as $table) {
        if (!hasTable($pdo, $table)) {
            echo "SKIP\n";
            exit(0);
        }
    }

    $suffix = (string) mt_rand(10000, 99999);
    $clientA = 0;
    $clientB = 0;
    $proposalApprovedA = 0;
    $proposalDraftA = 0;
    $proposalApprovedB = 0;
    $projectApprovedA = 0;
    $projectDraftA = 0;
    $projectApprovedB = 0;

    try {
        $clientA = insertRow("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Cliente Repo {$suffix}', 'Cliente Repo {$suffix}', 'repo{$suffix}@a.com', '0', NOW())");
        $clientB = insertRow("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Cliente Repo B {$suffix}', 'Cliente Repo B {$suffix}', 'repob{$suffix}@a.com', '0', NOW())");

        $proposalApprovedA = insertRow("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientA}, 'Proposta Aprovada {$suffix}', 'aprovada', 1000.00, NOW())");
        $proposalDraftA = insertRow("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientA}, 'Proposta Rascunho {$suffix}', 'rascunho', 1000.00, NOW())");
        $proposalApprovedB = insertRow("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientB}, 'Proposta B {$suffix}', 'aprovada', 1000.00, NOW())");

        $projectApprovedA = insertRow("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at) VALUES ({$proposalApprovedA}, {$clientA}, 'Projeto Elegivel {$suffix}', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");
        $projectDraftA = insertRow("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at) VALUES ({$proposalDraftA}, {$clientA}, 'Projeto Nao Elegivel {$suffix}', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");
        $projectApprovedB = insertRow("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at) VALUES ({$proposalApprovedB}, {$clientB}, 'Projeto Outro Cliente {$suffix}', 'ativo', 'planejamento', 1000.00, 0, NOW(), NOW())");

        $repo = new ProjectRepository();
        $rows = $repo->approvedByClient($clientA);
        $ids = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $rows);
        sort($ids);

        if ($ids !== [$projectApprovedA]) {
            throw new RuntimeException('A consulta de projetos aprovados por cliente retornou projetos indevidos.');
        }

        if (!$repo->belongsToClientApprovedProposal($projectApprovedA, $clientA)) {
            throw new RuntimeException('Validação do projeto elegível falhou.');
        }

        if ($repo->belongsToClientApprovedProposal($projectDraftA, $clientA)) {
            throw new RuntimeException('Projeto ligado a proposta não aprovada não deveria ser elegível.');
        }

        if ($repo->belongsToClientApprovedProposal($projectApprovedB, $clientA)) {
            throw new RuntimeException('Projeto de outro cliente não deveria ser elegível.');
        }
    } finally {
        foreach ([$projectApprovedA, $projectDraftA, $projectApprovedB] as $projectId) {
            if ($projectId > 0) {
                $pdo->exec('DELETE FROM projects WHERE id = ' . $projectId);
            }
        }
        foreach ([$proposalApprovedA, $proposalDraftA, $proposalApprovedB] as $proposalId) {
            if ($proposalId > 0) {
                $pdo->exec('DELETE FROM proposals WHERE id = ' . $proposalId);
            }
        }
        foreach ([$clientA, $clientB] as $clientId) {
            if ($clientId > 0) {
                $pdo->exec('DELETE FROM clients WHERE id = ' . $clientId);
            }
        }
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
