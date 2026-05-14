<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class AuditLogRepository
{
    public function create(string $entityType, int $entityId, string $action, ?int $actorId, ?array $data): void
    {
        $pdo = DB::pdo();
        $json = null;
        if (is_array($data)) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $stmt = $pdo->prepare('INSERT INTO audit_log (entity_type, entity_id, action, actor_id, data, created_at) VALUES (:t, :id, :a, :actor, :d, NOW())');
        $stmt->bindValue(':t', $entityType);
        $stmt->bindValue(':id', $entityId, \PDO::PARAM_INT);
        $stmt->bindValue(':a', $action);
        $stmt->bindValue(':actor', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':d', $json);
        $stmt->execute();
    }

    public function list(?string $entityType, ?int $entityId, int $limit = 200): array
    {
        $pdo = DB::pdo();
        $limit = max(1, min(500, $limit));

        $where = [];
        $params = [];
        if (is_string($entityType) && $entityType !== '') {
            $where[] = 'entity_type = :t';
            $params[':t'] = $entityType;
        }
        if (is_int($entityId) && $entityId > 0) {
            $where[] = 'entity_id = :id';
            $params[':id'] = $entityId;
        }

        $sql = 'SELECT id, entity_type, entity_id, action, actor_id, data, created_at FROM audit_log';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, $k === ':id' ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $decoded = json_decode((string) ($r['data'] ?? ''), true);
            $r['data'] = is_array($decoded) ? $decoded : [];
        }
        return $rows;
    }
}

