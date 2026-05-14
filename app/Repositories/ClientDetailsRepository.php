<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ClientDetailsRepository
{
    public function projects(int $clientId): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT pr.id, pr.title, pr.status, pr.workflow_phase, pr.progress_percent, pr.created_at, pr.total AS value
                FROM projects pr
                WHERE pr.client_id = :id
                ORDER BY pr.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $clientId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function proposals(int $clientId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, title, status, total, created_at FROM proposals WHERE client_id = :id ORDER BY id DESC');
        $stmt->bindValue(':id', $clientId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
