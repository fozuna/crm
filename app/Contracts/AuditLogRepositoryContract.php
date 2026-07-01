<?php
declare(strict_types=1);

namespace App\Contracts;

interface AuditLogRepositoryContract
{
    public function create(string $entityType, int $entityId, string $action, ?int $actorId, ?array $data): void;
}
