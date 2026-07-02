<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ServiceOrderRepositoryContract;
use App\Core\DB;

final class ServiceOrderRepository implements ServiceOrderRepositoryContract
{
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        [$whereSql, $params] = $this->buildFilters($filters);

        $baseSql = " FROM servicos_avulsos so
                     INNER JOIN clients c ON c.id = so.client_id
                     LEFT JOIN users u ON u.id = so.assigned_user_id
                     LEFT JOIN services s ON s.id = so.base_service_id
                     WHERE so.deleted_at IS NULL {$whereSql}";

        $countStmt = DB::pdo()->prepare('SELECT COUNT(*)' . $baseSql);
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT so.*,
                       c.name AS client_name,
                       c.company AS client_company,
                       c.email AS client_email,
                       c.phone AS client_phone,
                       c.contact_person AS client_contact_person,
                       u.name AS assigned_user_name,
                       s.name AS base_service_name" . $baseSql .
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

    public function find(int $id): ?array
    {
        $sql = "SELECT so.*,
                       c.name AS client_name,
                       c.company AS client_company,
                       c.email AS client_email,
                       c.phone AS client_phone,
                       c.contact_person AS client_contact_person,
                       c.document_number AS client_document_number,
                       c.street AS client_street,
                       c.street_number AS client_street_number,
                       c.address_complement AS client_address_complement,
                       c.neighborhood AS client_neighborhood,
                       c.city AS client_city,
                       c.state AS client_state,
                       u.name AS assigned_user_name,
                       s.name AS base_service_name,
                       far.title AS receivable_title,
                       far.status AS receivable_status
                FROM servicos_avulsos so
                INNER JOIN clients c ON c.id = so.client_id
                LEFT JOIN users u ON u.id = so.assigned_user_id
                LEFT JOIN services s ON s.id = so.base_service_id
                LEFT JOIN financial_accounts_receivable far ON far.id = so.financial_receivable_id
                WHERE so.id = :id AND so.deleted_at IS NULL
                LIMIT 1";
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data, int $actorId): int
    {
        $sequence = $this->nextSequence();
        $numeroOs = $this->formatNumber($sequence);

        $stmt = DB::pdo()->prepare(
            'INSERT INTO servicos_avulsos (
                numero_sequencial, numero_os, service_name, client_id, contact_name, assigned_user_id, type,
                type_other_description, status, request_description, executed_activities, technical_notes,
                internal_notes, opened_at, due_at, completed_at, estimated_hours, executed_hours, billable,
                base_service_id, base_amount, discount_amount, surcharge_amount, final_amount,
                financial_receivable_id, created_by, updated_by, created_at, updated_at
            ) VALUES (
                :numero_sequencial, :numero_os, :service_name, :client_id, :contact_name, :assigned_user_id, :type,
                :type_other_description, :status, :request_description, :executed_activities, :technical_notes,
                :internal_notes, :opened_at, :due_at, :completed_at, :estimated_hours, :executed_hours, :billable,
                :base_service_id, :base_amount, :discount_amount, :surcharge_amount, :final_amount,
                :financial_receivable_id, :created_by, :updated_by, NOW(), NOW()
            )'
        );
        $stmt->bindValue(':numero_sequencial', $sequence, \PDO::PARAM_INT);
        $stmt->bindValue(':numero_os', $numeroOs);
        $this->bindWriteData($stmt, $data, $actorId);
        $stmt->execute();

        return (int) DB::pdo()->lastInsertId();
    }

    public function update(int $id, array $data, int $actorId): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE servicos_avulsos SET
                service_name = :service_name,
                client_id = :client_id,
                contact_name = :contact_name,
                assigned_user_id = :assigned_user_id,
                type = :type,
                type_other_description = :type_other_description,
                status = :status,
                request_description = :request_description,
                executed_activities = :executed_activities,
                technical_notes = :technical_notes,
                internal_notes = :internal_notes,
                opened_at = :opened_at,
                due_at = :due_at,
                completed_at = :completed_at,
                estimated_hours = :estimated_hours,
                executed_hours = :executed_hours,
                billable = :billable,
                base_service_id = :base_service_id,
                base_amount = :base_amount,
                discount_amount = :discount_amount,
                surcharge_amount = :surcharge_amount,
                final_amount = :final_amount,
                updated_by = :updated_by,
                updated_at = NOW()
             WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $this->bindWriteData($stmt, $data, $actorId, false);
        $stmt->execute();
    }

    public function updateStatus(int $id, string $status, int $actorId, ?string $completedAt = null): void
    {
        $stmt = DB::pdo()->prepare('UPDATE servicos_avulsos SET status = :status, completed_at = :completed_at, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':completed_at', $completedAt, $completedAt === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':updated_by', $actorId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markDeleted(int $id, int $actorId): void
    {
        $stmt = DB::pdo()->prepare('UPDATE servicos_avulsos SET deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function attachFinancialReceivable(int $id, ?int $receivableId, int $actorId, ?string $status = null): void
    {
        $sql = 'UPDATE servicos_avulsos SET financial_receivable_id = :receivable_id, updated_by = :updated_by, updated_at = NOW()';
        if ($status !== null && $status !== '') {
            $sql .= ', status = :status';
        }
        $sql .= ' WHERE id = :id AND deleted_at IS NULL';

        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':receivable_id', $receivableId, $receivableId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorId, \PDO::PARAM_INT);
        if ($status !== null && $status !== '') {
            $stmt->bindValue(':status', $status);
        }
        $stmt->execute();
    }

    public function nextSequence(): int
    {
        $value = DB::pdo()->query('SELECT COALESCE(MAX(numero_sequencial), 0) + 1 FROM servicos_avulsos')->fetchColumn();
        return max(1, (int) $value);
    }

    public function listByClient(int $clientId, int $limit = 20): array
    {
        $sql = 'SELECT id, numero_os, service_name, status, type, final_amount, opened_at, completed_at
                FROM servicos_avulsos
                WHERE client_id = :client_id AND deleted_at IS NULL
                ORDER BY id DESC
                LIMIT ' . max(1, min(100, $limit));
        $stmt = DB::pdo()->prepare($sql);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function bindWriteData(\PDOStatement $stmt, array $data, int $actorId, bool $withCreatedBy = true): void
    {
        $stmt->bindValue(':service_name', (string) ($data['service_name'] ?? ''));
        $stmt->bindValue(':client_id', (int) ($data['client_id'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':contact_name', $data['contact_name'] ?? null, ($data['contact_name'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':assigned_user_id', $data['assigned_user_id'] ?? null, ($data['assigned_user_id'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':type', (string) ($data['type'] ?? ''));
        $stmt->bindValue(':type_other_description', $data['type_other_description'] ?? null, ($data['type_other_description'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':status', (string) ($data['status'] ?? ''));
        $stmt->bindValue(':request_description', $data['request_description'] ?? null, ($data['request_description'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':executed_activities', $data['executed_activities'] ?? null, ($data['executed_activities'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':technical_notes', $data['technical_notes'] ?? null, ($data['technical_notes'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':internal_notes', $data['internal_notes'] ?? null, ($data['internal_notes'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':opened_at', (string) ($data['opened_at'] ?? date('Y-m-d H:i:s')));
        $stmt->bindValue(':due_at', $data['due_at'] ?? null, ($data['due_at'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':completed_at', $data['completed_at'] ?? null, ($data['completed_at'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':estimated_hours', $data['estimated_hours'] ?? null, ($data['estimated_hours'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':executed_hours', $data['executed_hours'] ?? null, ($data['executed_hours'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
        $stmt->bindValue(':billable', (int) ($data['billable'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':base_service_id', $data['base_service_id'] ?? null, ($data['base_service_id'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':base_amount', (float) ($data['base_amount'] ?? 0));
        $stmt->bindValue(':discount_amount', (float) ($data['discount_amount'] ?? 0));
        $stmt->bindValue(':surcharge_amount', (float) ($data['surcharge_amount'] ?? 0));
        $stmt->bindValue(':final_amount', (float) ($data['final_amount'] ?? 0));
        if ($withCreatedBy) {
            $stmt->bindValue(':financial_receivable_id', $data['financial_receivable_id'] ?? null, ($data['financial_receivable_id'] ?? null) === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':created_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        }
        $stmt->bindValue(':updated_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
    }

    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = ' AND (so.numero_os LIKE :q OR so.service_name LIKE :q OR c.name LIKE :q OR c.company LIKE :q OR u.name LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        foreach (['client_id', 'assigned_user_id'] as $field) {
            $value = (int) ($filters[$field] ?? 0);
            if ($value > 0) {
                $where[] = ' AND so.' . $field . ' = :' . $field;
                $params[':' . $field] = $value;
            }
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = ' AND so.status = :status';
            $params[':status'] = $status;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = ' AND so.type = :type';
            $params[':type'] = $type;
        }

        $billable = trim((string) ($filters['billable'] ?? ''));
        if ($billable === '1' || $billable === '0') {
            $where[] = ' AND so.billable = :billable';
            $params[':billable'] = (int) $billable;
        }

        return [implode('', $where), $params];
    }

    private function orderBy(array $filters): string
    {
        $sort = trim((string) ($filters['sort'] ?? 'opened_desc'));
        return match ($sort) {
            'opened_asc' => 'so.opened_at ASC, so.id ASC',
            'due_asc' => 'so.due_at ASC, so.id ASC',
            'due_desc' => 'so.due_at DESC, so.id DESC',
            'client_asc' => 'c.company ASC, so.id DESC',
            'status_asc' => 'so.status ASC, so.id DESC',
            default => 'so.opened_at DESC, so.id DESC',
        };
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    }

    private function formatNumber(int $sequence): string
    {
        return 'OS-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
