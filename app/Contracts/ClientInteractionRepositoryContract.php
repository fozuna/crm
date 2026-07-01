<?php
declare(strict_types=1);

namespace App\Contracts;

interface ClientInteractionRepositoryContract
{
    public function createHistorical(int $clientId, string $kind, string $note, string $createdAt): int;
}
