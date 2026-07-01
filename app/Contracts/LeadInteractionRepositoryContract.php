<?php
declare(strict_types=1);

namespace App\Contracts;

interface LeadInteractionRepositoryContract
{
    public function listByLead(int $leadId): array;
    public function create(int $leadId, string $kind, string $note, ?int $createdBy): int;
}
