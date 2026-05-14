<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ProposalRepository
{
    public function allWithClient(): array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT p.id, p.title, p.status, p.total, p.created_at, c.name AS client_name
                FROM proposals p
                JOIN clients c ON c.id = p.client_id
                ORDER BY p.id DESC';
        return $pdo->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT p.*, c.name AS client_name
                FROM proposals p
                JOIN clients c ON c.id = p.client_id
                WHERE p.id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function items(int $proposalId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, service_id, is_bonus, catalog_price, description, qty, unit_price, total FROM proposal_items WHERE proposal_id = :id ORDER BY id ASC');
        $stmt->bindValue(':id', $proposalId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $payload): int
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO proposals (client_id, title, description, notes, status, subtotal, discount_percent, discount_amount, total, payment_method_id, payment_snapshot, payment_options, payment_selected_index, delivery_start, delivery_end, penalty_terms, terms, created_at) VALUES (:client_id, :title, :description, :notes, :status, :subtotal, :discount_percent, :discount_amount, :total, :payment_method_id, :payment_snapshot, :payment_options, :payment_selected_index, :delivery_start, :delivery_end, :penalty_terms, :terms, NOW())');
            $stmt->bindValue(':client_id', $payload['client_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':title', $payload['title']);
            $stmt->bindValue(':description', $payload['description']);
            $stmt->bindValue(':notes', $payload['notes']);
            $stmt->bindValue(':status', $payload['status']);
            $stmt->bindValue(':subtotal', $payload['subtotal']);
            $stmt->bindValue(':discount_percent', $payload['discount_percent']);
            $stmt->bindValue(':discount_amount', $payload['discount_amount']);
            $stmt->bindValue(':total', $payload['total']);
            $stmt->bindValue(':payment_method_id', $payload['payment_method_id'], $payload['payment_method_id'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':payment_snapshot', $payload['payment_snapshot']);
            $stmt->bindValue(':payment_options', $payload['payment_options']);
            $stmt->bindValue(':payment_selected_index', $payload['payment_selected_index'], \PDO::PARAM_INT);
            $stmt->bindValue(':delivery_start', $payload['delivery_start']);
            $stmt->bindValue(':delivery_end', $payload['delivery_end']);
            $stmt->bindValue(':penalty_terms', $payload['penalty_terms']);
            $stmt->bindValue(':terms', $payload['terms']);
            $stmt->execute();
            $proposalId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO proposal_items (proposal_id, service_id, is_bonus, catalog_price, description, qty, unit_price, total) VALUES (:proposal_id, :service_id, :is_bonus, :catalog_price, :description, :qty, :unit_price, :total)');
            foreach ($payload['items'] as $item) {
                $itemStmt->bindValue(':proposal_id', $proposalId, \PDO::PARAM_INT);
                $sid = isset($item['service_id']) ? (int) $item['service_id'] : 0;
                $itemStmt->bindValue(':service_id', $sid > 0 ? $sid : null, $sid > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
                $itemStmt->bindValue(':is_bonus', (int) ($item['is_bonus'] ?? 0), \PDO::PARAM_INT);
                $cp = isset($item['catalog_price']) ? (float) $item['catalog_price'] : null;
                $itemStmt->bindValue(':catalog_price', $cp, $cp === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
                $itemStmt->bindValue(':description', $item['description']);
                $itemStmt->bindValue(':qty', $item['qty']);
                $itemStmt->bindValue(':unit_price', $item['unit_price']);
                $itemStmt->bindValue(':total', $item['total']);
                $itemStmt->execute();
            }

            $this->replaceMilestones($proposalId, $payload['milestones'] ?? []);

            $pdo->commit();
            return $proposalId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $payload): void
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE proposals SET client_id = :client_id, title = :title, description = :description, notes = :notes, subtotal = :subtotal, discount_percent = :discount_percent, discount_amount = :discount_amount, total = :total, payment_method_id = :payment_method_id, payment_snapshot = :payment_snapshot, payment_options = :payment_options, payment_selected_index = :payment_selected_index, delivery_start = :delivery_start, delivery_end = :delivery_end, penalty_terms = :penalty_terms, terms = :terms WHERE id = :id');
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->bindValue(':client_id', $payload['client_id'], \PDO::PARAM_INT);
            $stmt->bindValue(':title', $payload['title']);
            $stmt->bindValue(':description', $payload['description']);
            $stmt->bindValue(':notes', $payload['notes']);
            $stmt->bindValue(':subtotal', $payload['subtotal']);
            $stmt->bindValue(':discount_percent', $payload['discount_percent']);
            $stmt->bindValue(':discount_amount', $payload['discount_amount']);
            $stmt->bindValue(':total', $payload['total']);
            $stmt->bindValue(':payment_method_id', $payload['payment_method_id'], $payload['payment_method_id'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':payment_snapshot', $payload['payment_snapshot']);
            $stmt->bindValue(':payment_options', $payload['payment_options']);
            $stmt->bindValue(':payment_selected_index', $payload['payment_selected_index'], \PDO::PARAM_INT);
            $stmt->bindValue(':delivery_start', $payload['delivery_start']);
            $stmt->bindValue(':delivery_end', $payload['delivery_end']);
            $stmt->bindValue(':penalty_terms', $payload['penalty_terms']);
            $stmt->bindValue(':terms', $payload['terms']);
            $stmt->execute();

            $del = $pdo->prepare('DELETE FROM proposal_items WHERE proposal_id = :id');
            $del->bindValue(':id', $id, \PDO::PARAM_INT);
            $del->execute();

            $itemStmt = $pdo->prepare('INSERT INTO proposal_items (proposal_id, service_id, is_bonus, catalog_price, description, qty, unit_price, total) VALUES (:proposal_id, :service_id, :is_bonus, :catalog_price, :description, :qty, :unit_price, :total)');
            foreach ($payload['items'] as $item) {
                $itemStmt->bindValue(':proposal_id', $id, \PDO::PARAM_INT);
                $sid = isset($item['service_id']) ? (int) $item['service_id'] : 0;
                $itemStmt->bindValue(':service_id', $sid > 0 ? $sid : null, $sid > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
                $itemStmt->bindValue(':is_bonus', (int) ($item['is_bonus'] ?? 0), \PDO::PARAM_INT);
                $cp = isset($item['catalog_price']) ? (float) $item['catalog_price'] : null;
                $itemStmt->bindValue(':catalog_price', $cp, $cp === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
                $itemStmt->bindValue(':description', $item['description']);
                $itemStmt->bindValue(':qty', $item['qty']);
                $itemStmt->bindValue(':unit_price', $item['unit_price']);
                $itemStmt->bindValue(':total', $item['total']);
                $itemStmt->execute();
            }

            $this->replaceMilestones($id, $payload['milestones'] ?? []);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function milestones(int $proposalId): array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, title, due_date, notes, penalty_terms FROM proposal_milestones WHERE proposal_id = :id ORDER BY id ASC');
        $stmt->bindValue(':id', $proposalId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function replaceMilestones(int $proposalId, array $milestones): void
    {
        $pdo = DB::pdo();
        $del = $pdo->prepare('DELETE FROM proposal_milestones WHERE proposal_id = :id');
        $del->bindValue(':id', $proposalId, \PDO::PARAM_INT);
        $del->execute();

        if (!is_array($milestones) || count($milestones) === 0) {
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO proposal_milestones (proposal_id, title, due_date, notes, penalty_terms, created_at) VALUES (:proposal_id, :title, :due_date, :notes, :penalty_terms, NOW())');
        foreach ($milestones as $m) {
            if (!is_array($m)) {
                continue;
            }
            $title = trim((string) ($m['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $due = $m['due_date'] ?? null;
            $notes = $m['notes'] ?? null;
            $penalty = $m['penalty_terms'] ?? null;

            $stmt->bindValue(':proposal_id', $proposalId, \PDO::PARAM_INT);
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':due_date', $due);
            $stmt->bindValue(':notes', $notes);
            $stmt->bindValue(':penalty_terms', $penalty);
            $stmt->execute();
        }
    }


    public function updateStatus(int $id, string $status): void
    {
        $allowed = ['rascunho', 'enviada', 'aprovada', 'recusada'];
        if (!in_array($status, $allowed, true)) {
            return;
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE proposals SET status = :status WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':status', $status);
        $stmt->execute();
    }

    public function setContractDecision(int $id, bool $requiresContract, ?int $templateId, ?string $reason): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE proposals SET requires_contract = :requires_contract, contract_template_id = :contract_template_id, contract_policy_reason = :contract_policy_reason WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':requires_contract', $requiresContract ? 1 : 0, \PDO::PARAM_INT);
        $stmt->bindValue(':contract_template_id', $templateId, $templateId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':contract_policy_reason', $reason);
        $stmt->execute();
    }

    public function markConverted(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE proposals SET converted_project = 1 WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }
}
