<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProjectRepository
{
    public function list(array $filters = []): array
    {
        $pdo = DB::pdo();
        $where = [];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'pr.status = :status';
            $params[':status'] = $status;
        }
        $phase = trim((string) ($filters['workflow_phase'] ?? ''));
        if ($phase !== '') {
            $where[] = 'pr.workflow_phase = :phase';
            $params[':phase'] = $phase;
        }
        $clientId = (int) ($filters['client_id'] ?? 0);
        if ($clientId > 0) {
            $where[] = 'pr.client_id = :client_id';
            $params[':client_id'] = $clientId;
        }
        $owner = (int) ($filters['owner_user_id'] ?? 0);
        if ($owner > 0) {
            $where[] = 'pr.owner_user_id = :owner';
            $params[':owner'] = $owner;
        }

        $sql = 'SELECT pr.id, pr.title, pr.status, pr.workflow_phase, pr.total, pr.progress_percent, pr.created_at, pr.updated_at, c.name AS client_name
                FROM projects pr
                JOIN clients c ON c.id = pr.client_id';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY pr.id DESC';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = \PDO::PARAM_STR;
            if (in_array($k, [':client_id', ':owner'], true)) {
                $type = \PDO::PARAM_INT;
            }
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT pr.*, c.name AS client_name
                FROM projects pr
                JOIN clients c ON c.id = pr.client_id
                WHERE pr.id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByProposal(int $proposalId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE proposal_id = :id LIMIT 1');
        $stmt->bindValue(':id', $proposalId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function approvedByClient(int $clientId): array
    {
        if ($clientId <= 0) {
            return [];
        }

        $pdo = DB::pdo();
        $sql = 'SELECT pr.id, pr.title, pr.client_id, pr.status, pr.workflow_phase, pr.proposal_id, p.status AS proposal_status
                FROM projects pr
                INNER JOIN proposals p ON p.id = pr.proposal_id
                WHERE pr.client_id = :client_id
                  AND p.status = :proposal_status
                ORDER BY pr.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':proposal_status', 'aprovada');
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function belongsToClientApprovedProposal(int $projectId, int $clientId): bool
    {
        if ($projectId <= 0 || $clientId <= 0) {
            return false;
        }

        $pdo = DB::pdo();
        $sql = 'SELECT COUNT(*)
                FROM projects pr
                INNER JOIN proposals p ON p.id = pr.proposal_id
                WHERE pr.id = :project_id
                  AND pr.client_id = :client_id
                  AND p.status = :proposal_status';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':project_id', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':proposal_status', 'aprovada');
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public function createFromProposal(array $proposal, int $actorId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("INSERT INTO projects (proposal_id, client_id, title, status, workflow_phase, description, owner_user_id, start_date, end_date, total, progress_percent, created_at, updated_at) VALUES (:proposal_id, :client_id, :title, :status, :phase, :desc, :owner, :start, :end, :total, 0, NOW(), NOW())");
        $stmt->bindValue(':proposal_id', (int) ($proposal['id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':client_id', (int) ($proposal['client_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':title', (string) ($proposal['title'] ?? ''));
        $stmt->bindValue(':status', 'ativo');
        $stmt->bindValue(':phase', 'planejamento');
        $stmt->bindValue(':desc', ($proposal['description'] ?? null));
        $stmt->bindValue(':owner', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':start', $proposal['delivery_start'] ?? null);
        $stmt->bindValue(':end', $proposal['delivery_end'] ?? null);
        $stmt->bindValue(':total', (float) ($proposal['total'] ?? 0));
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function updatePhase(int $projectId, string $phase): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE projects SET workflow_phase = :p, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':p', $phase);
        $stmt->execute();
    }

    public function updateProgress(int $projectId, float $progressPercent): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE projects SET progress_percent = :p, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':p', max(0, min(100, $progressPercent)));
        $stmt->execute();
    }
}
