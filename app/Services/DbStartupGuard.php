<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

final class DbStartupGuard
{
    public function __construct(
        private readonly ?DbUpgradeRunner $upgradeRunner = null,
        private readonly ?DbLifecycleLogger $logger = null,
    ) {
    }

    public function enforce(): void
    {
        try {
            $inspect = $this->upgrade()->inspect(DB::pdo());
        } catch (\Throwable $e) {
            $this->logger()->write('db_startup_validation_failed', [], $e);
            throw $e;
        }

        if (!(bool) ($inspect['pending'] ?? false)) {
            $this->logger()->write('db_startup_validation_ok', [
                'inspect' => $inspect,
            ]);
            return;
        }

        $referenceId = bin2hex(random_bytes(6));
        $this->logger()->write('db_startup_blocked', [
            'reference_id' => $referenceId,
            'inspect' => $inspect,
            'message' => 'Sistema bloqueado ate sincronizacao oficial do banco.',
        ]);

        throw new DatabaseStructureOutOfSyncException($referenceId, $inspect);
    }

    private function upgrade(): DbUpgradeRunner
    {
        return $this->upgradeRunner ?? new DbUpgradeRunner();
    }

    private function logger(): DbLifecycleLogger
    {
        return $this->logger ?? new DbLifecycleLogger();
    }
}
