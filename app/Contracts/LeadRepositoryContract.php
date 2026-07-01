<?php
declare(strict_types=1);

namespace App\Contracts;

interface LeadRepositoryContract
{
    public function listKanban(string $query = ''): array;
    public function find(int $id): ?array;
    public function create(array $data, int $actorId): int;
    public function update(int $id, array $data, int $actorId): void;
    public function updateStage(int $id, string $stage, int $actorId): void;
    public function markConverted(int $id, int $clientId, int $actorId): void;
    public function duplicateCounts(array $data, ?int $excludeLeadId = null): array;
}
