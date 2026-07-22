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

    /**
     * Recebimentos filtrados pela data real de pagamento (fr.payment_date), não pelo
     * vencimento do título — usado pelo relatório financeiro consolidado para responder
     * "quanto entrou de caixa no período filtrado" (CRM_AUDIT.md P02/P07).
     */
    public function listByPeriod(int $companyId, array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(5000, $perPage));
        $offset = ($page - 1) * $perPage;
        [$whereSql, $params] = $this->buildFilters($companyId, $filters);

        $countStmt = DB::pdo()->prepare("SELECT COUNT(*) FROM financial_receipts fr
                INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$whereSql}");
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT fr.id, fr.receivable_id, far.title, far.project_id, p.title AS project_title,
                       far.client_id, c.company AS client_company,
                       fr.amount_received, fr.interest_amount, fr.fine_amount, fr.discount_amount,
                       ((fr.amount_received + fr.interest_amount + fr.fine_amount) - fr.discount_amount) AS net_amount,
                       fr.payment_method, fr.payment_date
                FROM financial_receipts fr
                INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                INNER JOIN clients c ON c.id = far.client_id
                LEFT JOIN projects p ON p.id = far.project_id
                WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$whereSql}
                ORDER BY fr.payment_date DESC, fr.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = DB::pdo()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ];
    }

    public function totalReceived(int $companyId, array $filters): float
    {
        [$whereSql, $params] = $this->buildFilters($companyId, $filters);
        $sql = "SELECT COALESCE(SUM((fr.amount_received + fr.interest_amount + fr.fine_amount) - fr.discount_amount),0)
                FROM financial_receipts fr
                INNER JOIN financial_accounts_receivable far ON far.id = fr.receivable_id
                WHERE fr.reversed_at IS NULL AND far.deleted_at IS NULL {$whereSql}";
        $stmt = DB::pdo()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    private function buildFilters(int $companyId, array $filters): array
    {
        $where = [' AND far.company_id = :company_id'];
        $params = [':company_id' => $companyId];
        foreach (['client_id', 'project_id'] as $field) {
            $v = (int) ($filters[$field] ?? 0);
            if ($v > 0) {
                $where[] = ' AND far.' . $field . ' = :' . $field;
                $params[':' . $field] = $v;
            }
        }
        $from = trim((string) ($filters['due_from'] ?? ''));
        if ($from !== '') {
            $where[] = ' AND fr.payment_date >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        $to = trim((string) ($filters['due_to'] ?? ''));
        if ($to !== '') {
            $where[] = ' AND fr.payment_date <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }
        return [implode('', $where), $params];
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    }
}
