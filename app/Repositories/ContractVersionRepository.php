<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ContractVersionRepository
{
    public function listByContract(int $contractId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, contract_id, version, template_snapshot, proposal_snapshot, rendered_body, file_path, created_by, created_at FROM contract_versions WHERE contract_id = :contract_id ORDER BY version DESC');
        $stmt->bindValue(':contract_id', $contractId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function nextVersion(int $contractId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version), 0) FROM contract_versions WHERE contract_id = :contract_id');
        $stmt->bindValue(':contract_id', $contractId, \PDO::PARAM_INT);
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) + 1;
    }

    public function create(int $contractId, int $version, array $payload): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO contract_versions (contract_id, version, template_snapshot, proposal_snapshot, rendered_body, file_path, created_by, created_at) VALUES (:contract_id, :version, :template_snapshot, :proposal_snapshot, :rendered_body, :file_path, :created_by, NOW())');
        $stmt->bindValue(':contract_id', $contractId, \PDO::PARAM_INT);
        $stmt->bindValue(':version', $version, \PDO::PARAM_INT);
        $stmt->bindValue(':template_snapshot', $payload['template_snapshot']);
        $stmt->bindValue(':proposal_snapshot', $payload['proposal_snapshot']);
        $stmt->bindValue(':rendered_body', (string) $payload['rendered_body']);
        $stmt->bindValue(':file_path', $payload['file_path']);
        $createdBy = isset($payload['created_by']) ? (int) $payload['created_by'] : null;
        $stmt->bindValue(':created_by', $createdBy, $createdBy === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function find(int $contractId, int $versionId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, contract_id, version, template_snapshot, proposal_snapshot, rendered_body, file_path, created_by, created_at FROM contract_versions WHERE contract_id = :contract_id AND id = :id');
        $stmt->bindValue(':contract_id', $contractId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $versionId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}
