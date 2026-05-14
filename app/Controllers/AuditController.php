<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;
use App\Repositories\AuditLogRepository;
use App\Repositories\UserListRepository;

final class AuditController
{
    public function index(Request $request): void
    {
        $entityType = trim((string) $request->input('entity_type', ''));
        $entityId = (int) $request->input('entity_id', 0);
        $limit = (int) $request->input('limit', 200);

        $rows = (new AuditLogRepository())->list($entityType !== '' ? $entityType : null, $entityId > 0 ? $entityId : null, $limit);
        $actorIds = [];
        foreach ($rows as $r) {
            $aid = (int) ($r['actor_id'] ?? 0);
            if ($aid > 0) {
                $actorIds[] = $aid;
            }
        }
        $names = (new UserListRepository())->namesByIds($actorIds);

        View::render('audit/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'rows' => $rows,
            'filters' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'limit' => $limit,
            ],
            'actorNames' => $names,
        ]);
    }
}

