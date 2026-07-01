<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ClientInteractionRepositoryContract;
use App\Core\DB;

final class ClientInteractionRepository implements ClientInteractionRepositoryContract
{
    public function listByClient(int $clientId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, kind, note, created_at FROM client_interactions WHERE client_id = :id ORDER BY id DESC');
        $stmt->bindValue(':id', $clientId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $clientId, string $kind, string $note): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO client_interactions (client_id, kind, note, created_at) VALUES (:client_id, :kind, :note, NOW())');
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':note', $note);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function createHistorical(int $clientId, string $kind, string $note, string $createdAt): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO client_interactions (client_id, kind, note, created_at) VALUES (:client_id, :kind, :note, :created_at)');
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':note', $note);
        $stmt->bindValue(':created_at', $createdAt);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }
}
