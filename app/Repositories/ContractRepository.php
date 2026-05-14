<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ContractRepository
{
    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT ct.*, p.title AS proposal_title, p.total AS proposal_total, p.status AS proposal_status, c.name AS client_name, c.company AS client_company, t.name AS template_name
                FROM contracts ct
                JOIN proposals p ON p.id = ct.proposal_id
                JOIN clients c ON c.id = ct.client_id
                JOIN contract_templates t ON t.id = ct.template_id
                WHERE ct.id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByProposal(int $proposalId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, proposal_id, client_id, template_id, status, signature_mode, needs_signature, contract_number, title, current_version, current_file_path, rendered_body, rendered_summary, source_proposal_snapshot, policy_reason, signature_provider, signature_reference, signature_url, sent_for_signature_at, signed_at, effective_date, expires_at, created_by, updated_by, created_at, updated_at FROM contracts WHERE proposal_id = :proposal_id');
        $stmt->bindValue(':proposal_id', $proposalId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $payload): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO contracts (proposal_id, client_id, template_id, status, signature_mode, needs_signature, contract_number, title, current_version, current_file_path, rendered_body, rendered_summary, source_proposal_snapshot, policy_reason, signature_provider, signature_reference, signature_url, sent_for_signature_at, signed_at, effective_date, expires_at, created_by, updated_by, created_at, updated_at) VALUES (:proposal_id, :client_id, :template_id, :status, :signature_mode, :needs_signature, :contract_number, :title, :current_version, :current_file_path, :rendered_body, :rendered_summary, :source_proposal_snapshot, :policy_reason, :signature_provider, :signature_reference, :signature_url, :sent_for_signature_at, :signed_at, :effective_date, :expires_at, :created_by, :updated_by, NOW(), NOW())');
        $this->bindPayload($stmt, $payload);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE contracts SET template_id = :template_id, status = :status, signature_mode = :signature_mode, needs_signature = :needs_signature, contract_number = :contract_number, title = :title, current_version = :current_version, current_file_path = :current_file_path, rendered_body = :rendered_body, rendered_summary = :rendered_summary, source_proposal_snapshot = :source_proposal_snapshot, policy_reason = :policy_reason, signature_provider = :signature_provider, signature_reference = :signature_reference, signature_url = :signature_url, sent_for_signature_at = :sent_for_signature_at, signed_at = :signed_at, effective_date = :effective_date, expires_at = :expires_at, updated_by = :updated_by, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $this->bindPayload($stmt, $payload, false);
        $stmt->execute();
    }

    public function updateStatus(int $id, string $status, int $actorId, array $extra = []): void
    {
        $fields = ['status = :status', 'updated_by = :updated_by', 'updated_at = NOW()'];
        $params = [
            ':id' => [$id, \PDO::PARAM_INT],
            ':status' => [$status, \PDO::PARAM_STR],
            ':updated_by' => [$actorId, \PDO::PARAM_INT],
        ];

        foreach (['signature_provider', 'signature_reference', 'signature_url', 'sent_for_signature_at', 'signed_at', 'effective_date'] as $field) {
            if (array_key_exists($field, $extra)) {
                $fields[] = $field . ' = :' . $field;
                $params[':' . $field] = [$extra[$field], $extra[$field] === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR];
            }
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE contracts SET ' . implode(', ', $fields) . ' WHERE id = :id');
        foreach ($params as $key => [$value, $type]) {
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
    }

    public function all(array $filters = []): array
    {
        $pdo = DB::pdo();
        $where = [];
        $params = [];

        if (($status = trim((string) ($filters['status'] ?? ''))) !== '') {
            $where[] = 'ct.status = :status';
            $params[':status'] = [$status, \PDO::PARAM_STR];
        }
        if (($proposalId = (int) ($filters['proposal_id'] ?? 0)) > 0) {
            $where[] = 'ct.proposal_id = :proposal_id';
            $params[':proposal_id'] = [$proposalId, \PDO::PARAM_INT];
        }
        if (($clientId = (int) ($filters['client_id'] ?? 0)) > 0) {
            $where[] = 'ct.client_id = :client_id';
            $params[':client_id'] = [$clientId, \PDO::PARAM_INT];
        }

        $sql = 'SELECT ct.id, ct.contract_number, ct.title, ct.status, ct.signature_mode, ct.current_version, ct.sent_for_signature_at, ct.signed_at, ct.effective_date, ct.expires_at, ct.updated_at, p.id AS proposal_id, p.title AS proposal_title, p.total AS proposal_total, c.name AS client_name, c.company AS client_company, t.name AS template_name
                FROM contracts ct
                JOIN proposals p ON p.id = ct.proposal_id
                JOIN clients c ON c.id = ct.client_id
                JOIN contract_templates t ON t.id = ct.template_id';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY ct.updated_at DESC, ct.id DESC';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => [$value, $type]) {
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function summary(): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query("SELECT
            COUNT(*) AS total_contracts,
            SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) AS drafts,
            SUM(CASE WHEN status = 'pendente_assinatura' THEN 1 ELSE 0 END) AS pending_signature,
            SUM(CASE WHEN status = 'assinado' THEN 1 ELSE 0 END) AS signed,
            SUM(CASE WHEN status = 'vigente' THEN 1 ELSE 0 END) AS active_contracts,
            SUM(CASE WHEN effective_date IS NOT NULL AND effective_date <= CURDATE() AND status IN ('assinado','vigente') THEN 1 ELSE 0 END) AS ready_to_activate
            FROM contracts");
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    private function bindPayload(\PDOStatement $stmt, array $payload, bool $includeIdentity = true): void
    {
        if ($includeIdentity) {
            $stmt->bindValue(':proposal_id', (int) $payload['proposal_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':client_id', (int) $payload['client_id'], \PDO::PARAM_INT);
        }
        $stmt->bindValue(':template_id', (int) $payload['template_id'], \PDO::PARAM_INT);
        $stmt->bindValue(':status', (string) $payload['status']);
        $stmt->bindValue(':signature_mode', (string) $payload['signature_mode']);
        $stmt->bindValue(':needs_signature', (int) ($payload['needs_signature'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':contract_number', (string) $payload['contract_number']);
        $stmt->bindValue(':title', (string) $payload['title']);
        $stmt->bindValue(':current_version', (int) ($payload['current_version'] ?? 1), \PDO::PARAM_INT);
        $stmt->bindValue(':current_file_path', $payload['current_file_path']);
        $stmt->bindValue(':rendered_body', (string) $payload['rendered_body']);
        $stmt->bindValue(':rendered_summary', $payload['rendered_summary']);
        $stmt->bindValue(':source_proposal_snapshot', $payload['source_proposal_snapshot']);
        $stmt->bindValue(':policy_reason', $payload['policy_reason']);
        $stmt->bindValue(':signature_provider', $payload['signature_provider']);
        $stmt->bindValue(':signature_reference', $payload['signature_reference']);
        $stmt->bindValue(':signature_url', $payload['signature_url']);
        $stmt->bindValue(':sent_for_signature_at', $payload['sent_for_signature_at']);
        $stmt->bindValue(':signed_at', $payload['signed_at']);
        $stmt->bindValue(':effective_date', $payload['effective_date']);
        $stmt->bindValue(':expires_at', $payload['expires_at']);

        if ($includeIdentity) {
            $createdBy = isset($payload['created_by']) ? (int) $payload['created_by'] : null;
            $stmt->bindValue(':created_by', $createdBy, $createdBy === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        }
        $updatedBy = isset($payload['updated_by']) ? (int) $payload['updated_by'] : null;
        $stmt->bindValue(':updated_by', $updatedBy, $updatedBy === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
    }
}
