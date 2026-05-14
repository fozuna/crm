<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Controllers\FinancialModuleApiController;
use App\Core\DB;
use App\Core\Request;

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

function invokeEndpoint(int $clientId): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --invoke ' . $clientId;
    $output = shell_exec($command);
    if (!is_string($output) || trim($output) === '') {
        throw new RuntimeException('A chamada do endpoint não retornou payload.');
    }

    $decoded = json_decode(trim($output), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('O endpoint retornou JSON inválido: ' . $output);
    }

    return $decoded;
}

if (($argv[1] ?? '') === '--invoke') {
    $clientId = (int) ($argv[2] ?? 0);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    $_POST = [];
    (new FinancialModuleApiController())->approvedProjectsByClient(new Request(), ['clientId' => $clientId]);
    exit(0);
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
    $clientWithApproved = 0;
    $clientWithoutApproved = 0;
    $proposalApproved = 0;
    $proposalDraft = 0;
    $projectApproved = 0;
    $projectDraft = 0;

    try {
        $clientWithApproved = insertRow("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Cliente API {$suffix}', 'Cliente API {$suffix}', 'api{$suffix}@a.com', '0', NOW())");
        $clientWithoutApproved = insertRow("INSERT INTO clients (name, company, email, phone, created_at) VALUES ('Cliente Sem Projeto {$suffix}', 'Cliente Sem Projeto {$suffix}', 'apib{$suffix}@a.com', '0', NOW())");

        $proposalApproved = insertRow("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientWithApproved}, 'Proposta Aprovada API {$suffix}', 'aprovada', 2000.00, NOW())");
        $proposalDraft = insertRow("INSERT INTO proposals (client_id, title, status, total, created_at) VALUES ({$clientWithApproved}, 'Proposta Rascunho API {$suffix}', 'rascunho', 2000.00, NOW())");

        $projectApproved = insertRow("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at) VALUES ({$proposalApproved}, {$clientWithApproved}, 'Projeto Aprovado API {$suffix}', 'ativo', 'planejamento', 2000.00, 0, NOW(), NOW())");
        $projectDraft = insertRow("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, total, progress_percent, created_at, updated_at) VALUES ({$proposalDraft}, {$clientWithApproved}, 'Projeto Bloqueado API {$suffix}', 'ativo', 'planejamento', 2000.00, 0, NOW(), NOW())");

        $approvedPayload = invokeEndpoint($clientWithApproved);
        $approvedProjects = $approvedPayload['data']['projects'] ?? [];
        if (($approvedPayload['ok'] ?? false) !== true || count($approvedProjects) !== 1) {
            throw new RuntimeException('O endpoint deveria retornar exatamente um projeto aprovado para o cliente elegível.');
        }
        if ((int) ($approvedProjects[0]['id'] ?? 0) !== $projectApproved) {
            throw new RuntimeException('O endpoint retornou projeto incorreto para o cliente elegível.');
        }

        $emptyPayload = invokeEndpoint($clientWithoutApproved);
        if (($emptyPayload['ok'] ?? false) !== true || count((array) ($emptyPayload['data']['projects'] ?? [])) !== 0) {
            throw new RuntimeException('O endpoint deveria retornar lista vazia para cliente sem projetos aprovados.');
        }
        if (trim((string) ($emptyPayload['data']['message'] ?? '')) === '') {
            throw new RuntimeException('O endpoint deveria retornar mensagem informativa para cliente sem projetos aprovados.');
        }

        $invalidPayload = invokeEndpoint(0);
        if (($invalidPayload['ok'] ?? false) !== true || count((array) ($invalidPayload['data']['projects'] ?? [])) !== 0) {
            throw new RuntimeException('O endpoint deveria responder com lista vazia para cliente inválido.');
        }
    } finally {
        foreach ([$projectApproved, $projectDraft] as $projectId) {
            if ($projectId > 0) {
                $pdo->exec('DELETE FROM projects WHERE id = ' . $projectId);
            }
        }
        foreach ([$proposalApproved, $proposalDraft] as $proposalId) {
            if ($proposalId > 0) {
                $pdo->exec('DELETE FROM proposals WHERE id = ' . $proposalId);
            }
        }
        foreach ([$clientWithApproved, $clientWithoutApproved] as $clientId) {
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
