<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

class ServiceOrderApprovalEventRepository
{
    public function create(int $serviceOrderId, int $approvalId, string $eventType, array $payload = []): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO servicos_avulsos_aprovacao_eventos (
                servico_avulso_id,
                aprovacao_id,
                event_type,
                actor_identifier,
                ip_address,
                user_agent,
                geo_summary,
                metadata,
                created_at
            ) VALUES (
                :service_order_id,
                :approval_id,
                :event_type,
                :actor_identifier,
                :ip_address,
                :user_agent,
                :geo_summary,
                :metadata,
                NOW()
            )'
        );
        $stmt->bindValue(':service_order_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->bindValue(':approval_id', $approvalId, \PDO::PARAM_INT);
        $stmt->bindValue(':event_type', $eventType);
        $stmt->bindValue(':actor_identifier', $payload['actor_identifier'] ?? null, ($payload['actor_identifier'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':ip_address', $payload['ip_address'] ?? null, ($payload['ip_address'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':user_agent', $payload['user_agent'] ?? null, ($payload['user_agent'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':geo_summary', $payload['geo_summary'] ?? null, ($payload['geo_summary'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $metadata = isset($payload['metadata']) && is_array($payload['metadata'])
            ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE)
            : null;
        $stmt->bindValue(':metadata', $metadata, $metadata === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->execute();
        return (int) DB::pdo()->lastInsertId();
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT id, servico_avulso_id, aprovacao_id, event_type, actor_identifier, ip_address, user_agent, geo_summary, metadata, created_at
             FROM servicos_avulsos_aprovacao_eventos
             WHERE servico_avulso_id = :service_order_id
             ORDER BY id DESC'
        );
        $stmt->bindValue(':service_order_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
