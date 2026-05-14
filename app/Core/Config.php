<?php
declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function load(string $envPath, string $fallbackPath): array
    {
        $fallback = is_file($fallbackPath) ? (require $fallbackPath) : [];
        $env = [];

        if (is_file($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));

                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $quote = $value[0];
                    if (str_ends_with($value, $quote)) {
                        $value = substr($value, 1, -1);
                    }
                }

                if (in_array(strtolower($value), ['true', 'false'], true)) {
                    $env[$key] = strtolower($value) === 'true';
                    continue;
                }

                if (is_numeric($value) && !str_contains($value, '.')) {
                    $env[$key] = (int) $value;
                    continue;
                }

                $env[$key] = $value;
            }
        }

        return array_merge($fallback, $env);
    }

    public static function setAll(array $items): void
    {
        self::$items = $items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$items[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$items);
    }

    public static function all(): array
    {
        return self::$items;
    }
}

