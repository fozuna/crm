<?php
declare(strict_types=1);

namespace App\Contracts;

interface ClientRepositoryContract
{
    public function find(int $id): ?array;
    public function findBySourceLeadId(int $leadId): ?array;
    public function createProposalProspectFromLead(array $lead): int;
    public function promoteLeadProspectToActive(int $clientId, array $data): void;
    public function createFromLead(array $data): int;
}
