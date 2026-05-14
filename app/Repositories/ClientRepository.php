<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ClientRepository
{
    public function all(): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query("SELECT id, name, email, phone, company, contact_person, status, project_reference, created_at FROM clients ORDER BY id DESC");
        return $stmt->fetchAll();
    }

    public function options(): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT id, company FROM clients ORDER BY company ASC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, name, email, phone, company, contact_person, status, project_reference, logo_path, logo_mime, logo_original_name, has_hosting_contract, hosting_contract_amount, hosting_due_date, hosting_renewal_days, manages_domain, domain_due_date, domain_amount FROM clients WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO clients (name, email, phone, company, contact_person, status, project_reference, has_hosting_contract, hosting_contract_amount, hosting_due_date, hosting_renewal_days, manages_domain, domain_due_date, domain_amount, created_at) VALUES (:name, :email, :phone, :company, :contact_person, :status, :project_reference, :has_hosting_contract, :hosting_contract_amount, :hosting_due_date, :hosting_renewal_days, :manages_domain, :domain_due_date, :domain_amount, NOW())');
        $this->bindClientData($stmt, $data);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE clients SET name = :name, email = :email, phone = :phone, company = :company, contact_person = :contact_person, status = :status, project_reference = :project_reference, has_hosting_contract = :has_hosting_contract, hosting_contract_amount = :hosting_contract_amount, hosting_due_date = :hosting_due_date, hosting_renewal_days = :hosting_renewal_days, manages_domain = :manages_domain, domain_due_date = :domain_due_date, domain_amount = :domain_amount WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $this->bindClientData($stmt, $data);
        $stmt->execute();
    }

    public function updateLogo(int $id, string $path, string $mime, string $originalName): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE clients SET logo_path = :path, logo_mime = :mime, logo_original_name = :original WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':path', $path);
        $stmt->bindValue(':mime', $mime);
        $stmt->bindValue(':original', $originalName);
        $stmt->execute();
    }

    public function delete(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function bindClientData(\PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':name', (string) $data['name']);
        $this->bindNullable($stmt, ':email', $data['email'] ?? null);
        $this->bindNullable($stmt, ':phone', $data['phone'] ?? null);
        $this->bindNullable($stmt, ':company', $data['company'] ?? null);
        $this->bindNullable($stmt, ':contact_person', $data['contact_person'] ?? null);
        $stmt->bindValue(':status', (string) $data['status']);
        $this->bindNullable($stmt, ':project_reference', $data['project_reference'] ?? null);
        $stmt->bindValue(':has_hosting_contract', (int) ($data['has_hosting_contract'] ?? 0), \PDO::PARAM_INT);
        $this->bindNullable($stmt, ':hosting_contract_amount', $data['hosting_contract_amount'] ?? null);
        $this->bindNullable($stmt, ':hosting_due_date', $data['hosting_due_date'] ?? null);
        $this->bindNullable($stmt, ':hosting_renewal_days', $data['hosting_renewal_days'] ?? null, \PDO::PARAM_INT);
        $stmt->bindValue(':manages_domain', (int) ($data['manages_domain'] ?? 0), \PDO::PARAM_INT);
        $this->bindNullable($stmt, ':domain_due_date', $data['domain_due_date'] ?? null);
        $this->bindNullable($stmt, ':domain_amount', $data['domain_amount'] ?? null);
    }

    private function bindNullable(\PDOStatement $stmt, string $param, mixed $value, int $type = \PDO::PARAM_STR): void
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($param, null, \PDO::PARAM_NULL);
            return;
        }

        if ($type === \PDO::PARAM_INT) {
            $stmt->bindValue($param, (int) $value, \PDO::PARAM_INT);
            return;
        }

        $stmt->bindValue($param, $value);
    }
}
