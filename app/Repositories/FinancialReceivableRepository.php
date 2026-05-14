<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;
use App\DTOs\FinancialReceivableData;

final class FinancialReceivableRepository
{
    public function paginate(int $companyId, array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$whereSql, $params] = $this->buildFilters($companyId, $filters);

        $baseSql = " FROM financial_accounts_receivable far
                     INNER JOIN clients c ON c.id = far.client_id
                     LEFT JOIN projects p ON p.id = far.project_id
                     LEFT JOIN financial_categories fc ON fc.id = far.category_id
                     LEFT JOIN financial_cost_centers fcc ON fcc.id = far.cost_center_id
                     WHERE far.deleted_at IS NULL {$whereSql}";

        $countStmt = DB::pdo()->prepare('SELECT COUNT(*)' . $baseSql);
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT far.*,
                       c.name AS client_name,
                       c.company AS client_company,
                       p.title AS project_title,
                       fc.name AS category_name,
                       fcc.name AS cost_center_name,
                       CASE
                         WHEN far.status IN ('paid','canceled','renegotiated') THEN 0
                         WHEN far.due_date < CURDATE() THEN DATEDIFF(CURDATE(), far.due_date)
                         ELSE 0
                       END AS days_overdue" . $baseSql .
               ' ORDER BY ' . $this->orderBy($filters) .
               " LIMIT {$perPage} OFFSET {$offset}";
        $stmt = DB::pdo()->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function find(int $companyId, int $id): ?array
    {
        $sql = "SELECT far.*,
                       c.name AS client_name,
                       c.company AS client_company,
                       p.title AS project_title,
                       fc.name AS category_name,
                       fcc.name AS cost_center_name,
                       fba.bank_name,
                       fba.account_name,
                       ct.rendered_body AS contract_body
                FROM financial_accounts_receivable far
                INNER JOIN clients c ON c.id = far.client_id
                LEFT JOIN projects p ON p.id = far.project_id
                LEFT JOIN financial_categories fc ON fc.id = far.category_id
                LEFT JOIN financial_cost_centers fcc ON fcc.id = far.cost_center_id
                LEFT JOIN financial_bank_accounts fba ON fba.id = far.bank_account_id
                LEFT JOIN contracts ct ON ct.id = far.contract_id
                WHERE far.company_id = :company_id AND far.id = :id AND far.deleted_at IS NULL
                LIMIT 1";
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findBySourceInstallment(int $companyId, int $installmentId): ?array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM financial_accounts_receivable WHERE company_id = :company_id AND source_installment_id = :source_installment_id AND deleted_at IS NULL LIMIT 1');
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':source_installment_id', $installmentId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function listBySourceInstallment(int $companyId, int $installmentId): array
    {
        $stmt = DB::pdo()->prepare('SELECT * FROM financial_accounts_receivable WHERE company_id = :company_id AND source_installment_id = :source_installment_id AND deleted_at IS NULL ORDER BY id ASC');
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':source_installment_id', $installmentId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(FinancialReceivableData $data): int
    {
        $remaining = max(0, round($data->originalAmount + $data->interestAmount + $data->fineAmount - $data->discountAmount, 2));
        $stmt = DB::pdo()->prepare('INSERT INTO financial_accounts_receivable (company_id, project_id, client_id, contract_id, source_installment_id, installment_number, total_installments, title, description, original_amount, discount_amount, interest_amount, fine_amount, received_amount, remaining_amount, due_date, issue_date, payment_date, competence_date, status, payment_method, payment_channel, bank_account_id, category_id, cost_center_id, invoice_number, external_reference, recurrence_group, recurrence_interval_months, notes, created_by, updated_by, created_at, updated_at) VALUES (:company_id, :project_id, :client_id, :contract_id, :source_installment_id, :installment_number, :total_installments, :title, :description, :original_amount, :discount_amount, :interest_amount, :fine_amount, 0, :remaining_amount, :due_date, :issue_date, :payment_date, :competence_date, :status, :payment_method, :payment_channel, :bank_account_id, :category_id, :cost_center_id, :invoice_number, :external_reference, :recurrence_group, :recurrence_interval_months, :notes, :created_by, :updated_by, NOW(), NOW())');
        $this->bindData($stmt, $data, $remaining, true);
        $stmt->execute();
        return (int) DB::pdo()->lastInsertId();
    }

    public function update(int $id, FinancialReceivableData $data, float $receivedAmount): void
    {
        $remaining = max(0, round($data->originalAmount + $data->interestAmount + $data->fineAmount - $data->discountAmount - $receivedAmount, 2));
        $stmt = DB::pdo()->prepare('UPDATE financial_accounts_receivable SET project_id = :project_id, client_id = :client_id, contract_id = :contract_id, source_installment_id = :source_installment_id, installment_number = :installment_number, total_installments = :total_installments, title = :title, description = :description, original_amount = :original_amount, discount_amount = :discount_amount, interest_amount = :interest_amount, fine_amount = :fine_amount, remaining_amount = :remaining_amount, due_date = :due_date, issue_date = :issue_date, payment_date = :payment_date, competence_date = :competence_date, status = :status, payment_method = :payment_method, payment_channel = :payment_channel, bank_account_id = :bank_account_id, category_id = :category_id, cost_center_id = :cost_center_id, invoice_number = :invoice_number, external_reference = :external_reference, recurrence_group = :recurrence_group, recurrence_interval_months = :recurrence_interval_months, notes = :notes, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND company_id = :company_id');
        $this->bindData($stmt, $data, $remaining, false);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markDeleted(int $companyId, int $id, int $actorId): void
    {
        $stmt = DB::pdo()->prepare('UPDATE financial_accounts_receivable SET deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW() WHERE company_id = :company_id AND id = :id AND deleted_at IS NULL');
        $stmt->bindValue(':updated_by', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateFinancialSnapshot(int $companyId, int $id, float $receivedAmount, float $remainingAmount, string $status, ?string $paymentDate, int $actorId): void
    {
        $stmt = DB::pdo()->prepare('UPDATE financial_accounts_receivable SET received_amount = :received_amount, remaining_amount = :remaining_amount, status = :status, payment_date = :payment_date, updated_by = :updated_by, updated_at = NOW() WHERE company_id = :company_id AND id = :id');
        $stmt->bindValue(':received_amount', round($receivedAmount, 2));
        $stmt->bindValue(':remaining_amount', round($remainingAmount, 2));
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':payment_date', $paymentDate);
        $stmt->bindValue(':updated_by', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function latestByClient(int $companyId, int $limit = 10): array
    {
        $sql = "SELECT c.company AS client_company, c.name AS client_name,
                       COUNT(*) AS qty,
                       SUM(far.remaining_amount) AS total
                FROM financial_accounts_receivable far
                INNER JOIN clients c ON c.id = far.client_id
                WHERE far.company_id = :company_id
                  AND far.deleted_at IS NULL
                  AND far.status IN ('pending','partially_paid','overdue')
                GROUP BY far.client_id, c.company, c.name
                ORDER BY total DESC
                LIMIT " . max(1, min(50, $limit));
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function upcoming(int $companyId, int $days = 15, int $limit = 10): array
    {
        $sql = "SELECT far.id, far.title, far.due_date, far.remaining_amount, c.company AS client_company, p.title AS project_title
                FROM financial_accounts_receivable far
                INNER JOIN clients c ON c.id = far.client_id
                LEFT JOIN projects p ON p.id = far.project_id
                WHERE far.company_id = :company_id
                  AND far.deleted_at IS NULL
                  AND far.status IN ('pending','partially_paid')
                  AND far.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
                ORDER BY far.due_date ASC, far.id ASC
                LIMIT " . max(1, min(50, $limit));
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function bindData(\PDOStatement $stmt, FinancialReceivableData $data, float $remaining, bool $includeCreatedBy): void
    {
        $stmt->bindValue(':company_id', $data->companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $data->projectId, $data->projectId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':client_id', $data->clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':contract_id', $data->contractId, $data->contractId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':source_installment_id', $data->sourceInstallmentId, $data->sourceInstallmentId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':installment_number', $data->installmentNumber, \PDO::PARAM_INT);
        $stmt->bindValue(':total_installments', $data->totalInstallments, \PDO::PARAM_INT);
        $stmt->bindValue(':title', $data->title);
        $stmt->bindValue(':description', $data->description);
        $stmt->bindValue(':original_amount', $data->originalAmount);
        $stmt->bindValue(':discount_amount', $data->discountAmount);
        $stmt->bindValue(':interest_amount', $data->interestAmount);
        $stmt->bindValue(':fine_amount', $data->fineAmount);
        $stmt->bindValue(':remaining_amount', $remaining);
        $stmt->bindValue(':due_date', $data->dueDate);
        $stmt->bindValue(':issue_date', $data->issueDate);
        $stmt->bindValue(':payment_date', $data->paymentDate);
        $stmt->bindValue(':competence_date', $data->competenceDate);
        $stmt->bindValue(':status', $data->status);
        $stmt->bindValue(':payment_method', $data->paymentMethod);
        $stmt->bindValue(':payment_channel', $data->paymentChannel);
        $stmt->bindValue(':bank_account_id', $data->bankAccountId, $data->bankAccountId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':category_id', $data->categoryId, $data->categoryId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':cost_center_id', $data->costCenterId, $data->costCenterId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':invoice_number', $data->invoiceNumber);
        $stmt->bindValue(':external_reference', $data->externalReference);
        $stmt->bindValue(':recurrence_group', $data->recurrenceGroup);
        $stmt->bindValue(':recurrence_interval_months', $data->recurrenceIntervalMonths, \PDO::PARAM_INT);
        $stmt->bindValue(':notes', $data->notes);
        if ($includeCreatedBy) {
            $stmt->bindValue(':created_by', $data->createdBy > 0 ? $data->createdBy : null, $data->createdBy > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        }
        $stmt->bindValue(':updated_by', $data->updatedBy > 0 ? $data->updatedBy : null, $data->updatedBy > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
    }

    private function buildFilters(int $companyId, array $filters): array
    {
        $where = [' AND far.company_id = :company_id'];
        $params = [':company_id' => $companyId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = ' AND (far.title LIKE :q OR far.description LIKE :q OR far.invoice_number LIKE :q OR far.external_reference LIKE :q OR c.name LIKE :q OR c.company LIKE :q OR p.title LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        foreach (['client_id', 'project_id', 'category_id', 'cost_center_id'] as $field) {
            $v = (int) ($filters[$field] ?? 0);
            if ($v > 0) {
                $where[] = ' AND far.' . $field . ' = :' . $field;
                $params[':' . $field] = $v;
            }
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['pending', 'partially_paid', 'paid', 'overdue', 'canceled', 'renegotiated'], true)) {
            $where[] = ' AND far.status = :status';
            $params[':status'] = $status;
        }
        $dueFrom = trim((string) ($filters['due_from'] ?? ''));
        if ($dueFrom !== '') {
            $where[] = ' AND far.due_date >= :due_from';
            $params[':due_from'] = $dueFrom;
        }
        $dueTo = trim((string) ($filters['due_to'] ?? ''));
        if ($dueTo !== '') {
            $where[] = ' AND far.due_date <= :due_to';
            $params[':due_to'] = $dueTo;
        }
        return [implode('', $where), $params];
    }

    private function orderBy(array $filters): string
    {
        $sort = trim((string) ($filters['sort'] ?? 'due_date'));
        $direction = strtolower(trim((string) ($filters['direction'] ?? 'asc'))) === 'desc' ? 'DESC' : 'ASC';
        $map = [
            'due_date' => 'far.due_date',
            'client' => 'client_company',
            'project' => 'project_title',
            'amount' => 'far.original_amount',
            'remaining' => 'far.remaining_amount',
            'status' => 'far.status',
            'days_overdue' => 'days_overdue',
            'created_at' => 'far.created_at',
        ];
        $col = $map[$sort] ?? $map['due_date'];
        return $col . ' ' . $direction . ', far.id ' . $direction;
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    }
}
