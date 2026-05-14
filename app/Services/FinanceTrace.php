<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class FinanceTrace
{
    public static function enabled(): bool
    {
        return (bool) Config::get('FINANCE_TRACE', false) || (bool) Config::get('APP_DEBUG', false);
    }

    public static function log(string $event, array $data = []): void
    {
        if (!self::enabled()) {
            return;
        }
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $row = [
            'ts' => date('c'),
            'event' => $event,
            'data' => $data,
        ];
        @file_put_contents($dir . '/finance.log', json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
    }
}

