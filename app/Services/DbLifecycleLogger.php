<?php
declare(strict_types=1);

namespace App\Services;

final class DbLifecycleLogger
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile !== null && trim($logFile) !== ''
            ? $logFile
            : __DIR__ . '/../../storage/logs/db-lifecycle.log';
    }

    public function write(string $event, array $context = [], ?\Throwable $exception = null): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $row = [
            'ts' => date('c'),
            'event' => $event,
            'sapi' => PHP_SAPI,
            'uri' => (string) ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'context' => $context,
        ];

        if ($exception !== null) {
            $row['exception'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($line)) {
            $line = '{"ts":"' . date('c') . '","event":"logger_json_encode_failed"}';
        }

        @file_put_contents($this->logFile, $line . "\n", FILE_APPEND);
    }

    public function path(): string
    {
        return $this->logFile;
    }
}
