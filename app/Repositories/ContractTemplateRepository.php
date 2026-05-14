<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ContractTemplateRepository
{
    public function active(): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT id, name, slug, description, is_active, auto_criteria_json, signature_mode_default, require_signature_default, header_title, body_template, footer_notes, created_at, updated_at FROM contract_templates WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function all(): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT id, name, slug, description, is_active, auto_criteria_json, signature_mode_default, require_signature_default, header_title, body_template, footer_notes, created_at, updated_at FROM contract_templates ORDER BY is_active DESC, id ASC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, name, slug, description, is_active, auto_criteria_json, signature_mode_default, require_signature_default, header_title, body_template, footer_notes, created_at, updated_at FROM contract_templates WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function update(int $id, array $payload): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE contract_templates SET name = :name, description = :description, is_active = :is_active, auto_criteria_json = :criteria, signature_mode_default = :signature_mode_default, require_signature_default = :require_signature_default, header_title = :header_title, body_template = :body_template, footer_notes = :footer_notes, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':name', (string) $payload['name']);
        $stmt->bindValue(':description', $payload['description']);
        $stmt->bindValue(':is_active', (int) ($payload['is_active'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':criteria', $payload['auto_criteria_json']);
        $stmt->bindValue(':signature_mode_default', (string) $payload['signature_mode_default']);
        $stmt->bindValue(':require_signature_default', (int) ($payload['require_signature_default'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':header_title', (string) $payload['header_title']);
        $stmt->bindValue(':body_template', (string) $payload['body_template']);
        $stmt->bindValue(':footer_notes', $payload['footer_notes']);
        $stmt->execute();

        if ((int) ($payload['is_active'] ?? 0) === 1) {
            $disable = $pdo->prepare('UPDATE contract_templates SET is_active = 0, updated_at = NOW() WHERE id <> :id');
            $disable->bindValue(':id', $id, \PDO::PARAM_INT);
            $disable->execute();
        }
    }
}
