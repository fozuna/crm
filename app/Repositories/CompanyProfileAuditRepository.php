<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class CompanyProfileAuditRepository
{
    public function list(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT id, actor_id, action, source, diff, created_at FROM company_profile_audit ORDER BY id DESC LIMIT ' . $limit);
        return $stmt->fetchAll();
    }

    public function create(int $actorId, string $action, string $source, array $diff): void
    {
        $action = in_array($action, ['create', 'update', 'delete', 'logo_update'], true) ? $action : 'update';
        $source = in_array($source, ['ui', 'api'], true) ? $source : 'ui';
        $json = json_encode($diff, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            $json = '{}';
        }

        $pdo = DB::pdo();
        $stmt = $pdo->prepare('INSERT INTO company_profile_audit (actor_id, action, source, diff, created_at) VALUES (:actor_id, :action, :source, :diff, NOW())');
        $stmt->bindValue(':actor_id', $actorId, \PDO::PARAM_INT);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':source', $source);
        $stmt->bindValue(':diff', $json);
        $stmt->execute();
    }
}

