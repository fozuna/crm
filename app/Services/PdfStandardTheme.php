<?php
declare(strict_types=1);

namespace App\Services;

final class PdfStandardTheme
{
    public static function renderHeaderMinimal(
        ProfessionalPdf $pdf,
        array $branding,
        int $pageW,
        int $pageH,
        int $margin,
        int $headerH,
        int $accentH,
        int $gapBelowHeader,
        int $logoMaxW,
        int $logoMaxH
    ): int {
        $primary = self::hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $accent = self::hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
        $logoPath = self::resolveHeaderLogoPath($branding, $primary);
        $cnpj = self::formatCnpj((string) ($branding['company_cnpj'] ?? ''));

        $pdf->setFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->rect(0, $pageH - $headerH, $pageW, $headerH, 'F');
        if ($accentH > 0) {
            $pdf->setFillColor($accent[0], $accent[1], $accent[2]);
            $pdf->rect(0, $pageH - $headerH - $accentH, $pageW, $accentH, 'F');
        }

        $text = self::bestTextOn($primary);
        $pdf->setFillColor($text[0], $text[1], $text[2]);
        $pdf->setFont('F2', 12);

        $x0 = $margin;
        $x1 = $pageW - $margin;

        if ($logoPath !== '' && is_file($logoPath)) {
            [$lw, $lh] = self::fitLogoBox($logoPath, $logoMaxW, $logoMaxH);
            if ($lw > 0 && $lh > 0) {
                $ly = ($pageH - $headerH) + (int) floor(($headerH - $lh) / 2);
                $pdf->imageFromFile($x0, $ly, $lw, $lh, $logoPath);
            }
        }

        if ($cnpj !== '') {
            $fontSize = 11;
            $pdf->setFont('F2', $fontSize);
            $w = self::approxTextWidth(self::toPdfEncoding($cnpj), $fontSize);
            $x = (int) floor($x1 - $w);
            if ($x < $x0) {
                $x = $x0;
            }
            $y = ($pageH - $headerH) + (int) floor(($headerH - $fontSize) / 2) + 1;
            $pdf->text($x, $y, $cnpj);
        }

        return ($pageH - $headerH) - $gapBelowHeader;
    }

    public static function appendCenteredFooterPaginationAndContact(
        ProfessionalPdf $pdf,
        int $pageW,
        string $contactLine,
        int $y,
        array $rgb,
        int $fontSize
    ): void {
        $ref = new \ReflectionClass($pdf);
        $prop = $ref->getProperty('pages');
        $prop->setAccessible(true);
        $pages = (array) $prop->getValue($pdf);
        $total = count($pages);

        $rg = sprintf('%.3f %.3f %.3f rg', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);

        foreach ($pages as $i => $ops) {
            $label = 'Página ' . ($i + 1) . ' de ' . $total . ' • ' . $contactLine;
            $encoded = self::toPdfEncoding($label);
            $w = self::approxTextWidth($encoded, $fontSize);
            $x = (int) floor(($pageW / 2) - ($w / 2));
            if ($x < 10) {
                $x = 10;
            }
            $ops[] = $rg . ' BT /F1 ' . $fontSize . ' Tf ' . $x . ' ' . $y . ' Td (' . self::escapePdfString($encoded) . ') Tj ET';
            $pages[$i] = $ops;
        }

        $prop->setValue($pdf, $pages);
    }

    private static function escapePdfString(string $s): string
    {
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', (string) $s);
        return (string) $s;
    }

    private static function toPdfEncoding(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = self::normalizeUtf8($text);
        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
            if (is_string($tmp) && $tmp !== '') {
                return $tmp;
            }
        }
        return $normalized;
    }

    private static function approxTextWidth(string $text, int $fontSize): float
    {
        $spaces = substr_count($text, ' ');
        $len = strlen(str_replace(' ', '', $text));
        return ($len * 0.52 * $fontSize) + ($spaces * 0.28 * $fontSize);
    }

    private static function bestTextOn(array $bg): array
    {
        $y = (0.2126 * $bg[0]) + (0.7152 * $bg[1]) + (0.0722 * $bg[2]);
        return $y < 140 ? [255, 255, 255] : [17, 24, 39];
    }

    private static function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj);
        $digits = is_string($digits) ? $digits : '';
        if (strlen($digits) !== 14) {
            return '';
        }
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }

    private static function fitLogoBox(string $path, int $maxW, int $maxH): array
    {
        if (!function_exists('getimagesize')) {
            return [0, 0];
        }
        $info = @getimagesize($path);
        if ($info === false || !isset($info[0], $info[1])) {
            return [0, 0];
        }
        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        if ($srcW <= 0 || $srcH <= 0) {
            return [0, 0];
        }
        $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
        return [(int) floor($srcW * $scale), (int) floor($srcH * $scale)];
    }

    private static function resolveHeaderLogoPath(array $branding, array $primary): string
    {
        $logoPath = trim((string) ($branding['logo_path'] ?? ''));
        $logoLight = trim((string) ($branding['logo_light_path'] ?? ''));
        $logoDark = trim((string) ($branding['logo_dark_path'] ?? ''));

        $backgroundIsDark = self::bestTextOn($primary) === [255, 255, 255];
        if ($backgroundIsDark && $logoLight !== '' && is_file($logoLight)) {
            return $logoLight;
        }
        if (!$backgroundIsDark && $logoDark !== '' && is_file($logoDark)) {
            return $logoDark;
        }
        if ($logoPath !== '' && is_file($logoPath)) {
            return $logoPath;
        }
        if ($logoLight !== '' && is_file($logoLight)) {
            return $logoLight;
        }
        if ($logoDark !== '' && is_file($logoDark)) {
            return $logoDark;
        }
        return '';
    }

    private static function normalizeUtf8(string $text): string
    {
        if (preg_match('//u', $text) === 1) {
            return $text;
        }

        if (function_exists('mb_convert_encoding')) {
            $candidate = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            if (is_string($candidate) && $candidate !== '' && preg_match('//u', $candidate) === 1) {
                return $candidate;
            }
        }

        return utf8_encode($text);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [41, 50, 65];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
