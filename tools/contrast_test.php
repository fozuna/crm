<?php
declare(strict_types=1);

final class Contrast
{
    public static function ratio(string $hex1, string $hex2): float
    {
        $l1 = self::luminance($hex1);
        $l2 = self::luminance($hex2);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $rs = self::srgbToLinear($r / 255);
        $gs = self::srgbToLinear($g / 255);
        $bs = self::srgbToLinear($b / 255);
        return 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
    }

    private static function srgbToLinear(float $c): float
    {
        if ($c <= 0.04045) {
            return $c / 12.92;
        }
        return pow(($c + 0.055) / 1.055, 2.4);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            throw new RuntimeException('Hex inválido: ' . $hex);
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

$pairs = [
    ['Campo input: texto', '#0f172a', '#f8fafc', 4.5],
    ['Campo input: placeholder', '#64748b', '#f8fafc', 4.5],
    ['Botão principal (ícone)', '#293241', '#ee6c4d', 3.0],
    ['Sidebar', '#fefefe', '#293241', 4.5],
    ['Badge', '#0f172a', '#f1f5f9', 4.5],
    ['Texto padrão', '#0f172a', '#f8fafc', 4.5],
    ['PDF: texto corpo', '#1a1a1a', '#ffffff', 4.5],
    ['PDF: título/heading', '#111827', '#ffffff', 4.5],
    ['PDF: separador/linha', '#6b7280', '#ffffff', 3.0],
];

$fail = false;
foreach ($pairs as [$label, $fg, $bg, $min]) {
    $ratio = Contrast::ratio($fg, $bg);
    $ok = $ratio >= $min;
    $fail = $fail || !$ok;
    echo sprintf("%s: %s on %s => %.2f (%s, min %.1f)\n", $label, $fg, $bg, $ratio, $ok ? 'OK' : 'FAIL', $min);
}

if ($fail) {
    exit(1);
}
