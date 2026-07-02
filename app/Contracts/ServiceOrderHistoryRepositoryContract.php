<?php
declare(strict_types=1);

namespace App\Contracts;

interface ServiceOrderHistoryRepositoryContract
{
    public function create(
        int $serviceOrderId,
        string $action,
        ?int $actorId,
        ?string $fieldName = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        ?string $message = null,
        ?array $metadata = null
    ): int;

    public function listByServiceOrder(int $serviceOrderId): array;
}
