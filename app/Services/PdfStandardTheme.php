<?php
declare(strict_types=1);

namespace App\Services;

final class PdfStandardTheme
{
    /**
     * Paleta neutra compartilhada por todos os geradores de PDF, para que título,
     * texto e linhas divisórias tenham sempre o mesmo tom em qualquer documento
     * do sistema — em vez de cada gerador definir seu próprio cinza.
     */
    /** Cinza neutro claro (slate-200) usado em fios finos e bordas discretas. */
    public const HAIRLINE = [226, 232, 240];
    /** Cinza claro (slate-100) usado em fundos de zebra-striping de tabelas. */
    public const SURFACE = [248, 250, 252];
    /** Cinza médio (slate-500) para texto discreto (legendas, rodapé, metadados). */
    public const MUTED = [100, 116, 139];
    /** Cinza de leitura (slate-700) para o corpo de texto. */
    public const BODY = [51, 65, 85];
    /** Cinza escuro (slate-900) para títulos e texto de destaque. */
    public const INK = [15, 23, 42];

    /**
     * Quebra o texto em linhas medidas pela largura real estimada (não por
     * contagem de caracteres) e já calcula o word-spacing necessário para
     * justificar cada linha — exceto a última do parágrafo, que fica alinhada
     * à esquerda, seguindo a convenção tipográfica de texto justificado.
     *
     * A quebra por contagem de caracteres usada anteriormente gerava linhas
     * bem mais curtas que a largura real disponível (a estimativa de largura
     * de fonte não é linear com número de caracteres), o que fazia o gap a
     * distribuir ultrapassar qualquer limite razoável de word-spacing e a
     * justificação nunca era aplicada na prática — o texto sempre saía
     * alinhado à esquerda apesar do código de justificação existir.
     *
     * @return array<int, array{text: string, wordSpacing: float}>
     */
    public static function wrapJustified(string $text, int $fontSize, int $maxWidth): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($normalized === '') {
            return [];
        }

        $words = explode(' ', $normalized);
        $lines = [];
        $current = [];
        foreach ($words as $word) {
            $candidate = $current === [] ? [$word] : [...$current, $word];
            $width = self::approxTextWidth(implode(' ', $candidate), $fontSize);
            if ($width > $maxWidth && $current !== []) {
                $lines[] = $current;
                $current = [$word];
                continue;
            }
            $current = $candidate;
        }
        if ($current !== []) {
            $lines[] = $current;
        }

        $lastIndex = count($lines) - 1;
        $result = [];
        foreach ($lines as $i => $lineWords) {
            $lineText = implode(' ', $lineWords);
            $wordSpacing = 0.0;
            if ($i !== $lastIndex && count($lineWords) > 1) {
                $textWidth = self::approxTextWidth($lineText, $fontSize);
                $gap = max(0.0, $maxWidth - $textWidth);
                $wordSpacing = min(6.0, $gap / (count($lineWords) - 1));
            }
            $result[] = ['text' => $lineText, 'wordSpacing' => $wordSpacing];
        }

        return $result;
    }

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
        $accent = self::hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
        $logoPath = self::resolveHeaderLogoPath($branding);
        $cnpj = self::formatCnpj((string) ($branding['company_cnpj'] ?? ''));

        $x0 = $margin;
        $x1 = $pageW - $margin;
        $bandBottom = $pageH - $headerH;

        // Cabeçalho "clean": sem bloco de cor sólida — apenas um fio fino no rodapé
        // do cabeçalho e, opcionalmente, um traço discreto na cor de destaque logo
        // abaixo, no lugar da antiga barra colorida de página inteira.
        $pdf->setStrokeColor(self::HAIRLINE[0], self::HAIRLINE[1], self::HAIRLINE[2]);
        $pdf->setLineWidth(0.75);
        $pdf->line($x0, $bandBottom, $x1, $bandBottom);

        if ($accentH > 0) {
            $pdf->setStrokeColor($accent[0], $accent[1], $accent[2]);
            $pdf->setLineWidth(1.5);
            $pdf->line($x0, $bandBottom - 3, $x1, $bandBottom - 3);
        }

        if ($logoPath !== '' && is_file($logoPath)) {
            [$lw, $lh] = self::fitLogoBox($logoPath, $logoMaxW, $logoMaxH);
            if ($lw > 0 && $lh > 0) {
                $ly = $bandBottom + (int) floor(($headerH - $lh) / 2);
                $pdf->imageFromFile($x0, $ly, $lw, $lh, $logoPath);
            }
        }

        if ($cnpj !== '') {
            $fontSize = 9;
            $pdf->setFillColor(self::MUTED[0], self::MUTED[1], self::MUTED[2]);
            $pdf->setFont('F1', $fontSize);
            $w = self::approxTextWidth(self::toPdfEncoding($cnpj), $fontSize);
            $x = (int) floor($x1 - $w);
            if ($x < $x0) {
                $x = $x0;
            }
            $y = $bandBottom + (int) floor(($headerH - $fontSize) / 2) + 1;
            $pdf->text($x, $y, $cnpj);
        }

        return $bandBottom - $gapBelowHeader;
    }

    /**
     * Título de documento no padrão "clean" (rótulo em caixa alta na cor de
     * destaque, título em negrito, fio de destaque e linha(s) de metadados) —
     * substitui o antigo bloco sólido colorido usado como cabeçalho de página.
     */
    public static function documentTitleBlock(
        ProfessionalPdf $pdf,
        int $x0,
        int $x1,
        int $y,
        string $eyebrow,
        string $title,
        array $metaLines,
        array $headingRgb,
        array $accentRgb,
        array $mutedRgb,
        int $titleSize = 20
    ): int {
        if (trim($eyebrow) !== '') {
            $pdf->setFillColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
            $pdf->setFont('F2', 9);
            $pdf->text($x0, $y, mb_strtoupper($eyebrow));
            $y -= 20;
        }

        $pdf->setFillColor($headingRgb[0], $headingRgb[1], $headingRgb[2]);
        $pdf->setFont('F2', $titleSize);
        $pdf->text($x0, $y, $title);
        $y -= 11;

        $pdf->setStrokeColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $pdf->setLineWidth(1.75);
        $pdf->line($x0, $y, $x1, $y);
        $y -= 19;

        $pdf->setFillColor($mutedRgb[0], $mutedRgb[1], $mutedRgb[2]);
        $pdf->setFont('F1', 10);
        foreach ($metaLines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $pdf->text($x0, $y, $line);
            $y -= 14;
        }

        return $y - 6;
    }

    /**
     * Título de seção no padrão "clean": rótulo em negrito seguido de um fio na
     * cor de destaque logo abaixo — substitui o padrão anterior de texto solto
     * com uma linha cinza separando o conteúdo seguinte.
     */
    public static function sectionHeading(
        ProfessionalPdf $pdf,
        int $x0,
        int $x1,
        int $y,
        string $title,
        array $headingRgb,
        array $accentRgb,
        int $fontSize = 13
    ): int {
        $pdf->setFillColor($headingRgb[0], $headingRgb[1], $headingRgb[2]);
        $pdf->setFont('F2', $fontSize);
        $pdf->text($x0, $y, $title);

        $ruleY = $y - 7;
        $pdf->setStrokeColor($accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $pdf->setLineWidth(1.5);
        $pdf->line($x0, $ruleY, $x1, $ruleY);

        return $ruleY - 17;
    }

    /**
     * Linha de cabeçalho de tabela preenchida com a cor primária e texto branco
     * em negrito — substitui os cabeçalhos de tabela em cinza claro com borda.
     *
     * @param array<int,int> $colWidths
     * @param array<int,string> $labels
     * @param array<int,bool>|null $rightAlign
     */
    public static function tableHeaderRow(
        ProfessionalPdf $pdf,
        int $x0,
        int $y,
        int $rowH,
        array $colWidths,
        array $labels,
        array $primaryRgb,
        ?array $rightAlign = null
    ): int {
        $totalW = (int) array_sum($colWidths);
        $pdf->setFillColor($primaryRgb[0], $primaryRgb[1], $primaryRgb[2]);
        $pdf->rect($x0, $y - $rowH, $totalW, $rowH, 'F');

        $pdf->setFillColor(255, 255, 255);
        $pdf->setFont('F2', 10);
        $textY = $y - (int) floor(($rowH + 7) / 2);
        $cx = $x0;
        foreach ($labels as $i => $label) {
            $w = (int) ($colWidths[$i] ?? 0);
            $align = is_array($rightAlign) && (bool) ($rightAlign[$i] ?? false);
            $encoded = self::toPdfEncoding((string) $label);
            if ($align) {
                $tw = self::approxTextWidth($encoded, 10);
                $pdf->text((int) max($cx + 8, $cx + $w - $tw - 8), $textY, (string) $label);
            } else {
                $pdf->text($cx + 8, $textY, (string) $label);
            }
            $cx += $w;
        }

        return $y - $rowH;
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

    private static function resolveHeaderLogoPath(array $branding): string
    {
        $logoPath = trim((string) ($branding['logo_path'] ?? ''));
        $logoLight = trim((string) ($branding['logo_light_path'] ?? ''));
        $logoDark = trim((string) ($branding['logo_dark_path'] ?? ''));

        // O cabeçalho do PDF é sempre fundo claro/branco (sem bloco de cor sólida) —
        // a variante correta é o "logo escuro" (logo_dark_path, pensado em /empresa
        // para uso sobre fundo claro), com fallback para as demais variantes.
        if ($logoDark !== '' && is_file($logoDark)) {
            return $logoDark;
        }
        if ($logoPath !== '' && is_file($logoPath)) {
            return $logoPath;
        }
        if ($logoLight !== '' && is_file($logoLight)) {
            return $logoLight;
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
