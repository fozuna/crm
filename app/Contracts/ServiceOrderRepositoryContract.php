<?php
declare(strict_types=1);

namespace App\Contracts;

interface ServiceOrderRepositoryContract
{
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array;
    public function reportRows(array $filters, int $limit = 2000): array;
    public function find(int $id): ?array;
    public function create(array $data, int $actorId): int;
    public function update(int $id, array $data, int $actorId): void;
    public function updateStatus(int $id, string $status, int $actorId, ?string $completedAt = null): void;
    public function markDeleted(int $id, int $actorId): void;
    public function attachFinancialReceivable(int $id, ?int $receivableId, int $actorId, ?string $status = null): void;
    public function nextSequence(): int;
    public function listByClient(int $clientId, int $limit = 20): array;
}
