<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

class ServiceOrderApprovalNotificationRepository
{
    public function create(int $serviceOrderId, int $approvalId, array $payload): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO servicos_avulsos_aprovacao_notificacoes (
                servico_avulso_id,
                aprovacao_id,
                channel,
                notification_type,
                recipient_name,
                recipient_target,
                status,
                subject,
                message,
                metadata,
                created_at,
                sent_at
            ) VALUES (
                :service_order_id,
                :approval_id,
                :channel,
                :notification_type,
                :recipient_name,
                :recipient_target,
                :status,
                :subject,
                :message,
                :metadata,
                NOW(),
                :sent_at
            )'
        );
        $stmt->bindValue(':service_order_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->bindValue(':approval_id', $approvalId, \PDO::PARAM_INT);
        $stmt->bindValue(':channel', (string) $payload['channel']);
        $stmt->bindValue(':notification_type', (string) $payload['notification_type']);
        $stmt->bindValue(':recipient_name', $payload['recipient_name'] ?? null, ($payload['recipient_name'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':recipient_target', (string) $payload['recipient_target']);
        $stmt->bindValue(':status', (string) ($payload['status'] ?? 'pendente'));
        $stmt->bindValue(':subject', $payload['subject'] ?? null, ($payload['subject'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':message', (string) $payload['message']);
        $metadata = isset($payload['metadata']) && is_array($payload['metadata'])
            ? json_encode($payload['metadata'], JSON_UNESCAPED_UNICODE)
            : null;
        $stmt->bindValue(':metadata', $metadata, $metadata === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':sent_at', $payload['sent_at'] ?? null, ($payload['sent_at'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->execute();
        return (int) DB::pdo()->lastInsertId();
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT id, servico_avulso_id, aprovacao_id, channel, notification_type, recipient_name, recipient_target, status, subject, message, metadata, created_at, sent_at
             FROM servicos_avulsos_aprovacao_notificacoes
             WHERE servico_avulso_id = :service_order_id
             ORDER BY id DESC'
        );
        $stmt->bindValue(':service_order_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
