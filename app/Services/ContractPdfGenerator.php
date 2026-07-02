<?php
declare(strict_types=1);

namespace App\Services;

final class ContractPdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';
    private int $pageW = 595;
    private int $pageH = 842;
    private int $margin = 56;
    private int $yTop = 750;
    private array $branding = [];
    private array $primary = [41, 50, 65];
    private array $surface = [248, 250, 252];
    private array $border = [203, 213, 225];

    public function build(array $branding, array $contract, string $body, string $footer): string
    {
        $this->branding = $branding;
        $this->primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $contractTitle = trim((string) ($contract['title'] ?? 'Contrato de Prestação de Serviços'));
        $company = trim((string) ($branding['company_name'] ?? 'TRAXTER'));

        $this->yTop = PdfStandardTheme::renderHeaderMinimal(
            $pdf,
            $this->branding,
            $this->pageW,
            $this->pageH,
            $this->margin,
            56,
            6,
            36,
            200,
            32
        );

        $y = $this->yTop;
        $meta = [];
        $meta[] = 'Contrato ' . (string) ($contract['contract_number'] ?? '');
        $meta[] = 'Versão ' . (int) ($contract['current_version'] ?? 1);
        if ($company !== '') {
            $meta[] = $company;
        }
        $y = $this->heroCard($pdf, $y, $contractTitle, implode(' • ', array_filter($meta, static fn ($v) => trim((string) $v) !== '')));
        $y = $this->paragraph($pdf, $y, $body, 90, 12);
        if (trim($footer) !== '') {
            $y -= 12;
            $pdf->setFont('F2', 11);
            $pdf->setFillColor(17, 24, 39);
            $pdf->text($this->margin, $y, 'Formalização');
            $y -= 18;
            $y = $this->paragraph($pdf, $y, $footer, 90, 12);
        }

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $this->pageW, $this->contactLine, 20, [71, 85, 105], 10);

        return $pdf->output();
    }

    private function heroCard(ProfessionalPdf $pdf, int $y, string $title, string $meta): int
    {
        $boxH = 96;
        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->rect($this->margin, $y - $boxH, $this->pageW - ($this->margin * 2), $boxH, 'F');
        $pdf->setFillColor(255, 255, 255);
        $pdf->setFont('F2', 18);
        $pdf->text($this->margin + 18, $y - 28, $title);
        $pdf->setFont('F1', 11);
        foreach ($this->wrap($meta !== '' ? $meta : 'Documento contratual padronizado.', 60) as $index => $line) {
            if ($index > 2) {
                break;
            }
            $pdf->text($this->margin + 18, $y - 48 - ($index * 13), $line);
        }

        return $y - $boxH - 18;
    }

    private function paragraph(ProfessionalPdf $pdf, int $y, string $text, int $maxChars, int $lineHeight): int
    {
        $pdf->setFont('F1', 10);
        $pdf->setFillColor(30, 41, 59);
        $paragraphs = preg_split("/\R{2,}/", trim($text)) ?: [];
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $lines = $this->wrap($paragraph, $maxChars);
            foreach ($lines as $line) {
                if ($y < 110) {
                    $pdf->addPage();
                    $y = PdfStandardTheme::renderHeaderMinimal(
                        $pdf,
                        $this->branding,
                        $this->pageW,
                        $this->pageH,
                        $this->margin,
                        56,
                        6,
                        36,
                        200,
                        32
                    );
                    $pdf->setFont('F1', 10);
                    $pdf->setFillColor(30, 41, 59);
                }
                $pdf->text($this->margin, $y, $line);
                $y -= $lineHeight;
            }
            $y -= 8;
        }

        return $y;
    }

    private function wrap(string $text, int $maxChars): array
    {
        $lines = [];
        $parts = preg_split("/\R/", $text) ?: [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                $lines[] = '';
                continue;
            }
            $words = preg_split('/\s+/', $part) ?: [];
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if (mb_strlen($candidate) <= $maxChars) {
                    $current = $candidate;
                    continue;
                }
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $word;
            }
            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [41, 50, 65];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
