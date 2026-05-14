<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProjectMilestoneRepository
{
    public function listByProject(int $projectId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, project_id, title, due_date, notes, status, created_at, updated_at FROM project_milestones WHERE project_id = :id ORDER BY id ASC');
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $projectId, string $title, ?string $dueDate, ?string $notes): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO project_milestones (project_id, title, due_date, notes, status, created_at, updated_at) VALUES (:pid, :t, :d, :n, :s, NOW(), NOW())');
        $stmt->bindValue(':pid', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':t', $title);
        $stmt->bindValue(':d', $dueDate);
        $stmt->bindValue(':n', $notes);
        $stmt->bindValue(':s', 'pendente');
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM project_milestones WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }
}

