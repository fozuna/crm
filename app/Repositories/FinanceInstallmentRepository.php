<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;
use App\Services\FinanceTrace;

final class FinanceInstallmentRepository
{
    public function listByProject(int $projectId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, paid_at, canceled_at, canceled_by, cancel_reason, reopened_at, reopened_by, created_at, updated_at FROM finance_installments WHERE project_id = :id ORDER BY installment_no ASC');
        $stmt->bindValue(':id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        FinanceTrace::log('installments.list_by_project', ['project_id' => $projectId, 'count' => is_array($rows) ? count($rows) : 0]);
        return $rows;
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, paid_at, canceled_at, canceled_by, cancel_reason, reopened_at, reopened_by, created_at, updated_at FROM finance_installments WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(int $proposalId, int $projectId, int $no, float $amount, string $dueDate): int
    {
        $pdo = DB::pdo();
        FinanceTrace::log('installments.create.start', ['proposal_id' => $proposalId, 'project_id' => $projectId, 'no' => $no, 'amount' => $amount, 'due' => $dueDate]);
        $stmt = $pdo->prepare('INSERT INTO finance_installments (proposal_id, project_id, installment_no, amount, paid_amount, due_date, status, created_at, updated_at) VALUES (:proposal_id, :project_id, :no, :amount, 0, :due, :status, NOW(), NOW())');
        $stmt->bindValue(':proposal_id', $proposalId, \PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, \PDO::PARAM_INT);
        $stmt->bindValue(':no', $no, \PDO::PARAM_INT);
        $stmt->bindValue(':amount', $amount);
        $stmt->bindValue(':due', $dueDate);
        $stmt->bindValue(':status', 'pendente');
        $stmt->execute();
        $id = (int) $pdo->lastInsertId();
        FinanceTrace::log('installments.create.done', ['id' => $id]);
        return $id;
    }

    public function markPaid(int $id, float $newPaidAmount, string $paidAt, bool $fullyPaid): void
    {
        $pdo = DB::pdo();
        $status = $fullyPaid ? 'pago' : 'pendente';
        $stmt = $pdo->prepare('UPDATE finance_installments SET paid_amount = :paid, status = :status, paid_at = :paid_at, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':paid', $newPaidAmount);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':paid_at', $paidAt);
        $stmt->execute();
    }

    public function markPaidWithStatus(int $id, float $newPaidAmount, string $paidAt, string $status): void
    {
        $allowed = ['pendente', 'reaberto', 'pago', 'cancelado'];
        if (!in_array($status, $allowed, true)) {
            $status = 'pendente';
        }
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE finance_installments SET paid_amount = :paid, status = :status, paid_at = :paid_at, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':paid', $newPaidAmount);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':paid_at', $paidAt);
        $stmt->execute();
    }

    public function updateFields(int $id, array $fields): void
    {
        $allowed = ['due_date', 'amount', 'status', 'paid_at', 'paid_amount'];
        $set = [];
        $params = [':id' => $id];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $fields)) {
                continue;
            }
            $set[] = $k . ' = :' . $k;
            $params[':' . $k] = $fields[$k];
        }
        if (count($set) === 0) {
            return;
        }
        $sql = 'UPDATE finance_installments SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id';
        $pdo = DB::pdo();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $p => $v) {
            $type = is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($p, $v, $type);
        }
        $stmt->execute();
    }

    public function deleteIfNoPayments(int $id): bool
    {
        $pdo = DB::pdo();
        $cnt = (int) $pdo->query('SELECT COUNT(*) FROM finance_payments WHERE installment_id = ' . (int) $id)->fetchColumn();
        if ($cnt > 0) {
            return false;
        }
        $stmt = $pdo->prepare('DELETE FROM finance_installments WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function cancel(int $id, int $actorId, string $reason): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE finance_installments SET status = 'cancelado', canceled_at = NOW(), canceled_by = :actor, cancel_reason = :reason, updated_at = NOW() WHERE id = :id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':actor', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':reason', $reason);
        $stmt->execute();
    }

    public function reopen(int $id, int $actorId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare("UPDATE finance_installments SET status = 'reaberto', reopened_at = NOW(), reopened_by = :actor, updated_at = NOW() WHERE id = :id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':actor', $actorId, \PDO::PARAM_INT);
        $stmt->execute();
    }
}
