<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinanceCancellationRepository
{
    public function create(int $installmentId, int $requestedBy, string $reason, float $penalty, float $interest, float $total): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("INSERT INTO finance_cancellation_requests (installment_id, requested_by, reason, status, reviewed_by, reviewed_at, penalty_amount, interest_amount, total_amount, created_at)
                               VALUES (:iid, :req, :reason, 'pendente', NULL, NULL, :penalty, :interest, :total, NOW())");
        $stmt->bindValue(':iid', $installmentId, \PDO::PARAM_INT);
        $stmt->bindValue(':req', $requestedBy, \PDO::PARAM_INT);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':penalty', $penalty);
        $stmt->bindValue(':interest', $interest);
        $stmt->bindValue(':total', $total);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function listPendingByProject(int $projectId): array
    {
        $pdo = DB::pdo();
        $sql = "SELECT r.id, r.installment_id, r.requested_by, r.reason, r.status, r.reviewed_by, r.reviewed_at, r.penalty_amount, r.interest_amount, r.total_amount, r.created_at,
                       i.installment_no
                FROM finance_cancellation_requests r
                JOIN finance_installments i ON i.id = r.installment_id
                WHERE i.project_id = :pid AND r.status = 'pendente'
                ORDER BY r.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':pid', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT * FROM finance_cancellation_requests WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function approve(int $id, int $reviewerId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE finance_cancellation_requests SET status = 'aprovada', reviewed_by = :rb, reviewed_at = NOW() WHERE id = :id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':rb', $reviewerId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function reject(int $id, int $reviewerId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE finance_cancellation_requests SET status = 'rejeitada', reviewed_by = :rb, reviewed_at = NOW() WHERE id = :id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':rb', $reviewerId, \PDO::PARAM_INT);
        $stmt->execute();
    }
}

