<?php
declare(strict_types=1);

namespace App\Services;

final class Money
{
    public static function parseBRL(string $raw): float
    {
        $s = trim($raw);
        if ($s === '') {
            return 0.0;
        }

        $s = str_replace(['R$', 'r$', ' '], '', $s);
        $s = preg_replace('/[^0-9,\.-]/', '', $s);
        $s = is_string($s) ? $s : '';
        $s = trim($s);
        if ($s === '' || $s === '-' || $s === ',' || $s === '.') {
            return 0.0;
        }

        $negative = false;
        if (str_starts_with($s, '-')) {
            $negative = true;
            $s = ltrim($s, '-');
        }

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, '.')) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s) === 1) {
                $s = str_replace('.', '', $s);
            }
        }

        $v = (float) $s;
        if (!is_finite($v)) {
            return 0.0;
        }
        if ($negative) {
            $v = -$v;
        }
        return $v;
    }
}

