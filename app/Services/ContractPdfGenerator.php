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
    private array $accent = [238, 108, 77];
    private array $surface = [248, 250, 252];
    private array $border = [203, 213, 225];

    public function build(array $branding, array $contract, string $body, string $footer): string
    {
        $this->branding = $branding;
        $this->primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $this->accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
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

        $x1 = $this->pageW - $this->margin;
        $y = $this->yTop;
        $meta = [];
        $meta[] = 'Contrato ' . (string) ($contract['contract_number'] ?? '');
        $meta[] = 'Versão ' . (int) ($contract['current_version'] ?? 1);
        if ($company !== '') {
            $meta[] = $company;
        }
        $y = PdfStandardTheme::documentTitleBlock(
            $pdf,
            $this->margin,
            $x1,
            $y,
            'Documento contratual',
            $contractTitle,
            [implode(' · ', array_filter($meta, static fn ($v) => trim((string) $v) !== ''))],
            PdfStandardTheme::INK,
            $this->accent,
            PdfStandardTheme::MUTED
        );
        $y = $this->paragraph($pdf, $y, $body, 12);
        if (trim($footer) !== '') {
            $y -= 6;
            $y = PdfStandardTheme::sectionHeading($pdf, $this->margin, $x1, $y, 'Formalização', PdfStandardTheme::INK, $this->accent);
            $y = $this->paragraph($pdf, $y, $footer, 12);
        }

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $this->pageW, $this->contactLine, 20, [71, 85, 105], 10);

        return $pdf->output();
    }

    private function paragraph(ProfessionalPdf $pdf, int $y, string $text, int $lineHeight): int
    {
        $fontSize = 10;
        $contentW = $this->pageW - (2 * $this->margin);
        $pdf->setFont('F1', $fontSize);
        $pdf->setFillColor(PdfStandardTheme::BODY[0], PdfStandardTheme::BODY[1], PdfStandardTheme::BODY[2]);
        $paragraphs = preg_split("/\R{2,}/", trim($text)) ?: [];
        foreach ($paragraphs as $paragraph) {
            // Quebras de linha simples dentro de um parágrafo são tratadas como
            // intencionais (ex.: cláusulas em linhas separadas) e preservadas;
            // cada trecho é justificado pela largura real, exceto a última
            // linha, que fica alinhada à esquerda — convenção tipográfica.
            $chunks = preg_split("/\R/", trim((string) $paragraph)) ?: [];
            foreach ($chunks as $chunk) {
                $chunk = trim((string) $chunk);
                if ($chunk === '') {
                    continue;
                }

                $wrapped = PdfStandardTheme::wrapJustified($chunk, $fontSize, $contentW);
                foreach ($wrapped as $line) {
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
                        $pdf->setFont('F1', $fontSize);
                        $pdf->setFillColor(PdfStandardTheme::BODY[0], PdfStandardTheme::BODY[1], PdfStandardTheme::BODY[2]);
                    }
                    $pdf->text($this->margin, $y, $line['text'], $line['wordSpacing'] > 0.0 ? $line['wordSpacing'] : null);
                    $y -= $lineHeight;
                }
            }
            $y -= 8;
        }

        return $y;
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
