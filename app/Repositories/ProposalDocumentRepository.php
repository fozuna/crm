<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProposalDocumentRepository
{
    public function listByProposal(int $proposalId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, proposal_id, version, file_path, generated_at FROM proposal_documents WHERE proposal_id = :id ORDER BY version DESC');
        $stmt->bindValue(':id', $proposalId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function nextVersion(int $proposalId): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(version),0) FROM proposal_documents WHERE proposal_id = :id');
        $stmt->bindValue(':id', $proposalId, \PDO::PARAM_INT);
        $stmt->execute();
        return ((int) $stmt->fetchColumn()) + 1;
    }

    public function create(int $proposalId, int $version, string $path, ?string $brandingSnapshot, ?string $totalsSnapshot): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO proposal_documents (proposal_id, version, file_path, branding_snapshot, totals_snapshot, generated_at) VALUES (:proposal_id, :version, :file_path, :branding, :totals, NOW())');
        $stmt->bindValue(':proposal_id', $proposalId, \PDO::PARAM_INT);
        $stmt->bindValue(':version', $version, \PDO::PARAM_INT);
        $stmt->bindValue(':file_path', $path);
        $stmt->bindValue(':branding', $brandingSnapshot);
        $stmt->bindValue(':totals', $totalsSnapshot);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function find(int $proposalId, int $docId): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, proposal_id, version, file_path, generated_at FROM proposal_documents WHERE proposal_id = :proposal_id AND id = :id');
        $stmt->bindValue(':proposal_id', $proposalId, \PDO::PARAM_INT);
        $stmt->bindValue(':id', $docId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }
}

