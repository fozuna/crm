<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class UserRepository
{
    private function hasRoleColumn(): bool
    {
        static $cached = null;
        if (is_bool($cached)) {
            return $cached;
        }
        try {
            $pdo = DB::pdo();
            $db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === '') {
                $cached = false;
                return $cached;
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role'");
            $stmt->bindValue(':db', $db);
            $stmt->execute();
            $cached = ((int) $stmt->fetchColumn()) > 0;
            return $cached;
        } catch (\Throwable) {
            $cached = false;
            return $cached;
        }
    }

    public function findByEmail(string $email): ?array
    {
        $pdo = DB::pdo();
        $select = $this->hasRoleColumn()
            ? 'SELECT id, name, email, password_hash, is_admin, role FROM users WHERE email = :email LIMIT 1'
            : 'SELECT id, name, email, password_hash, is_admin FROM users WHERE email = :email LIMIT 1';
        $stmt = $pdo->prepare($select);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch();
        if (is_array($row) && !$this->hasRoleColumn()) {
            $row['role'] = null;
        }
        return is_array($row) ? $row : null;
    }

    public function createAdmin(string $name, string $email, string $password): int
    {
        $pdo = DB::pdo();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $sql = $this->hasRoleColumn()
            ? "INSERT INTO users (name, email, password_hash, is_admin, role, created_at) VALUES (:name, :email, :hash, 1, 'admin', NOW())"
            : 'INSERT INTO users (name, email, password_hash, is_admin, created_at) VALUES (:name, :email, :hash, 1, NOW())';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':hash', $hash);
        $stmt->execute();
        return (int) $pdo->lastInsertId();
    }
}
