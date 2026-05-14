<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class ServiceRepository
{
    public function paginated(array $filters, int $page = 1, int $perPage = 20): array
    {
        $pdo = DB::pdo();
        $page = max(1, $page);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->filtersWhere($filters);

        $sort = (string) ($filters['sort'] ?? 'name_asc');
        $order = 'name ASC';
        if ($sort === 'name_desc') {
            $order = 'name DESC';
        } elseif ($sort === 'updated_desc') {
            $order = 'updated_at DESC, id DESC';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM services' . $whereSql);
        $this->bindAll($countStmt, $params);
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT id, name, default_price, active, description, is_bonus, created_at, updated_at FROM services' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'rows' => $rows,
        ];
    }

    public function activeList(bool $includeBonus = true): array
    {
        $pdo = DB::pdo();
        $sql = 'SELECT id, name, default_price, active, description, is_bonus, updated_at FROM services WHERE active = 1';
        if (!$includeBonus) {
            $sql .= ' AND is_bonus = 0';
        }
        $sql .= ' ORDER BY name ASC';
        return $pdo->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('SELECT id, name, default_price, active, description, is_bonus, created_at, updated_at FROM services WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function existsByName(string $name, ?int $exceptId = null): bool
    {
        $pdo = DB::pdo();
        $sql = 'SELECT id FROM services WHERE name = :n';
        if (is_int($exceptId) && $exceptId > 0) {
            $sql .= ' AND id <> :id';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':n', $name);
        if (is_int($exceptId) && $exceptId > 0) {
            $stmt->bindValue(':id', $exceptId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchColumn() !== false;
    }

    public function create(array $data): int
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO services (name, default_price, active, description, is_bonus, created_at, updated_at) VALUES (:n, :p, :a, :d, :b, NOW(), NOW())');
        $stmt->bindValue(':n', (string) $data['name']);
        $stmt->bindValue(':p', (float) $data['default_price']);
        $stmt->bindValue(':a', (int) $data['active'], \PDO::PARAM_INT);
        $stmt->bindValue(':d', (string) $data['description']);
        $stmt->bindValue(':b', (int) $data['is_bonus'], \PDO::PARAM_INT);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $pdo = DB::pdo();
        $stmt = $pdo->prepare('UPDATE services SET name = :n, default_price = :p, active = :a, description = :d, is_bonus = :b, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':n', (string) $data['name']);
        $stmt->bindValue(':p', (float) $data['default_price']);
        $stmt->bindValue(':a', (int) $data['active'], \PDO::PARAM_INT);
        $stmt->bindValue(':d', (string) $data['description']);
        $stmt->bindValue(':b', (int) $data['is_bonus'], \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function filtersWhere(array $filters): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(name LIKE :q OR description LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $status = (string) ($filters['status'] ?? '');
        if ($status === 'ativo') {
            $where[] = 'active = 1';
        } elseif ($status === 'inativo') {
            $where[] = 'active = 0';
        }

        $type = (string) ($filters['type'] ?? '');
        if ($type === 'bonus') {
            $where[] = 'is_bonus = 1';
        } elseif ($type === 'normal') {
            $where[] = 'is_bonus = 0';
        }

        $sql = '';
        if (count($where) > 0) {
            $sql = ' WHERE ' . implode(' AND ', $where);
        }
        return [$sql, $params];
    }

    private function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
    }
}

