<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class FinancePaymentRepository
{
    public function listByInstallment(int $installmentId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, installment_id, amount, method, reference, note, paid_at, created_by, created_at FROM finance_payments WHERE installment_id = :id ORDER BY id DESC');
        $stmt->bindValue(':id', $installmentId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(int $installmentId, float $amount, string $paidAt, ?string $method, ?string $reference, ?string $note, int $actorId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO finance_payments (installment_id, amount, method, reference, note, paid_at, created_by, created_at) VALUES (:iid, :amount, :method, :ref, :note, :paid_at, :actor, NOW())');
        $stmt->bindValue(':iid', $installmentId, \PDO::PARAM_INT);
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':method', $method);
        $stmt->bindValue(':ref', $reference);
        $stmt->bindValue(':note', $note);
        $stmt->bindValue(':paid_at', $paidAt);
        $stmt->bindValue(':actor', $actorId, \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }
}

