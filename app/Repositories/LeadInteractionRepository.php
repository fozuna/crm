<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LeadInteractionRepositoryContract;
use App\Core\DB;

final class LeadInteractionRepository implements LeadInteractionRepositoryContract
{
    public function listByLead(int $leadId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT li.id, li.kind, li.note, li.created_by, li.created_at, u.name AS actor_name
            FROM lead_interactions li
            LEFT JOIN users u ON u.id = li.created_by
            WHERE li.lead_id = :lead_id
            ORDER BY li.id DESC');
        $stmt->bindValue(':lead_id', $leadId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $leadId, string $kind, string $note, ?int $createdBy): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO lead_interactions (lead_id, kind, note, created_by, created_at) VALUES (:lead_id, :kind, :note, :created_by, NOW())');
        $stmt->bindValue(':lead_id', $leadId, \PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':note', $note);
        $stmt->bindValue(':created_by', $createdBy, $createdBy === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }
}
