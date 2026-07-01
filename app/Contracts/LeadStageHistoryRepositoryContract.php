<?php
declare(strict_types=1);

namespace App\Contracts;

interface LeadStageHistoryRepositoryContract
{
    public function create(int $leadId, ?string $fromStage, string $toStage, ?int $actorId, string $action = 'move', ?string $note = null): int;
    public function listByLead(int $leadId): array;
}
