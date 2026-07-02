<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ServiceOrderHistoryRepositoryContract;
use App\Core\DB;

final class ServiceOrderHistoryRepository implements ServiceOrderHistoryRepositoryContract
{
    public function create(
        int $serviceOrderId,
        string $action,
        ?int $actorId,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $message = null,
        ?array $metadata = null
    ): int {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO servicos_avulsos_historico (
                servico_avulso_id, actor_id, action, field_name, old_value, new_value, message, metadata, created_at
            ) VALUES (
                :servico_avulso_id, :actor_id, :action, :field_name, :old_value, :new_value, :message, :metadata, NOW()
            )'
        );
        $stmt->bindValue(':servico_avulso_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->bindValue(':actor_id', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':field_name', $fieldName, $fieldName === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':old_value', $this->stringify($oldValue), $oldValue === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':new_value', $this->stringify($newValue), $newValue === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, $message === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $metadataJson = $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        $stmt->bindValue(':metadata', $metadataJson, $metadataJson === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->execute();

        return (int) DB::pdo()->lastInsertId();
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT h.*, u.name AS actor_name
             FROM servicos_avulsos_historico h
             LEFT JOIN users u ON u.id = h.actor_id
             WHERE h.servico_avulso_id = :id
             ORDER BY h.id DESC'
        );
        $stmt->bindValue(':id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $decoded = json_decode((string) ($row['metadata'] ?? ''), true);
            $row['metadata'] = is_array($decoded) ? $decoded : [];
        }
        return $rows;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: null;
        }
        return (string) $value;
    }
}
