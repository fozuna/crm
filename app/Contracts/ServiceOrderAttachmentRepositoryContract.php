<?php
declare(strict_types=1);

namespace App\Contracts;

interface ServiceOrderAttachmentRepositoryContract
{
    public function create(int $serviceOrderId, array $data, ?int $actorId): int;
    public function listByServiceOrder(int $serviceOrderId): array;
    public function find(int $id): ?array;
    public function delete(int $id): void;
}
