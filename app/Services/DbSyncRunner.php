<?php
declare(strict_types=1);

namespace App\Services;

final class DbSyncRunner
{
    public function __construct(
        private readonly ?DbUpgradeRunner $upgradeRunner = null,
        private readonly ?SqlScriptParser $parser = null,
        private readonly ?DbLifecycleLogger $logger = null,
    ) {
    }

    public function run(\PDO $pdo, array $options = []): array
    {
        $environment = trim((string) ($options['environment'] ?? 'manual'));
        $schemaPath = (string) ($options['schema_path'] ?? (__DIR__ . '/../../database/schema.sql'));
        $upgradePath = (string) ($options['upgrade_path'] ?? (__DIR__ . '/../../database/upgrade.sql'));

        $this->logger()->write('db_sync_start', [
            'environment' => $environment,
            'schema_path' => $schemaPath,
            'upgrade_path' => $upgradePath,
        ]);

        $schemaResult = $this->executeSqlFile($pdo, $schemaPath, 'schema');
        $upgradeResult = $this->upgrade()->run($pdo, $this->logCallback('upgrade', $environment, $upgradePath));
        $inspect = $this->upgrade()->inspect($pdo);

        $result = [
            'ok' => !($inspect['pending'] ?? true),
            'environment' => $environment,
            'schema' => $schemaResult,
            'upgrade' => $upgradeResult,
            'inspect' => $inspect,
        ];

        $this->logger()->write($result['ok'] ? 'db_sync_finish' : 'db_sync_incomplete', $result);

        if (!($result['ok'] ?? false)) {
            throw new \RuntimeException('A sincronização do banco terminou com pendências estruturais.');
        }

        return $result;
    }

    private function executeSqlFile(\PDO $pdo, string $path, string $stage): array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException(basename($path) . ' não encontrado ou vazio.');
        }

        $statements = $this->parser()->split($raw);
        $applied = 0;
        $skipped = 0;

        foreach ($statements as $index => $sql) {
            try {
                $this->executeStatement($pdo, $sql);
                $applied++;
            } catch (\Throwable $e) {
                if ($this->shouldIgnoreException($e)) {
                    $skipped++;
                    continue;
                }

                $this->logger()->write('db_sync_statement_error', [
                    'stage' => $stage,
                    'path' => $path,
                    'statement_index' => $index + 1,
                    'statement_preview' => substr(trim($sql), 0, 500),
                ], $e);

                throw new \RuntimeException(
                    'Falha ao aplicar ' . basename($path) . ' no statement #' . ($index + 1) . ': ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        $result = [
            'path' => $path,
            'statements' => count($statements),
            'applied' => $applied,
            'skipped' => $skipped,
        ];

        $this->logger()->write('db_sync_stage_finish', [
            'stage' => $stage,
            'result' => $result,
        ]);

        return $result;
    }

    private function shouldIgnoreException(\Throwable $e): bool
    {
        if (!($e instanceof \PDOException)) {
            return false;
        }

        $errorCode = $e->errorInfo[1] ?? null;
        return is_int($errorCode) && in_array($errorCode, [1007, 1050, 1060, 1061, 1062, 1091, 1359, 1826], true);
    }

    private function executeStatement(\PDO $pdo, string $sql): void
    {
        $stmt = $pdo->prepare($sql);
        if (!$stmt instanceof \PDOStatement) {
            throw new \RuntimeException('Falha ao preparar statement SQL.');
        }

        $stmt->execute();
        do {
            if ($stmt->columnCount() > 0) {
                $stmt->fetchAll();
            }
        } while ($stmt->nextRowset());
        $stmt->closeCursor();
    }

    private function logCallback(string $stage, string $environment, string $path): callable
    {
        return function (string $event, array $context = [], ?\Throwable $exception = null) use ($stage, $environment, $path): void {
            $this->logger()->write('db_sync_' . $event, [
                'stage' => $stage,
                'environment' => $environment,
                'path' => $path,
                'context' => $context,
            ], $exception);
        };
    }

    private function upgrade(): DbUpgradeRunner
    {
        return $this->upgradeRunner ?? new DbUpgradeRunner();
    }

    private function parser(): SqlScriptParser
    {
        return $this->parser ?? new SqlScriptParser();
    }

    private function logger(): DbLifecycleLogger
    {
        return $this->logger ?? new DbLifecycleLogger();
    }
}
