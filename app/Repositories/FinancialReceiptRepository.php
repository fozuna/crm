<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;
use App\DTOs\FinancialReceiptData;

final class FinancialReceiptRepository
{
    public function create(FinancialReceiptData $data): int
    {
        $stmt = DB::pdo()->prepare('INSERT INTO financial_receipts (receivable_id, amount_received, interest_amount, fine_amount, discount_amount, payment_method, payment_date, transaction_reference, bank_reference, receipt_file_path, observation, created_by, created_at) VALUES (:receivable_id, :amount_received, :interest_amount, :fine_amount, :discount_amount, :payment_method, :payment_date, :transaction_reference, :bank_reference, :receipt_file_path, :observation, :created_by, NOW())');
        $stmt->bindValue(':receivable_id', $data->receivableId, \PDO::PARAM_INT);
        $stmt->bindValue(':amount_received', $data->amountReceived);
        $stmt->bindValue(':interest_amount', $data->interestAmount);
        $stmt->bindValue(':fine_amount', $data->fineAmount);
        $stmt->bindValue(':discount_amount', $data->discountAmount);
        $stmt->bindValue(':payment_method', $data->paymentMethod);
        $stmt->bindValue(':payment_date', $data->paymentDate);
        $stmt->bindValue(':transaction_reference', $data->transactionReference);
        $stmt->bindValue(':bank_reference', $data->bankReference);
        $stmt->bindValue(':receipt_file_path', $data->receiptFilePath);
        $stmt->bindValue(':observation', $data->observation);
        $stmt->bindValue(':created_by', $data->createdBy > 0 ? $data->createdBy : null, $data->createdBy > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->execute();
        return (int) DB::pdo()->lastInsertId();
    }

    public function reverse(int $receiptId, int $actorId, string $reason): void
    {
        $stmt = DB::pdo()->prepare('UPDATE financial_receipts SET reversed_at = NOW(), reversed_by = :actor_id, reversal_reason = :reason WHERE id = :id AND reversed_at IS NULL');
        $stmt->bindValue(':actor_id', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':id', $receiptId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function find(int $receiptId): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM financial_receipts WHERE id = :id');
        $stmt->bindValue(':id', $receiptId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function listByReceivable(int $receivableId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM financial_receipts WHERE receivable_id = :receivable_id ORDER BY payment_date DESC, id DESC');
        $stmt->bindValue(':receivable_id', $receivableId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function latestActiveByReceivable(int $receivableId): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM financial_receipts WHERE receivable_id = :receivable_id AND reversed_at IS NULL ORDER BY payment_date DESC, id DESC LIMIT 1');
        $stmt->bindValue(':receivable_id', $receivableId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function netReceivedByReceivable(int $receivableId): float
    {
        $stmt = DB::pdo()->prepare("SELECT COALESCE(SUM((amount_received + interest_amount + fine_amount) - discount_amount),0) FROM financial_receipts WHERE receivable_id = :receivable_id AND reversed_at IS NULL");
        $stmt->bindValue(':receivable_id', $receivableId, \PDO::PARAM_INT);
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }
}
