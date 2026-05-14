<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditLogRepository;

final class AuditApiController
{
    public function index(Request $request): void
    {
        $entityType = trim((string) $request->input('entity_type', ''));
        $entityId = (int) $request->input('entity_id', 0);
        $limit = (int) $request->input('limit', 200);
        $rows = (new AuditLogRepository())->list($entityType !== '' ? $entityType : null, $entityId > 0 ? $entityId : null, $limit);
        Response::json(['ok' => true, 'data' => $rows]);
    }
}

