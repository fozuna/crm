<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LeadStageHistoryRepositoryContract;
use App\Core\DB;
use App\Services\LeadStages;

final class LeadStageHistoryRepository implements LeadStageHistoryRepositoryContract
{
    public function create(int $leadId, ?string $fromStage, string $toStage, ?int $actorId, string $action = 'move', ?string $note = null): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO lead_stage_history (lead_id, from_stage, to_stage, action, note, actor_id, created_at) VALUES (:lead_id, :from_stage, :to_stage, :action, :note, :actor_id, NOW())');
        $stmt->bindValue(':lead_id', $leadId, \PDO::PARAM_INT);
        $stmt->bindValue(':from_stage', $fromStage, $fromStage === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':to_stage', $toStage);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':note', $note, $note === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':actor_id', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function listByLead(int $leadId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT h.id, h.from_stage, h.to_stage, h.action, h.note, h.actor_id, h.created_at, u.name AS actor_name
            FROM lead_stage_history h
            LEFT JOIN users u ON u.id = h.actor_id
            WHERE h.lead_id = :lead_id
            ORDER BY h.id DESC');
        $stmt->bindValue(':lead_id', $leadId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['from_stage_label'] = ($row['from_stage'] ?? null) !== null ? LeadStages::label((string) $row['from_stage']) : 'Inicial';
            $row['to_stage_label'] = LeadStages::label((string) ($row['to_stage'] ?? ''));
        }

        return $rows;
    }
}
