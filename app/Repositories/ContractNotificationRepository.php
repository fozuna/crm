<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ContractNotificationRepository
{
    public function listByContract(int $contractId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, contract_id, type, recipient_name, recipient_email, channel, status, message, metadata, created_at, sent_at FROM contract_notifications WHERE contract_id = :contract_id ORDER BY id DESC');
        $stmt->bindValue(':contract_id', $contractId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $contractId, array $payload): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO contract_notifications (contract_id, type, recipient_name, recipient_email, channel, status, message, metadata, created_at, sent_at) VALUES (:contract_id, :type, :recipient_name, :recipient_email, :channel, :status, :message, :metadata, NOW(), :sent_at)');
        $stmt->bindValue(':contract_id', $contractId, \PDO::PARAM_INT);
        $stmt->bindValue(':type', (string) $payload['type']);
        $stmt->bindValue(':recipient_name', $payload['recipient_name']);
        $stmt->bindValue(':recipient_email', $payload['recipient_email']);
        $stmt->bindValue(':channel', (string) ($payload['channel'] ?? 'system'));
        $stmt->bindValue(':status', (string) ($payload['status'] ?? 'pending'));
        $stmt->bindValue(':message', (string) $payload['message']);
        $stmt->bindValue(':metadata', $payload['metadata']);
        $stmt->bindValue(':sent_at', $payload['sent_at']);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }
}
