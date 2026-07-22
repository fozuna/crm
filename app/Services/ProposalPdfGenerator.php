<?php
declare(strict_types=1);

namespace App\Services;

final class ProposalPdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';
    private string $company = 'TRAXTER.';
    private string $logoPath = '';
    private array $branding = [];
    private array $primary = [41, 50, 65];
    private array $accent = [238, 108, 77];
    private string $companyCnpj = '';

    private array $textBody = [26, 26, 26];
    private array $textHeading = [17, 24, 39];
    private array $divider = [107, 114, 128];
    private array $surface = [248, 250, 252];
    private array $border = [203, 213, 225];
    private float $fontScale = 1.0;

    private int $footerLogoW = 0;
    private int $footerLogoH = 0;

    private int $pageW = 595;
    private int $pageH = 842;
    private int $margin;
    private int $x0;
    private int $x1;
    private int $contentW;
    private int $yTop;
    private int $yBottom;
    private int $headerH = 56;
    private int $footerReserve = 112;

    public function build(array $branding, array $proposal, array $items, array $milestones, array $paymentOptions, int $selectedIndex): string
    {
        $this->branding = $branding;
        $this->margin = 64;
        $this->x0 = $this->margin;
        $this->x1 = $this->pageW - $this->margin;
        $this->contentW = $this->x1 - $this->x0;
        $this->yBottom = 56;

        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $this->primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $this->accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
        $this->company = (string) ($branding['company_name'] ?? 'TRAXTER');
        $this->logoPath = (string) ($branding['logo_path'] ?? '');
        $this->fontScale = 1.0;
        $this->companyCnpj = (string) ($branding['company_cnpj'] ?? '');

        $this->renderHeader($pdf);

        $issueDate = $this->issueDate($proposal);
        $y = $this->yTop;

        $y = $this->heroCard($pdf, $y, $proposal, $issueDate);
        $y = $this->initialDataBox($pdf, $y, (int) ($proposal['id'] ?? 0), (string) ($proposal['client_name'] ?? ''), $issueDate);
        $y = $this->fieldBlock($pdf, $y, 'Descrição do projeto', (string) ($proposal['description'] ?? ''));
        $y = $this->itemsTable($pdf, $y, $items);
        $y = $this->financialSummary($pdf, $y, $proposal, $paymentOptions, $selectedIndex);
        $y = $this->deliveryBlock($pdf, $y, $proposal, $milestones);
        $y = $this->termsBlock($pdf, $y, (string) ($proposal['terms'] ?? ''), (string) ($proposal['notes'] ?? ''));

        $this->footerBlock($pdf, $y, $issueDate);

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $this->pageW, $this->contactLine, 20, [71, 85, 105], 10);

        return $pdf->output();
    }

    private function issueDate(array $proposal): string
    {
        $createdAt = (string) ($proposal['created_at'] ?? '');
        $ts = $createdAt !== '' ? strtotime($createdAt) : false;
        if ($ts === false) {
            $ts = time();
        }
        return date('d/m/Y', $ts);
    }

    private function renderHeader(ProfessionalPdf $pdf): void
    {
        $gap = (int) floor(($this->margin + 14) / 2);
        $this->yTop = PdfStandardTheme::renderHeaderMinimal(
            $pdf,
            [
                'primary_color' => (string) ($this->rgbToHex($this->primary)),
                'accent_color' => (string) ($this->rgbToHex($this->accent)),
                'logo_path' => $this->logoPath,
                'logo_light_path' => (string) ($this->branding['logo_light_path'] ?? ''),
                'logo_dark_path' => (string) ($this->branding['logo_dark_path'] ?? ''),
                'company_cnpj' => $this->companyCnpj,
            ],
            $this->pageW,
            $this->pageH,
            $this->margin,
            $this->headerH,
            4,
            $gap,
            200,
            32
        );
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 10);
    }

    private function computeFooterLogo(): void
    {
        $this->footerLogoW = 0;
        $this->footerLogoH = 0;
        if ($this->logoPath === '' || !is_file($this->logoPath)) {
            return;
        }
        [$w, $h] = $this->fitLogoBox($this->logoPath, 200, 32);
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $this->footerLogoW = max(1, (int) floor($w / 3));
        $this->footerLogoH = max(1, (int) floor($h / 3));
    }

    private function renderFooterLogo(ProfessionalPdf $pdf, string $logoPath): void
    {
        if ($this->footerLogoW <= 0 || $this->footerLogoH <= 0) {
            return;
        }
        $x = $this->x0;
        $y = $this->yBottom + 6;
        $pdf->imageFromFile($x, $y, $this->footerLogoW, $this->footerLogoH, $logoPath);
    }

    private function heroCard(ProfessionalPdf $pdf, int $y, array $proposal, string $issueDate): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(120));

        return PdfStandardTheme::documentTitleBlock(
            $pdf,
            $this->x0,
            $this->x1,
            $y,
            'Documento comercial',
            'Proposta Comercial',
            ['Proposta #' . (int) ($proposal['id'] ?? 0) . ' · Emitida em ' . $issueDate],
            $this->textHeading,
            $this->accent,
            PdfStandardTheme::MUTED
        );
    }

    private function initialDataBox(ProfessionalPdf $pdf, int $y, int $proposalId, string $clientName, string $issueDate): int
    {
        $labelW = 72;
        $clientLines = $this->wrapLine($clientName, 42);
        $clientLines = array_values(array_filter($clientLines, static fn($l) => $l !== ''));
        if (count($clientLines) === 0) {
            $clientLines = ['Não informado'];
        }

        $lineH = $this->sp(16);
        $gap = $this->sp(6);
        $pad = $this->sp(14);
        $totalLines = 1 + count($clientLines) + 1;
        $boxH = ($pad * 2) + ($totalLines * $lineH) + (2 * $gap) + 18;

        $y = $this->ensureBlock($pdf, $y, $this->sp($boxH + 24));
        $yTop = $y;
        $yBoxBottom = $yTop - $boxH;

        $pdf->setFillColor($this->surface[0], $this->surface[1], $this->surface[2]);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->setLineWidth(0.75);
        $pdf->rect($this->x0, $yBoxBottom, $this->contentW, $boxH, 'DF');

        $pdf->setFillColor($this->textHeading[0], $this->textHeading[1], $this->textHeading[2]);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0 + 16, $yTop - 18, 'Identificação');
        $pdf->setStrokeColor($this->accent[0], $this->accent[1], $this->accent[2]);
        $pdf->setLineWidth(1.25);
        $pdf->line($this->x0 + 16, $yTop - 24, $this->x1 - 16, $yTop - 24);

        $x = $this->x0 + 16;
        $xVal = $x + $labelW;
        $curY = $yTop - 44;

        $curY = $this->kvLine($pdf, $x, $xVal, $curY, 'Proposta:', '#' . $proposalId);
        $curY -= $gap;

        $this->applyHeadingText($pdf);
        $this->setFontScaledMin($pdf, 'F2', 12, 12);
        $pdf->text($x, $curY, 'Cliente:');

        $this->applyBodyText($pdf);
        $this->setFontScaledMin($pdf, 'F1', 11, 11);
        $pdf->text($xVal, $curY, $clientLines[0]);
        $curY -= $lineH;
        for ($i = 1; $i < count($clientLines); $i++) {
            $pdf->text($xVal, $curY, $clientLines[$i]);
            $curY -= $lineH;
        }
        $curY -= $gap;

        $this->kvLine($pdf, $x, $xVal, $curY, 'Data:', $issueDate);

        return $yBoxBottom - $this->sp(14);
    }

    private function fieldBlock(ProfessionalPdf $pdf, int $y, string $title, string $body): int
    {
        $segments = $this->buildTextSegments($body);
        $y = $this->ensureBlock($pdf, $y, $this->sp(92));

        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, $title, $this->textHeading, $this->accent);

        $y = $this->renderTextSegments($pdf, $y, $segments, 74, 16, 8);
        return $y - $this->sp(6);
    }

    private function block(ProfessionalPdf $pdf, int $y, string $title, string $body): int
    {
        return $this->fieldBlock($pdf, $y, $title, $body);
    }

    private function itemsTable(ProfessionalPdf $pdf, int $y, array $items): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(120));
        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Serviços', $this->textHeading, $this->accent);

        $descW = 258;
        $qtyW = 44;
        $unitW = 72;
        $totalW = $this->contentW - $descW - $qtyW - $unitW;
        $colWidths = [$descW, $qtyW, $unitW, $totalW];
        $colLabels = ['Descrição', 'Qtd', 'Valor', 'Total'];
        $colRightAlign = [false, false, true, true];

        $drawHeader = function (int $headerTop) use ($pdf, $colWidths, $colLabels, $colRightAlign): int {
            return PdfStandardTheme::tableHeaderRow($pdf, $this->x0, $headerTop, 24, $colWidths, $colLabels, $this->primary, $colRightAlign);
        };

        $currentY = $drawHeader($y);
        if (count($items) === 0) {
            $items = [[
                'description' => 'Nenhum item informado.',
                'qty' => '',
                'unit_price' => '',
                'total' => '',
                'is_bonus' => 0,
            ]];
        }

        $rowIndex = 0;
        foreach ($items as $it) {
            $desc = (string) ($it['description'] ?? '');
            if ((int) ($it['is_bonus'] ?? 0) === 1) {
                $desc = '[BÔNUS] ' . $desc;
            }
            $lines = $this->wrapLine($desc !== '' ? $desc : '—', 42);
            $qty = trim((string) ($it['qty'] ?? ''));
            $unit = $it['unit_price'] === '' ? '' : $this->brl((float) ($it['unit_price'] ?? 0));
            $tot = $it['total'] === '' ? '' : $this->brl((float) ($it['total'] ?? 0));
            $rowH = 14 + (count($lines) * 14);
            if (($currentY - $rowH) < ($this->yBottom + $this->footerReserve + 20)) {
                $pdf->addPage();
                $this->renderHeader($pdf);
                $currentY = $this->yTop;
                $currentY = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $currentY, 'Serviços (continuação)', $this->textHeading, $this->accent);
                $currentY = $drawHeader($currentY);
            }

            $fill = ($rowIndex % 2 === 0) ? [255, 255, 255] : $this->surface;
            $pdf->setFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
            $pdf->rect($this->x0, $currentY - $rowH, $this->contentW, $rowH, 'DF');
            $this->applyBodyText($pdf);
            $this->setFontScaled($pdf, 'F1', 10);
            $lineY = $currentY - 17;
            $pdf->text($this->x0 + 8, $lineY, $lines[0] ?? '—');
            if ($qty !== '') {
                $this->rightAlignedText($pdf, $this->x0 + $descW + $qtyW - 8, $lineY, $qty, 'F1', 10);
            }
            if ($unit !== '') {
                $this->rightAlignedText($pdf, $this->x0 + $descW + $qtyW + $unitW - 8, $lineY, $unit, 'F1', 10);
            }
            if ($tot !== '') {
                $this->rightAlignedText($pdf, $this->x1 - 8, $lineY, $tot, 'F1', 10);
            }
            for ($i = 1; $i < count($lines); $i++) {
                $lineY -= $this->sp(14);
                $pdf->text($this->x0 + 8, $lineY, $lines[$i]);
            }
            $currentY -= $rowH;
            $rowIndex++;
        }

        return $currentY - $this->sp(14);
    }

    private function financialSummary(ProfessionalPdf $pdf, int $y, array $proposal, array $paymentOptions, int $selectedIndex): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(120));
        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Resumo financeiro', $this->textHeading, $this->accent);
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 10);

        $subtotal = (float) ($proposal['subtotal'] ?? 0);
        $discountP = (float) ($proposal['discount_percent'] ?? 0);
        $discountA = (float) ($proposal['discount_amount'] ?? 0);
        $total = (float) ($proposal['total'] ?? 0);

        $pdf->text($this->x0, $y, 'Subtotal: ' . $this->brl($subtotal));
        $y -= $this->sp(14);
        $pdf->text($this->x0, $y, 'Desconto (' . number_format($discountP, 2, ',', '.') . '%): ' . $this->brl($discountA));
        $y -= $this->sp(14);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, 'Total: ' . $this->brl($total));
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 10);
        $y -= $this->sp(14);

        if (!is_array($paymentOptions) || count($paymentOptions) === 0) {
            $paymentOptions = [[
                'label' => (string) (($proposal['payment_snapshot'] ?? '') !== '' ? 'Opção principal' : 'Pagamento'),
                'total' => $total,
                'snapshot' => [],
            ]];
        }

        $selectedIndex = max(0, min((int) $selectedIndex, count($paymentOptions) - 1));

        foreach ($paymentOptions as $idx => $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $label = (string) ($opt['label'] ?? ('Opção ' . ($idx + 1)));
            $optTotal = isset($opt['total']) ? (float) $opt['total'] : $total;
            $tag = ($idx === $selectedIndex) ? ' (principal)' : '';

            $y = $this->ensureBlock($pdf, $y, $this->sp(92));
            $this->applyHeadingText($pdf);
            $this->setFontScaled($pdf, 'F2', 10);
            $pdf->text($this->x0, $y, 'Forma de pagamento ' . ($idx + 1) . $tag);
            $y -= $this->sp(14);
            $this->applyBodyText($pdf);
            $this->setFontScaled($pdf, 'F1', 10);
            $pdf->text($this->x0, $y, $label);
            $y -= $this->sp(14);
            $pdf->text($this->x0, $y, 'Valor: ' . $this->brl($optTotal));
            $y -= $this->sp(14);

            $snap = $opt['snapshot'] ?? [];
            $schedule = is_array($snap['schedule'] ?? null) ? $snap['schedule'] : [];
            foreach ($schedule as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $due = (string) ($row['due_date'] ?? '');
                $due = $due !== '' ? date('d/m/Y', strtotime($due)) : '';
                $kind = (string) ($row['kind'] ?? 'parcela');
                $no = (int) ($row['no'] ?? 0);
                $pLabel = $kind === 'entrada' ? 'Entrada' : ($kind === 'avista' ? 'À vista' : ('Parcela ' . $no));
                $amount = (float) ($row['amount'] ?? 0);
                $y = $this->ensureBlock($pdf, $y, $this->sp(56));
                $pdf->text($this->x0 + 12, $y, $pLabel . ' (' . $due . '): ' . $this->brl($amount));
                $y -= $this->sp(14);
            }

            $special = trim((string) ($snap['special_terms'] ?? ''));
            if ($special !== '') {
                $y = $this->block($pdf, $y - $this->sp(2), 'Condições especiais (opção ' . ($idx + 1) . ')', $special);
            }
            $y -= $this->sp(4);
        }

        return $y - 10;
    }

    private function deliveryBlock(ProfessionalPdf $pdf, int $y, array $proposal, array $milestones): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(110));
        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Prazos de entrega', $this->textHeading, $this->accent);
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 10);

        $start = (string) ($proposal['delivery_start'] ?? '');
        $end = (string) ($proposal['delivery_end'] ?? '');
        $startTxt = $start !== '' ? date('d/m/Y', strtotime($start)) : 'Não informado';
        $endTxt = $end !== '' ? date('d/m/Y', strtotime($end)) : 'Não informado';
        $pdf->text($this->x0, $y, 'Início estimado: ' . $startTxt);
        $y -= $this->sp(14);
        $pdf->text($this->x0, $y, 'Término estimado: ' . $endTxt);
        $y -= $this->sp(14);

        foreach ($milestones as $m) {
            $title = (string) ($m['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $due = (string) ($m['due_date'] ?? '');
            $dueTxt = $due !== '' ? date('d/m/Y', strtotime($due)) : 'Não informado';
            $y = $this->ensureBlock($pdf, $y, $this->sp(56));
            $pdf->text($this->x0 + 12, $y, 'Marco: ' . $title . ' (' . $dueTxt . ')');
            $y -= $this->sp(14);
        }

        $penalty = trim((string) ($proposal['penalty_terms'] ?? ''));
        if ($penalty !== '') {
            $y = $this->block($pdf, $y - $this->sp(2), 'Penalidades por atraso', $penalty);
        }

        return $y - 10;
    }

    private function termsBlock(ProfessionalPdf $pdf, int $y, string $terms, string $notes): int
    {
        $terms = trim($terms);
        $notes = trim($notes);
        if ($terms === '' && $notes === '') {
            return $y;
        }

        $y = $this->ensureBlock($pdf, $y, $this->sp(160));
        if ($terms !== '') {
            $y = $this->block($pdf, $y, 'Termos e condições', $terms);
        }
        if ($notes !== '') {
            $y = $this->block($pdf, $y, 'Observações', $notes);
        }
        return $y;
    }

    private function footerBlock(ProfessionalPdf $pdf, int $y, string $issueDate): void
    {
        $minY = $this->yBottom + $this->footerReserve;
        if ($y < $minY) {
            $pdf->addPage();
            $this->renderHeader($pdf);
        }

        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 10);
        $pdf->text($this->x0, $this->yBottom + $this->sp(92), 'Campo Grande, MS - ' . $issueDate);
        $pdf->text($this->x0, $this->yBottom + $this->sp(78), 'Proposta válida por 30 dias.');

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $half = (int) floor($this->contentW / 2);
        $leftStart = $this->x0;
        $leftEnd = $this->x0 + $half - 10;
        $rightStart = $this->x0 + $half + 10;
        $rightEnd = $this->x1;

        $inset = 12;
        $lineY = $this->yBottom + $this->sp(52);
        $pdf->line($leftStart + $inset, $lineY, $leftEnd - $inset, $lineY);
        $pdf->line($rightStart + $inset, $lineY, $rightEnd - $inset, $lineY);

        $labelY = $lineY - $this->sp(14);
        $leftCx = (int) floor(($leftStart + $leftEnd) / 2);
        $rightCx = (int) floor(($rightStart + $rightEnd) / 2);

        $this->applyBodyText($pdf);
        $this->centerText($pdf, $leftCx, $labelY, 'Assinatura do cliente', 'F1', 10);

        $this->centerText($pdf, $rightCx, $labelY, 'TRAXTER. Automações e Sistemas', 'F1', 10);
        $this->centerText($pdf, $rightCx, $labelY - $this->sp(11), '30.358.115/0001-13', 'F1', 10);
    }

    private function rgbToHex(array $rgb): string
    {
        $r = (int) ($rgb[0] ?? 0);
        $g = (int) ($rgb[1] ?? 0);
        $b = (int) ($rgb[2] ?? 0);
        $r = max(0, min(255, $r));
        $g = max(0, min(255, $g));
        $b = max(0, min(255, $b));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    private function ensureSpace(ProfessionalPdf $pdf, int $y, int $minY): int
    {
        if ($y < $minY) {
            $pdf->addPage();
            $this->renderHeader($pdf);
            $y = $this->yTop;
        }
        return $y;
    }

    private function ensureBlock(ProfessionalPdf $pdf, int $y, int $needed): int
    {
        return $this->ensureSpace($pdf, $y, $this->yBottom + $this->footerReserve + $needed);
    }

    private function sp(int $base): int
    {
        return (int) max(1, round($base * $this->fontScale));
    }

    private function setFontScaled(ProfessionalPdf $pdf, string $key, int $size): void
    {
        $pdf->setFont($key, $this->scaleFont($size));
    }

    private function setFontSizeScaled(ProfessionalPdf $pdf, int $size): void
    {
        $pdf->setFontSize($this->scaleFont($size));
    }

    private function scaleFont(int $size): int
    {
        return max(8, min(24, $size));
    }

    private function applyBodyText(ProfessionalPdf $pdf): void
    {
        $c = $this->textBody;
        $pdf->setFillColor($c[0], $c[1], $c[2]);
    }

    private function applyHeadingText(ProfessionalPdf $pdf): void
    {
        $c = $this->textHeading;
        $pdf->setFillColor($c[0], $c[1], $c[2]);
    }

    private function applyDividerStroke(ProfessionalPdf $pdf): void
    {
        $c = $this->divider;
        $pdf->setStrokeColor($c[0], $c[1], $c[2]);
    }

    private function bestTextOn(array $bg): array
    {
        $white = [255, 255, 255];
        $dark = $this->textHeading;
        $rw = $this->contrastRatio($white, $bg);
        $rd = $this->contrastRatio($dark, $bg);
        if ($rw >= 4.5 && $rw >= $rd) {
            return $white;
        }
        if ($rd >= 4.5) {
            return $dark;
        }
        return ($rw >= $rd) ? $white : $dark;
    }

    private function contrastRatio(array $rgb1, array $rgb2): float
    {
        $l1 = $this->luminance($rgb1);
        $l2 = $this->luminance($rgb2);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(array $rgb): float
    {
        [$r, $g, $b] = $rgb;
        $rs = $this->srgbToLinear($r / 255);
        $gs = $this->srgbToLinear($g / 255);
        $bs = $this->srgbToLinear($b / 255);
        return 0.2126 * $rs + 0.7152 * $gs + 0.0722 * $bs;
    }

    private function srgbToLinear(float $c): float
    {
        if ($c <= 0.04045) {
            return $c / 12.92;
        }
        return pow(($c + 0.055) / 1.055, 2.4);
    }

    private function rgOp(array $rgb): string
    {
        return sprintf('%.3f %.3f %.3f rg', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
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
        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        if ($srcW <= 0 || $srcH <= 0) {
            return [0, 0];
        }
        return self::fitBox($srcW, $srcH, $maxW, $maxH);
    }

    private static function fitBox(int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        if ($srcW <= 0 || $srcH <= 0 || $maxW <= 0 || $maxH <= 0) {
            return [0, 0];
        }
        $scale = min($maxW / $srcW, $maxH / $srcH, 1.0);
        $w = (int) floor($srcW * $scale);
        $h = (int) floor($srcH * $scale);
        return [$w, $h];
    }

    private function setFontScaledMin(ProfessionalPdf $pdf, string $key, int $size, int $minSize): void
    {
        $pdf->setFont($key, max($minSize, $this->scaleFont($size)));
    }

    private function kvLine(ProfessionalPdf $pdf, int $x, int $xVal, int $y, string $label, string $value): int
    {
        $lineH = $this->sp(16);
        $this->applyHeadingText($pdf);
        $this->setFontScaledMin($pdf, 'F2', 12, 12);
        $pdf->text($x, $y, $label);
        $this->applyBodyText($pdf);
        $this->setFontScaledMin($pdf, 'F1', 11, 11);
        $pdf->text($xVal, $y, $value);
        return $y - $lineH;
    }

    private function centerText(ProfessionalPdf $pdf, int $centerX, int $y, string $text, string $fontKey, int $size): void
    {
        $scaled = $this->scaleFont($size);
        $pdf->setFont($fontKey, $scaled);
        $w = $this->approxTextWidth($text, $scaled);
        $x = (int) floor($centerX - ($w / 2));
        if ($x < $this->x0) {
            $x = $this->x0;
        }
        if (($x + (int) ceil($w)) > $this->x1) {
            $x = $this->x1 - (int) ceil($w);
        }
        $pdf->text($x, $y, $text);
    }

    private function rightAlignedText(ProfessionalPdf $pdf, int $rightX, int $y, string $text, string $fontKey, int $size): void
    {
        $scaled = $this->scaleFont($size);
        $pdf->setFont($fontKey, $scaled);
        $w = $this->approxTextWidth($text, $scaled);
        $x = (int) floor($rightX - $w);
        $pdf->text(max($this->x0, $x), $y, $text);
    }

    private function formatCnpj(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        $d = is_string($d) ? $d : '';
        if (strlen($d) !== 14) {
            return '';
        }
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }


    private function wrapPreserveNewlines(string $text, int $maxLen): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $parts = explode("\n", $text);

        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                $out[] = '';
                continue;
            }

            $words = preg_split('/\s+/', $part) ?: [];
            $line = '';
            foreach ($words as $w) {
                $w = (string) $w;
                $test = $line === '' ? $w : ($line . ' ' . $w);
                if (strlen($test) > $maxLen) {
                    if ($line !== '') {
                        $out[] = $line;
                    }
                    $line = $w;
                } else {
                    $line = $test;
                }
            }
            if ($line !== '') {
                $out[] = $line;
            }
        }

        if (count($out) === 0) {
            return [''];
        }

        return $out;
    }

    private function wrapLine(string $text, int $maxLen): array
    {
        $text = trim(preg_replace('/\s+/', ' ', str_replace(["\r\n", "\r", "\n"], ' ', $text)) ?? '');
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $out = [];
        $line = '';
        foreach ($words as $word) {
            $word = (string) $word;
            $test = $line === '' ? $word : ($line . ' ' . $word);
            if (mb_strlen($test) > $maxLen) {
                if ($line !== '') {
                    $out[] = $line;
                }
                $line = $word;
                continue;
            }
            $line = $test;
        }
        if ($line !== '') {
            $out[] = $line;
        }

        return count($out) > 0 ? $out : [''];
    }

    private function buildTextSegments(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [['type' => 'paragraph', 'text' => 'Não informado']];
        }

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $lines = explode("\n", $text);
        $segments = [];
        $paragraph = '';

        foreach ($lines as $rawLine) {
            $line = trim((string) $rawLine);
            if ($line === '') {
                if ($paragraph !== '') {
                    $segments[] = ['type' => 'paragraph', 'text' => $paragraph];
                    $paragraph = '';
                }
                continue;
            }

            if ($this->isHeadingLine($line)) {
                if ($paragraph !== '') {
                    $segments[] = ['type' => 'paragraph', 'text' => $paragraph];
                    $paragraph = '';
                }
                $segments[] = ['type' => 'heading', 'text' => $line];
                continue;
            }

            if ($this->isListLine($line)) {
                if ($paragraph !== '') {
                    $segments[] = ['type' => 'paragraph', 'text' => $paragraph];
                    $paragraph = '';
                }
                $segments[] = ['type' => 'list', 'text' => $line];
                continue;
            }

            $paragraph = $paragraph === '' ? $line : ($paragraph . ' ' . $line);
        }

        if ($paragraph !== '') {
            $segments[] = ['type' => 'paragraph', 'text' => $paragraph];
        }

        return count($segments) > 0 ? $segments : [['type' => 'paragraph', 'text' => 'Não informado']];
    }

    private function renderTextSegments(
        ProfessionalPdf $pdf,
        int $y,
        array $segments,
        int $maxLen,
        int $lineHeight,
        int $paragraphGap
    ): int {
        foreach ($segments as $segment) {
            $type = (string) ($segment['type'] ?? 'paragraph');
            $text = trim((string) ($segment['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $indent = $type === 'list' ? 12 : 0;
            $localMaxLen = $type === 'paragraph' ? $maxLen : max(24, $maxLen - 4);
            $lines = $this->wrapLine($text, $localMaxLen);

            if ($type === 'heading') {
                $this->applyHeadingText($pdf);
                $this->setFontScaled($pdf, 'F2', 10);
            } else {
                $this->applyBodyText($pdf);
                $this->setFontScaled($pdf, 'F1', 10);
            }

            foreach ($lines as $index => $line) {
                $y = $this->ensureBlock($pdf, $y, $this->sp($lineHeight + 18));
                $isJustifiedParagraph = $type === 'paragraph' && $index < (count($lines) - 1) && str_contains($line, ' ');
                if ($isJustifiedParagraph) {
                    $ws = $this->justifyWordSpacing($line, 10, $this->contentW - $indent);
                    $pdf->text($this->x0 + $indent, $y, $line, $ws);
                } else {
                    $pdf->text($this->x0 + $indent, $y, $line);
                }
                $y -= $this->sp($lineHeight);
            }

            $y -= $this->sp($type === 'heading' ? 4 : $paragraphGap);
        }

        return $y;
    }

    private function isHeadingLine(string $line): bool
    {
        if (mb_strlen($line) > 48) {
            return false;
        }

        if (preg_match('/[a-zà-ÿ]/u', $line) === 1) {
            return false;
        }

        return preg_match('/[A-ZÀ-Ý]/u', $line) === 1;
    }

    private function isListLine(string $line): bool
    {
        return preg_match('/^(?:[-*•·]|[0-9]+[.)]|[A-Za-z][.)]|[▪◦])\s+/u', $line) === 1;
    }

    private function brl(float $n): string
    {
        return 'R$ ' . number_format($n, 2, ',', '.');
    }

    private function tint(array $rgb, float $amount): array
    {
        $amount = max(0.0, min(1.0, $amount));
        return [
            (int) round(255 - (255 - $rgb[0]) * $amount),
            (int) round(255 - (255 - $rgb[1]) * $amount),
            (int) round(255 - (255 - $rgb[2]) * $amount),
        ];
    }

    private function justifyWordSpacing(string $line, int $fontSize, int $width): float
    {
        $spaces = substr_count($line, ' ');
        if ($spaces <= 0) {
            return 0.0;
        }
        $w = $this->approxTextWidth($line, $fontSize);
        $extra = max(0.0, $width - $w);
        $per = $extra / $spaces;
        if ($per < 0.12 || $per > 1.15) {
            return 0.0;
        }
        return $per;
    }

    private function approxTextWidth(string $text, int $fontSize): float
    {
        $spaces = substr_count($text, ' ');
        $len = strlen(str_replace(' ', '', $text));
        return ($len * 0.52 * $fontSize) + ($spaces * 0.28 * $fontSize);
    }

    private function hexToRgb(string $hex): array
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
