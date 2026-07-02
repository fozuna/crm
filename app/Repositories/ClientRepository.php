<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ClientRepositoryContract;
use App\Core\DB;

final class ClientRepository implements ClientRepositoryContract
{
    public function findBySourceLeadId(int $leadId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, name, email, phone, company, contact_person, status, project_reference, source_lead_id FROM clients WHERE source_lead_id = :lead_id LIMIT 1');
        $stmt->bindValue(':lead_id', $leadId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function dependencyCounts(int $clientId): array
    {
        $pdo = DB::pdo();

        $count = static function (string $table) use ($pdo, $clientId): int {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE client_id = :id");
                $stmt->bindValue(':id', $clientId, \PDO::PARAM_INT);
                $stmt->execute();
                return (int) $stmt->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };

        return [
            'proposals' => $count('proposals'),
            'projects' => $count('projects'),
            'contracts' => $count('contracts'),
            'receivables' => $count('financial_accounts_receivable'),
            'service_orders' => $count('servicos_avulsos'),
        ];
    }

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

    public function createFromLead(array $data): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO clients (
            name, email, phone, company, contact_person, status, project_reference,
            has_hosting_contract, hosting_contract_amount, hosting_due_date, hosting_renewal_days,
            manages_domain, domain_due_date, domain_amount,
            person_type, document_number, secondary_phone, postal_code, street, street_number,
            address_complement, neighborhood, city, state, birth_or_opening_date, market_segment,
            acquisition_source, billing_email, billing_phone, billing_notes, contract_notes, source_lead_id, created_at
        ) VALUES (
            :name, :email, :phone, :company, :contact_person, :status, :project_reference,
            :has_hosting_contract, :hosting_contract_amount, :hosting_due_date, :hosting_renewal_days,
            :manages_domain, :domain_due_date, :domain_amount,
            :person_type, :document_number, :secondary_phone, :postal_code, :street, :street_number,
            :address_complement, :neighborhood, :city, :state, :birth_or_opening_date, :market_segment,
            :acquisition_source, :billing_email, :billing_phone, :billing_notes, :contract_notes, :source_lead_id, NOW()
        )');
        $this->bindClientData($stmt, $data);
        $this->bindLeadBackedClientData($stmt, $data);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function createProposalProspectFromLead(array $lead): int
    {
        $payload = [
            'name' => (string) ($lead['name'] ?? ''),
            'email' => (string) ($lead['email'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'company' => (string) (($lead['company'] ?? '') !== '' ? $lead['company'] : ($lead['name'] ?? '')),
            'contact_person' => (string) (($lead['contact_person'] ?? '') !== '' ? $lead['contact_person'] : ($lead['name'] ?? '')),
            'status' => 'lead',
            'project_reference' => 'Origem no Kanban de leads #' . (int) ($lead['id'] ?? 0),
            'person_type' => (string) ($lead['person_type'] ?? 'pj'),
            'document_number' => (string) ($lead['document_number'] ?? ''),
            'secondary_phone' => $lead['secondary_phone'] ?? null,
            'postal_code' => $lead['postal_code'] ?? null,
            'street' => $lead['street'] ?? null,
            'street_number' => $lead['street_number'] ?? null,
            'address_complement' => $lead['address_complement'] ?? null,
            'neighborhood' => $lead['neighborhood'] ?? null,
            'city' => $lead['city'] ?? null,
            'state' => $lead['state'] ?? null,
            'birth_or_opening_date' => $lead['birth_or_opening_date'] ?? null,
            'market_segment' => $lead['market_segment'] ?? null,
            'acquisition_source' => $lead['acquisition_source'] ?? null,
            'billing_email' => $lead['email'] ?? null,
            'billing_phone' => $lead['phone'] ?? null,
            'billing_notes' => null,
            'contract_notes' => null,
            'source_lead_id' => isset($lead['id']) ? (int) $lead['id'] : null,
            'has_hosting_contract' => 0,
            'hosting_contract_amount' => null,
            'hosting_due_date' => null,
            'hosting_renewal_days' => null,
            'manages_domain' => 0,
            'domain_due_date' => null,
            'domain_amount' => null,
        ];

        return $this->createFromLead($payload);
    }

    public function promoteLeadProspectToActive(int $clientId, array $data): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE clients SET
            name = :name,
            email = :email,
            phone = :phone,
            company = :company,
            contact_person = :contact_person,
            status = :status,
            project_reference = :project_reference,
            has_hosting_contract = :has_hosting_contract,
            hosting_contract_amount = :hosting_contract_amount,
            hosting_due_date = :hosting_due_date,
            hosting_renewal_days = :hosting_renewal_days,
            manages_domain = :manages_domain,
            domain_due_date = :domain_due_date,
            domain_amount = :domain_amount,
            person_type = :person_type,
            document_number = :document_number,
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
            billing_email = :billing_email,
            billing_phone = :billing_phone,
            billing_notes = :billing_notes,
            contract_notes = :contract_notes,
            source_lead_id = :source_lead_id
            WHERE id = :id');
        $stmt->bindValue(':id', $clientId, \PDO::PARAM_INT);
        $this->bindClientData($stmt, $data);
        $this->bindLeadBackedClientData($stmt, $data);
        $stmt->execute();
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

    private function bindLeadBackedClientData(\PDOStatement $stmt, array $data): void
    {
        $stmt->bindValue(':person_type', (string) ($data['person_type'] ?? 'pj'));
        $this->bindNullable($stmt, ':document_number', $data['document_number'] ?? null);
        $this->bindNullable($stmt, ':secondary_phone', $data['secondary_phone'] ?? null);
        $this->bindNullable($stmt, ':postal_code', $data['postal_code'] ?? null);
        $this->bindNullable($stmt, ':street', $data['street'] ?? null);
        $this->bindNullable($stmt, ':street_number', $data['street_number'] ?? null);
        $this->bindNullable($stmt, ':address_complement', $data['address_complement'] ?? null);
        $this->bindNullable($stmt, ':neighborhood', $data['neighborhood'] ?? null);
        $this->bindNullable($stmt, ':city', $data['city'] ?? null);
        $this->bindNullable($stmt, ':state', $data['state'] ?? null);
        $this->bindNullable($stmt, ':birth_or_opening_date', $data['birth_or_opening_date'] ?? null);
        $this->bindNullable($stmt, ':market_segment', $data['market_segment'] ?? null);
        $this->bindNullable($stmt, ':acquisition_source', $data['acquisition_source'] ?? null);
        $this->bindNullable($stmt, ':billing_email', $data['billing_email'] ?? null);
        $this->bindNullable($stmt, ':billing_phone', $data['billing_phone'] ?? null);
        $this->bindNullable($stmt, ':billing_notes', $data['billing_notes'] ?? null);
        $this->bindNullable($stmt, ':contract_notes', $data['contract_notes'] ?? null);
        $this->bindNullable($stmt, ':source_lead_id', $data['source_lead_id'] ?? null, \PDO::PARAM_INT);
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
