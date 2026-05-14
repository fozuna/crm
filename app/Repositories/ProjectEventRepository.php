<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProjectEventRepository
{
    public function listByProject(int $projectId, int $limit = 200): array
    {
        $pdo = DB::pdo();
        $limit = max(1, min(500, $limit));
        $stmt = $pdo->prepare('SELECT id, project_id, kind, message, payload, created_by, created_at FROM project_events WHERE project_id = :id ORDER BY id DESC LIMIT ' . $limit);
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $decoded = json_decode((string) ($r['payload'] ?? ''), true);
            $r['payload'] = is_array($decoded) ? $decoded : [];
        }
        return $rows;
    }

    public function create(int $projectId, string $kind, string $message, ?array $payload, ?int $actorId): void
    {
        $pdo = DB::pdo();
        $json = null;
        if (is_array($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
        $stmt = $pdo->prepare('INSERT INTO project_events (project_id, kind, message, payload, created_by, created_at) VALUES (:pid, :k, :m, :p, :actor, NOW())');
        $stmt->bindValue(':pid', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':k', $kind);
        $stmt->bindValue(':m', $message);
        $stmt->bindValue(':p', $json);
        $stmt->bindValue(':actor', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
    }
}

