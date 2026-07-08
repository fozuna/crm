<?php
declare(strict_types=1);

namespace App\Services;

final class DatabaseStructureOutOfSyncException extends \RuntimeException
{
    public function __construct(
        private readonly string $referenceId,
        private readonly array $inspect,
        string $message = 'Estrutura de banco desatualizada. Execute o sincronizador oficial antes de disponibilizar o sistema.'
    ) {
        parent::__construct($message);
    }

    public function referenceId(): string
    {
        return $this->referenceId;
    }

    public function inspect(): array
    {
        return $this->inspect;
    }
}
