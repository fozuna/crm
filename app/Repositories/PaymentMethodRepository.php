<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class PaymentMethodRepository
{
    public function all(): array
    {
        $pdo = DB::pdo();
        return $pdo->query('SELECT id, name, type, active, discount_percent, installments_count, interval_days, has_down_payment, down_payment_percent, special_terms, created_at FROM payment_methods ORDER BY active DESC, id DESC')->fetchAll();
    }

    public function active(): array
    {
        $pdo = DB::pdo();
        return $pdo->query('SELECT id, name, type, discount_percent, installments_count, interval_days, has_down_payment, down_payment_percent, special_terms FROM payment_methods WHERE active = 1 ORDER BY id DESC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, name, type, active, discount_percent, installments_count, interval_days, has_down_payment, down_payment_percent, special_terms FROM payment_methods WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $data): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO payment_methods (name, type, active, discount_percent, installments_count, interval_days, has_down_payment, down_payment_percent, special_terms, created_at) VALUES (:name, :type, :active, :discount_percent, :installments_count, :interval_days, :has_down_payment, :down_payment_percent, :special_terms, NOW())');
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':active', $data['active'], \PDO::PARAM_INT);
        $stmt->bindValue(':discount_percent', $data['discount_percent']);
        $stmt->bindValue(':installments_count', $data['installments_count'], \PDO::PARAM_INT);
        $stmt->bindValue(':interval_days', $data['interval_days'], \PDO::PARAM_INT);
        $stmt->bindValue(':has_down_payment', $data['has_down_payment'], \PDO::PARAM_INT);
        $stmt->bindValue(':down_payment_percent', $data['down_payment_percent']);
        $stmt->bindValue(':special_terms', $data['special_terms']);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE payment_methods SET name = :name, type = :type, active = :active, discount_percent = :discount_percent, installments_count = :installments_count, interval_days = :interval_days, has_down_payment = :has_down_payment, down_payment_percent = :down_payment_percent, special_terms = :special_terms WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':type', $data['type']);
        $stmt->bindValue(':active', $data['active'], \PDO::PARAM_INT);
        $stmt->bindValue(':discount_percent', $data['discount_percent']);
        $stmt->bindValue(':installments_count', $data['installments_count'], \PDO::PARAM_INT);
        $stmt->bindValue(':interval_days', $data['interval_days'], \PDO::PARAM_INT);
        $stmt->bindValue(':has_down_payment', $data['has_down_payment'], \PDO::PARAM_INT);
        $stmt->bindValue(':down_payment_percent', $data['down_payment_percent']);
        $stmt->bindValue(':special_terms', $data['special_terms']);
        $stmt->execute();
    }

    public function delete(int $id): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('DELETE FROM payment_methods WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }
}

