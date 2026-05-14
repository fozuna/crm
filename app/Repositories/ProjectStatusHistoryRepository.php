<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProjectStatusHistoryRepository
{
    public function listByProject(int $projectId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, project_id, from_phase, to_phase, reason, actor_id, created_at FROM project_status_history WHERE project_id = :id ORDER BY id DESC');
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $projectId, ?string $from, string $to, ?string $reason, int $actorId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO project_status_history (project_id, from_phase, to_phase, reason, actor_id, created_at) VALUES (:pid, :from, :to, :reason, :actor, NOW())');
        $stmt->bindValue(':pid', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':from', $from);
        $stmt->bindValue(':to', $to);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':actor', $actorId, \PDO::PARAM_INT);
        $stmt->execute();
    }
}

