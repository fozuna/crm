<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\LeadRepositoryContract;
use App\Core\DB;
use App\Services\LeadStages;

final class LeadRepository implements LeadRepositoryContract
{
    public function listKanban(string $query = ''): array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT id, name, company, contact_person, person_type, document_number, email, phone, secondary_phone, postal_code, street, street_number, address_complement, neighborhood, city, state, birth_or_opening_date, market_segment, acquisition_source, stage, notes, converted_client_id, converted_at, created_by, updated_by, created_at, updated_at
                FROM leads
                WHERE converted_at IS NULL';

        if ($query !== '') {
            $sql .= ' AND (name LIKE :q OR company LIKE :q OR email LIKE :q OR phone LIKE :q OR acquisition_source LIKE :q)';
        }

        $sql .= ' ORDER BY FIELD(stage, '
            . $pdo->quote(LeadStages::CADASTRO_REALIZADO) . ', '
            . $pdo->quote(LeadStages::EM_CONTATO) . ', '
            . $pdo->quote(LeadStages::PROPOSTA_ENVIADA) . ', '
            . $pdo->quote(LeadStages::NEGOCIACAO) . ', '
            . $pdo->quote(LeadStages::PRONTO_APROVACAO) . ', '
            . $pdo->quote(LeadStages::APROVADO) . '), updated_at DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        if ($query !== '') {
            $stmt->bindValue(':q', '%' . $query . '%');
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, name, company, contact_person, person_type, document_number, email, phone, secondary_phone, postal_code, street, street_number, address_complement, neighborhood, city, state, birth_or_opening_date, market_segment, acquisition_source, stage, notes, converted_client_id, converted_at, created_by, updated_by, created_at, updated_at FROM leads WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data, int $actorId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO leads (
            name, company, contact_person, person_type, document_number, email, phone, secondary_phone,
            postal_code, street, street_number, address_complement, neighborhood, city, state,
            birth_or_opening_date, market_segment, acquisition_source, stage, notes, created_by, updated_by, created_at, updated_at
        ) VALUES (
            :name, :company, :contact_person, :person_type, :document_number, :email, :phone, :secondary_phone,
            :postal_code, :street, :street_number, :address_complement, :neighborhood, :city, :state,
            :birth_or_opening_date, :market_segment, :acquisition_source, :stage, :notes, :created_by, :updated_by, NOW(), NOW()
        )');
        $this->bind($stmt, $data);
        $stmt->bindValue(':created_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->bindValue(':updated_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data, int $actorId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE leads SET
            name = :name,
            company = :company,
            contact_person = :contact_person,
            person_type = :person_type,
            document_number = :document_number,
            email = :email,
            phone = :phone,
            secondary_phone = :secondary_phone,
            postal_code = :postal_code,
            street = :street,
            street_number = :street_number,
            address_complement = :address_complement,
            neighborhood = :neighborhood,
            city = :city,
            state = :state,
            birth_or_opening_date = :birth_or_opening_date,
            market_segment = :market_segment,
            acquisition_source = :acquisition_source,
            stage = :stage,
            notes = :notes,
            updated_by = :updated_by,
            updated_at = NOW()
            WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $this->bind($stmt, $data);
        $stmt->bindValue(':updated_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function updateStage(int $id, string $stage, int $actorId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE leads SET stage = :stage, updated_by = :updated_by, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':stage', $stage);
        $stmt->bindValue(':updated_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function markConverted(int $id, int $clientId, int $actorId): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE leads SET stage = :stage, converted_client_id = :client_id, converted_at = NOW(), updated_by = :updated_by, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':stage', LeadStages::APROVADO);
        $stmt->bindValue(':client_id', $clientId, \PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorId > 0 ? $actorId : null, $actorId > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->execute();
    }

    public function duplicateCounts(array $data, ?int $excludeLeadId = null): array
    {
        $pdo = DB::pdo();
        $counts = [
            'document_number' => 0,
            'email' => 0,
            'phone' => 0,
            'secondary_phone' => 0,
        ];

        $leadWhere = $excludeLeadId !== null ? ' AND id <> :exclude_id' : '';

        if (($data['document_number'] ?? '') !== '') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE converted_at IS NULL AND document_number = :value' . $leadWhere);
            $stmt->bindValue(':value', (string) $data['document_number']);
            if ($excludeLeadId !== null) {
                $stmt->bindValue(':exclude_id', $excludeLeadId, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $counts['document_number'] += (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE document_number = :value');
            $stmt->bindValue(':value', (string) $data['document_number']);
            $stmt->execute();
            $counts['document_number'] += (int) $stmt->fetchColumn();
        }

        if (($data['email'] ?? '') !== '') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE converted_at IS NULL AND email = :value' . $leadWhere);
            $stmt->bindValue(':value', (string) $data['email']);
            if ($excludeLeadId !== null) {
                $stmt->bindValue(':exclude_id', $excludeLeadId, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $counts['email'] += (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE email = :value');
            $stmt->bindValue(':value', (string) $data['email']);
            $stmt->execute();
            $counts['email'] += (int) $stmt->fetchColumn();
        }

        foreach (['phone', 'secondary_phone'] as $field) {
            if (($data[$field] ?? '') === '') {
                continue;
            }

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM leads WHERE converted_at IS NULL AND (phone = :phone_value OR secondary_phone = :secondary_phone_value)' . $leadWhere);
            $stmt->bindValue(':phone_value', (string) $data[$field]);
            $stmt->bindValue(':secondary_phone_value', (string) $data[$field]);
            if ($excludeLeadId !== null) {
                $stmt->bindValue(':exclude_id', $excludeLeadId, \PDO::PARAM_INT);
            }
            $stmt->execute();
            $counts[$field] += (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE phone = :phone_value OR secondary_phone = :secondary_phone_value');
            $stmt->bindValue(':phone_value', (string) $data[$field]);
            $stmt->bindValue(':secondary_phone_value', (string) $data[$field]);
            $stmt->execute();
            $counts[$field] += (int) $stmt->fetchColumn();
        }

        return $counts;
    }

    private function bind(\PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':name', (string) $data['name']);
        $this->bindNullable($stmt, ':company', $data['company'] ?? null);
        $this->bindNullable($stmt, ':contact_person', $data['contact_person'] ?? null);
        $stmt->bindValue(':person_type', (string) $data['person_type']);
        $stmt->bindValue(':document_number', (string) $data['document_number']);
        $stmt->bindValue(':email', (string) $data['email']);
        $stmt->bindValue(':phone', (string) $data['phone']);
        $this->bindNullable($stmt, ':secondary_phone', $data['secondary_phone'] ?? null);
        $stmt->bindValue(':postal_code', (string) $data['postal_code']);
        $stmt->bindValue(':street', (string) $data['street']);
        $stmt->bindValue(':street_number', (string) $data['street_number']);
        $this->bindNullable($stmt, ':address_complement', $data['address_complement'] ?? null);
        $stmt->bindValue(':neighborhood', (string) $data['neighborhood']);
        $stmt->bindValue(':city', (string) $data['city']);
        $stmt->bindValue(':state', (string) $data['state']);
        $this->bindNullable($stmt, ':birth_or_opening_date', $data['birth_or_opening_date'] ?? null);
        $stmt->bindValue(':market_segment', (string) $data['market_segment']);
        $stmt->bindValue(':acquisition_source', (string) $data['acquisition_source']);
        $stmt->bindValue(':stage', (string) $data['stage']);
        $this->bindNullable($stmt, ':notes', $data['notes'] ?? null);
    }

    private function bindNullable(\PDOStatement $stmt, string $param, mixed $value): void
    {
        if ($value === null || $value === '') {
            $stmt->bindValue($param, null, \PDO::PARAM_NULL);
            return;
        }

        $stmt->bindValue($param, (string) $value);
    }
}
