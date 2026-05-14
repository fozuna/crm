<?php
declare(strict_types=1);

namespace App\Services;

final class ContractPdfGenerator
{
    private int $pageW = 595;
    private int $pageH = 842;
    private int $margin = 56;

    public function build(array $branding, array $contract, string $body, string $footer): string
    {
        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
        $company = trim((string) ($branding['company_name'] ?? 'TRAXTER'));
        $contractTitle = trim((string) ($contract['title'] ?? 'Contrato de Prestacao de Servicos'));

        $pdf->setFillColor($primary[0], $primary[1], $primary[2]);
        $pdf->rect(0, 786, $this->pageW, 56, 'F');
        $pdf->setFillColor($accent[0], $accent[1], $accent[2]);
        $pdf->rect(0, 780, $this->pageW, 6, 'F');

        $pdf->setFillColor(255, 255, 255);
        $pdf->setFont('F2', 16);
        $pdf->text($this->margin, 812, $contractTitle);
        $pdf->setFont('F1', 10);
        $pdf->text($this->margin, 794, $company !== '' ? $company : 'TRAXTER');
        $pdf->text(380, 812, 'Contrato ' . (string) ($contract['contract_number'] ?? ''));
        $pdf->text(380, 794, 'Versao ' . (int) ($contract['current_version'] ?? 1));

        $y = 750;
        $y = $this->paragraph($pdf, $y, $body, 90, 12);
        if (trim($footer) !== '') {
            $y -= 12;
            $pdf->setFont('F2', 11);
            $pdf->setFillColor(17, 24, 39);
            $pdf->text($this->margin, $y, 'Formalizacao');
            $y -= 18;
            $y = $this->paragraph($pdf, $y, $footer, 90, 12);
        }

        $pdf->setStrokeColor(203, 213, 225);
        $pdf->line($this->margin, 92, $this->pageW - $this->margin, 92);
        $pdf->setFont('F1', 9);
        $pdf->setFillColor(71, 85, 105);
        $pdf->text($this->margin, 76, 'Gerado automaticamente a partir da proposta aprovada vinculada ao CRM TRAXTER.');

        return $pdf->output();
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
                    $y = 760;
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
