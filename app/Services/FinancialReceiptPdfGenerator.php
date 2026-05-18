<?php
declare(strict_types=1);

namespace App\Services;

final class FinancialReceiptPdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';
    private string $companyName = 'TRAXTER';
    private string $logoPath = '';
    private string $logoMime = '';
    private string $renderableLogoPath = '';
    private int $headerLogoW = 0;
    private int $headerLogoH = 0;
    private array $temporaryFiles = [];
    private array $primary = [15, 23, 42];
    private array $accent = [14, 116, 144];
    private array $surface = [248, 250, 252];
    private array $border = [203, 213, 225];
    private array $body = [31, 41, 55];
    private array $muted = [71, 85, 105];
    private int $pageW = 595;
    private int $pageH = 842;
    private int $margin = 42;
    private int $contentX = 48;
    private int $contentW = 499;
    private int $yTop = 778;
    private int $yBottom = 54;
    private array $branding = [];

    public function build(array $branding, array $receivable, array $receipt): string
    {
        $this->branding = $branding;
        $this->companyName = trim((string) ($branding['company_name'] ?? 'TRAXTER')) ?: 'TRAXTER';
        $this->logoPath = trim((string) ($branding['logo_path'] ?? ''));
        $this->logoMime = trim((string) ($branding['logo_mime'] ?? ''));
        $this->primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#0f172a'));
        $this->accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#0ea5a4'));
        $this->prepareHeaderLogo();

        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $y = $this->renderHeader($pdf, $branding);
        $y = $this->renderTitleBlock($pdf, $y, $receivable, $receipt);
        $y = $this->renderClientService($pdf, $y, $receivable);
        $y = $this->renderPaymentHighlights($pdf, $y, $receipt);
        $y = $this->renderDetails($pdf, $y, $receivable, $receipt);
        $y = $this->renderStatement($pdf, $y, $receivable, $receipt);
        $this->renderFooter($pdf, $y, $receipt);

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $this->pageW, $this->contactLine, 20, $this->muted, 10);

        $bytes = $pdf->output();
        $this->cleanupTemporaryFiles();
        return $bytes;
    }

    private function renderHeader(ProfessionalPdf $pdf, array $branding): int
    {
        $effectiveBranding = $branding;
        $effectiveBranding['primary_color'] = (string) ($branding['primary_color'] ?? '#0f172a');
        $effectiveBranding['accent_color'] = (string) ($branding['accent_color'] ?? '#0ea5a4');
        $effectiveBranding['logo_path'] = $this->renderableLogoPath !== '' ? $this->renderableLogoPath : $this->logoPath;

        return PdfStandardTheme::renderHeaderMinimal(
            $pdf,
            $effectiveBranding,
            $this->pageW,
            $this->pageH,
            $this->contentX,
            82,
            6,
            36,
            140,
            34
        );
    }

    private function renderTitleBlock(ProfessionalPdf $pdf, int $y, array $receivable, array $receipt): int
    {
        $yTop = $y;
        $blockH = 56;
        $y = $yTop;

        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->setFont('F2', 16);
        $pdf->text($this->contentX, $y - 18, 'RECIBO DE PAGAMENTO');
        $pdf->setFillColor($this->muted[0], $this->muted[1], $this->muted[2]);
        $pdf->setFont('F1', 10);
        $pdf->text($this->contentX, $y - 34, 'Documento financeiro emitido para comprovacao formal do recebimento.');

        $pdf->setFillColor($this->body[0], $this->body[1], $this->body[2]);
        $pdf->setFont('F1', 10);
        $pdf->text(392, $y - 14, 'Recibo #' . (int) ($receipt['id'] ?? 0));
        $pdf->text(392, $y - 28, 'Conta #' . (int) ($receivable['id'] ?? 0));
        $pdf->text(392, $y - 42, 'Data: ' . $this->formatDate((string) ($receipt['payment_date'] ?? '')));

        return $yTop - $blockH - 10;
    }

    private function renderClientService(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $blockY = $y;
        $blockH = 116;

        $pdf->setFillColor($this->surface[0], $this->surface[1], $this->surface[2]);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->setLineWidth(0.8);
        $pdf->rect($this->contentX, $blockY - $blockH, $this->contentW, $blockH, 'DF');

        $clientName = trim((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? '')));
        $serviceText = $this->serviceDescription($receivable);

        $pdf->setFillColor($this->muted[0], $this->muted[1], $this->muted[2]);
        $pdf->setFont('F2', 10);
        $pdf->text($this->contentX + 18, $blockY - 24, 'NOME DO CLIENTE');
        $pdf->text($this->contentX + 18, $blockY - 72, 'SERVICO QUE GEROU AQUELE RECEBIMENTO');

        $pdf->setFillColor($this->body[0], $this->body[1], $this->body[2]);
        $pdf->setFont('F2', 14);
        foreach ($this->wrap($clientName !== '' ? $clientName : 'Cliente nao informado', 54) as $index => $line) {
            $pdf->text($this->contentX + 18, $blockY - 44 - ($index * 16), $line);
        }

        $pdf->setFont('F1', 11);
        foreach ($this->wrap($serviceText, 86) as $index => $line) {
            $pdf->text($this->contentX + 18, $blockY - 92 - ($index * 13), $line);
        }

        return $blockY - $blockH - 20;
    }

    private function renderPaymentHighlights(ProfessionalPdf $pdf, int $y, array $receipt): int
    {
        $cardW = 156;
        $gap = 15;
        $cards = [
            ['label' => 'Valor recebido', 'value' => $this->brl((float) ($receipt['amount_received'] ?? 0)), 'accent' => true],
            ['label' => 'Juros / multa', 'value' => $this->brl((float) (($receipt['interest_amount'] ?? 0) + ($receipt['fine_amount'] ?? 0))), 'accent' => false],
            ['label' => 'Desconto aplicado', 'value' => $this->brl((float) ($receipt['discount_amount'] ?? 0)), 'accent' => false],
        ];

        foreach ($cards as $index => $card) {
            $x = $this->contentX + ($index * ($cardW + $gap));
            $pdf->setFillColor(255, 255, 255);
            $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
            $pdf->rect($x, $y - 76, $cardW, 76, 'DF');

            $pdf->setFillColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $pdf->setFont('F1', 10);
            $pdf->text($x + 14, $y - 24, $card['label']);

            $valueColor = $card['accent'] ? $this->accent : $this->primary;
            $pdf->setFillColor($valueColor[0], $valueColor[1], $valueColor[2]);
            $pdf->setFont('F2', 16);
            $pdf->text($x + 14, $y - 48, $card['value']);
        }

        return $y - 100;
    }

    private function renderDetails(ProfessionalPdf $pdf, int $y, array $receivable, array $receipt): int
    {
        $left = [
            ['Documento', (string) (($receivable['invoice_number'] ?? '') !== '' ? $receivable['invoice_number'] : ('Conta #' . (int) ($receivable['id'] ?? 0)))],
            ['Metodo', (string) ($receipt['payment_method'] ?? 'Nao informado')],
            ['Referencia transacao', (string) ($receipt['transaction_reference'] ?? '—')],
            ['Banco', trim((string) (($receivable['bank_name'] ?? '') . ' ' . ($receivable['account_name'] ?? ''))) ?: '—'],
        ];
        $right = [
            ['Data do pagamento', $this->formatDate((string) ($receipt['payment_date'] ?? ''))],
            ['Projeto', (string) ($receivable['project_title'] ?? '—')],
            ['Competencia', $this->formatDate((string) ($receivable['competence_date'] ?? ''))],
            ['Referencia interna', (string) ($receivable['external_reference'] ?? '—')],
        ];

        $boxH = 132;
        $pdf->setFillColor(255, 255, 255);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->rect($this->contentX, $y - $boxH, $this->contentW, $boxH, 'DF');

        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->setFont('F2', 12);
        $pdf->text($this->contentX + 18, $y - 24, 'Detalhes do pagamento');

        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->line($this->contentX + (int) floor($this->contentW / 2), $y - 104, $this->contentX + (int) floor($this->contentW / 2), $y - 34);

        $this->drawKeyValues($pdf, $this->contentX + 18, $y - 46, $left);
        $this->drawKeyValues($pdf, $this->contentX + 268, $y - 46, $right);

        return $y - $boxH - 18;
    }

    private function renderStatement(ProfessionalPdf $pdf, int $y, array $receivable, array $receipt): int
    {
        $netAmount = (float) (($receipt['amount_received'] ?? 0) + ($receipt['interest_amount'] ?? 0) + ($receipt['fine_amount'] ?? 0) - ($receipt['discount_amount'] ?? 0));
        $statement = 'Recebemos de ' . $this->safeInlineName((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? 'cliente nao informado'))) .
            ' a importancia de ' . $this->brl($netAmount) .
            ', referente ao servico "' . $this->serviceDescription($receivable) . '".';
        $observation = trim((string) ($receipt['observation'] ?? ''));

        $statementLines = $this->wrap($statement, 92);
        $observationLines = $observation !== '' ? $this->wrap('Observacoes: ' . $observation, 92) : [];
        $linesCount = count($statementLines) + max(1, count($observationLines));
        $boxH = 72 + ($linesCount * 14);

        if (($y - $boxH) < $this->yBottom + 110) {
            $pdf->addPage();
            $y = $this->renderHeader($pdf, $this->branding);
        }

        $pdf->setFillColor(255, 255, 255);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->rect($this->contentX, $y - $boxH, $this->contentW, $boxH, 'DF');

        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->setFont('F2', 12);
        $pdf->text($this->contentX + 18, $y - 24, 'Declaracao de recebimento');

        $pdf->setFillColor($this->body[0], $this->body[1], $this->body[2]);
        $pdf->setFont('F1', 11);
        $lineY = $y - 46;
        foreach ($statementLines as $line) {
            $pdf->text($this->contentX + 18, $lineY, $line);
            $lineY -= 14;
        }

        if (count($observationLines) > 0) {
            $lineY -= 4;
            foreach ($observationLines as $line) {
                $pdf->text($this->contentX + 18, $lineY, $line);
                $lineY -= 14;
            }
        }

        return $y - $boxH - 18;
    }

    private function renderFooter(ProfessionalPdf $pdf, int $y, array $receipt): void
    {
        $footerY = max($this->yBottom + 36, $y);
        if ($footerY < 118) {
            $footerY = 118;
        }

        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->line($this->contentX, $footerY, $this->contentX + 220, $footerY);
        $pdf->line($this->contentX + 279, $footerY, $this->contentX + 499, $footerY);

        $pdf->setFillColor($this->muted[0], $this->muted[1], $this->muted[2]);
        $pdf->setFont('F1', 10);
        $pdf->text($this->contentX + 36, $footerY - 16, 'Assinatura do cliente');
        $pdf->text($this->contentX + 337, $footerY - 16, $this->companyName);

        $pdf->setFillColor($this->body[0], $this->body[1], $this->body[2]);
    }

    private function drawKeyValues(ProfessionalPdf $pdf, int $x, int $y, array $pairs): void
    {
        foreach ($pairs as [$label, $value]) {
            $pdf->setFillColor($this->muted[0], $this->muted[1], $this->muted[2]);
            $pdf->setFont('F1', 9);
            $pdf->text($x, $y, (string) $label);

            $pdf->setFillColor($this->body[0], $this->body[1], $this->body[2]);
            $pdf->setFont('F2', 10);
            foreach ($this->wrap((string) $value, 28) as $index => $line) {
                $pdf->text($x, $y - 14 - ($index * 12), $line);
            }

            $y -= 34;
        }
    }

    private function serviceDescription(array $receivable): string
    {
        $parts = [];
        $project = trim((string) ($receivable['project_title'] ?? ''));
        if ($project !== '') {
            $parts[] = 'Projeto: ' . $project;
        }

        $description = trim((string) ($receivable['description'] ?? ''));
        if ($description !== '') {
            $parts[] = $description;
        }

        if (count($parts) === 0) {
            $title = trim((string) ($receivable['title'] ?? 'Recebimento financeiro'));
            return $title !== '' ? $title : 'Recebimento financeiro sem descricao detalhada.';
        }

        return implode(' | ', $parts);
    }

    private function safeInlineName(string $name): string
    {
        $name = trim($name);
        return $name !== '' ? $name : 'cliente nao informado';
    }

    private function formatDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return 'Nao informada';
        }

        $time = strtotime($date);
        if ($time === false) {
            return $date;
        }

        return date('d/m/Y', $time);
    }

    private function wrap(string $text, int $maxLen): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return ['—'];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : ($line . ' ' . $word);
            if (mb_strlen($candidate) > $maxLen && $line !== '') {
                $lines[] = $line;
                $line = $word;
                continue;
            }
            $line = $candidate;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines === [] ? ['—'] : $lines;
    }

    private function brl(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [15, 23, 42];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function prepareHeaderLogo(): void
    {
        $this->renderableLogoPath = '';
        $this->headerLogoW = 0;
        $this->headerLogoH = 0;

        if ($this->logoPath === '' || !is_file($this->logoPath)) {
            return;
        }

        $path = $this->resolveRenderableLogoPath($this->logoPath, $this->logoMime);
        if ($path === '' || !is_file($path)) {
            return;
        }

        [$w, $h] = $this->fitLogoBox($path, 140, 34);
        if ($w <= 0 || $h <= 0) {
            return;
        }

        $this->renderableLogoPath = $path;
        $this->headerLogoW = $w;
        $this->headerLogoH = $h;
    }

    private function renderHeaderLogo(ProfessionalPdf $pdf): void
    {
        if ($this->renderableLogoPath === '' || $this->headerLogoW <= 0 || $this->headerLogoH <= 0) {
            return;
        }

        $x = $this->contentX;
        $maxBottom = 804;
        $y = $maxBottom - (int) floor((34 - $this->headerLogoH) / 2);
        $pdf->imageFromFile($x, $y, $this->headerLogoW, $this->headerLogoH, $this->renderableLogoPath);
    }

    private function resolveRenderableLogoPath(string $path, string $mime): string
    {
        $mime = $this->detectMime($path, $mime);
        if ($mime === 'image/svg+xml') {
            return $this->rasterizeSvgIfPossible($path);
        }

        return $this->isRasterMime($mime) ? $path : '';
    }

    private function detectMime(string $path, string $mime): string
    {
        $mime = trim($mime);
        if ($mime !== '') {
            return $mime;
        }

        $detected = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
        if ($detected !== '') {
            return $detected;
        }

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => '',
        };
    }

    private function isRasterMime(string $mime): bool
    {
        return in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true);
    }

    private function rasterizeSvgIfPossible(string $path): string
    {
        if (!class_exists(\Imagick::class)) {
            return '';
        }

        try {
            $imagick = new \Imagick();
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            $imagick->readImage($path);
            $imagick->setImageFormat('png32');

            $tmp = tempnam(sys_get_temp_dir(), 'traxter_receipt_logo_');
            if (!is_string($tmp) || $tmp === '') {
                $imagick->clear();
                $imagick->destroy();
                return '';
            }

            $target = $tmp . '.png';
            @unlink($tmp);
            if (!$imagick->writeImage($target) || !is_file($target)) {
                $imagick->clear();
                $imagick->destroy();
                return '';
            }

            $imagick->clear();
            $imagick->destroy();
            $this->temporaryFiles[] = $target;
            return $target;
        } catch (\Throwable) {
            return '';
        }
    }

    private function fitLogoBox(string $path, int $maxW, int $maxH): array
    {
        if (!function_exists('getimagesize')) {
            return [0, 0];
        }

        $info = @getimagesize($path);
        if ($info === false || !isset($info[0], $info[1])) {
            return [0, 0];
        }

        return $this->fitBox((int) $info[0], (int) $info[1], $maxW, $maxH);
    }

    private function fitBox(int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        if ($srcW <= 0 || $srcH <= 0 || $maxW <= 0 || $maxH <= 0) {
            return [0, 0];
        }

        $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
        return [
            max(1, (int) floor($srcW * $scale)),
            max(1, (int) floor($srcH * $scale)),
        ];
    }

    private function cleanupTemporaryFiles(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_string($file) && $file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
        $this->temporaryFiles = [];
    }
}
