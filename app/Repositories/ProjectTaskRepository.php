<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProjectTaskRepository
{
    public function listByProject(int $projectId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, project_id, phase, title, description, assigned_user_id, status, due_date, order_no, created_at, updated_at FROM project_tasks WHERE project_id = :id ORDER BY phase ASC, order_no ASC, id ASC');
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $projectId, string $phase, string $title, ?string $description, ?int $assignedUserId, ?string $dueDate, int $orderNo): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO project_tasks (project_id, phase, title, description, assigned_user_id, status, due_date, order_no, created_at, updated_at) VALUES (:pid, :phase, :title, :desc, :assigned, :status, :due, :order_no, NOW(), NOW())');
        $stmt->bindValue(':pid', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':phase', $phase);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':desc', $description);
        $stmt->bindValue(':assigned', $assignedUserId, $assignedUserId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':status', 'pendente');
        $stmt->bindValue(':due', $dueDate);
        $stmt->bindValue(':order_no', $orderNo, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function updateStatus(int $taskId, string $status): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE project_tasks SET status = :s, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $taskId, \PDO::PARAM_INT);
        $stmt->bindValue(':s', $status);
        $stmt->execute();
    }

    public function delete(int $taskId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM project_tasks WHERE id = :id');
        $stmt->bindValue(':id', $taskId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function statsByProject(int $projectId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS done FROM project_tasks WHERE project_id = :id");
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $row = is_array($row) ? $row : [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'done' => (int) ($row['done'] ?? 0),
        ];
    }
}

