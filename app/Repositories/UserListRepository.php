<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class UserListRepository
{
    public function all(): array
    {
        $pdo = DB::pdo();
        return $pdo->query("SELECT id, name, email, is_admin, role FROM users ORDER BY name ASC")->fetchAll();
    }

    public function namesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($v) => is_int($v) && $v > 0)));
        if (count($ids) === 0) {
            return [];
        }

        $pdo = DB::pdo();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id IN (' . $in . ')');
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (string) $r['name'];
        }
        return $out;
    }
}

