<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinancialAuditLogRepository
{
    public function create(int $companyId, ?int $receivableId, ?int $actorId, ?string $ip, string $action, ?array $before, ?array $after, ?array $metadata = null): void
    {
        $stmt = DB::pdo()->prepare('INSERT INTO financial_audit_logs (company_id, receivable_id, actor_id, ip_address, action, before_data, after_data, metadata, created_at) VALUES (:company_id, :receivable_id, :actor_id, :ip_address, :action, :before_data, :after_data, :metadata, NOW())');
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':receivable_id', $receivableId, $receivableId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':actor_id', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':ip_address', $ip);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':before_data', $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE) : null, $before !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(':after_data', $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE) : null, $after !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->bindValue(':metadata', $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null, $metadata !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function listByReceivable(int $companyId, int $receivableId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT id, actor_id, ip_address, action, before_data, after_data, metadata, created_at
                FROM financial_audit_logs
                WHERE company_id = :company_id AND receivable_id = :receivable_id
                ORDER BY id DESC
                LIMIT ' . $limit;
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':receivable_id', $receivableId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['before_data'] = json_decode((string) ($row['before_data'] ?? ''), true) ?: [];
            $row['after_data'] = json_decode((string) ($row['after_data'] ?? ''), true) ?: [];
            $row['metadata'] = json_decode((string) ($row['metadata'] ?? ''), true) ?: [];
        }
        return $rows;
    }
}
