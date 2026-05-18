<?php
declare(strict_types=1);

namespace App\Services;

final class ProposalPdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';
    private string $company = 'TRAXTER.';
    private string $logoPath = '';
    private array $primary = [41, 50, 65];
    private array $accent = [238, 108, 77];
    private string $companyCnpj = '';

    private array $textBody = [26, 26, 26];
    private array $textHeading = [17, 24, 39];
    private array $divider = [107, 114, 128];
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
    private int $headerH = 54;
    private int $footerReserve = 170;

    public function build(array $branding, array $proposal, array $items, array $milestones, array $paymentOptions, int $selectedIndex): string
    {
        $this->margin = (int) round(72);
        $this->x0 = $this->margin;
        $this->x1 = $this->pageW - $this->margin;
        $this->contentW = $this->x1 - $this->x0;
        $this->yBottom = $this->margin;

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

        $y = $this->sectionTitle($pdf, $y, 'Proposta Comercial');
        $y = $this->initialDataBox($pdf, $y, (int) ($proposal['id'] ?? 0), (string) ($proposal['client_name'] ?? ''), $issueDate);

        $y = $this->sectionSeparator($pdf, $y);
        $y = $this->fieldBlock($pdf, $y, 'Descrição do projeto', (string) ($proposal['description'] ?? ''));

        $y = $this->sectionSeparator($pdf, $y);
        $y = $this->itemsTable($pdf, $y, $items);

        $y = $this->sectionSeparator($pdf, $y);
        $y = $this->financialSummary($pdf, $y, $proposal, $paymentOptions, $selectedIndex);

        $y = $this->sectionSeparator($pdf, $y);
        $y = $this->deliveryBlock($pdf, $y, $proposal, $milestones);

        $y = $this->sectionSeparator($pdf, $y);
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

    private function sectionTitle(ProfessionalPdf $pdf, int $y, string $title): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(170));
        $this->applyHeadingText($pdf);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, $title);
        return $y - $this->sp(24);
    }

    private function initialDataBox(ProfessionalPdf $pdf, int $y, int $proposalId, string $clientName, string $issueDate): int
    {
        $labelW = 88;
        $clientLines = $this->wrapPreserveNewlines($clientName, 64);
        $clientLines = array_values(array_filter($clientLines, static fn($l) => $l !== ''));
        if (count($clientLines) === 0) {
            $clientLines = [''];
        }

        $lineH = $this->sp(16);
        $gap = $this->sp(10);
        $pad = $this->sp(16);
        $totalLines = 1 + count($clientLines) + 1;
        $boxH = ($pad * 2) + ($totalLines * $lineH) + (2 * $gap);

        $y = $this->ensureBlock($pdf, $y, $this->sp($boxH + 36));
        $yTop = $y;
        $yBoxBottom = $yTop - $boxH;

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $pdf->line($this->x0, $yTop, $this->x1, $yTop);
        $pdf->line($this->x0, $yTop - 2, $this->x1, $yTop - 2);
        $pdf->line($this->x0, $yBoxBottom, $this->x1, $yBoxBottom);
        $pdf->line($this->x0, $yBoxBottom + 2, $this->x1, $yBoxBottom + 2);

        $x = $this->x0 + 12;
        $xVal = $x + $labelW;
        $curY = $yTop - $pad - $this->sp(2);

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

        return $yBoxBottom - $this->sp(24);
    }

    private function sectionSeparator(ProfessionalPdf $pdf, int $y): int
    {
        $y -= $this->sp(6);
        $y = $this->ensureBlock($pdf, $y, $this->sp(40));
        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $pdf->line($this->x0, $y, $this->x1, $y);
        return $y - $this->sp(18);
    }

    private function fieldBlock(ProfessionalPdf $pdf, int $y, string $title, string $body): int
    {
        $lines = $this->wrapPreserveNewlines($body, 95);
        $nonEmpty = count(array_filter($lines, static fn($l) => $l !== ''));
        $minLines = min(3, max(1, $nonEmpty));
        $needed = $this->sp(14 + 14 + (12 * $minLines) + 24);
        $y = $this->ensureBlock($pdf, $y, $needed);

        $this->applyHeadingText($pdf);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, $title);
        $y -= $this->sp(14);

        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 11);
        $justify = count(array_filter($lines, static fn($l) => $l !== '')) > 3;

        foreach ($lines as $line) {
            $y = $this->ensureBlock($pdf, $y, $this->sp(70));
            if ($line === '') {
                $y -= $this->sp(12);
                continue;
            }

            if ($justify && str_contains($line, ' ')) {
                $ws = $this->justifyWordSpacing($line, 11, $this->contentW);
                $pdf->text($this->x0, $y, $line, $ws);
            } else {
                $pdf->text($this->x0, $y, $line);
            }
            $y -= $this->sp(12);
        }

        return $y - $this->sp(24);
    }

    private function block(ProfessionalPdf $pdf, int $y, string $title, string $body): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(120));
        $this->applyHeadingText($pdf);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, $title);
        $y -= $this->sp(14);
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 11);
        foreach ($this->wrapPreserveNewlines($body, 90) as $line) {
            $y = $this->ensureBlock($pdf, $y, $this->sp(70));
            if ($line === '') {
                $y -= $this->sp(12);
                continue;
            }
            $pdf->text($this->x0, $y, $line);
            $y -= $this->sp(12);
        }
        return $y - $this->sp(10);
    }

    private function itemsTable(ProfessionalPdf $pdf, int $y, array $items): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(140));
        $this->applyHeadingText($pdf);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, 'Serviços');
        $y -= $this->sp(24);

        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, 'Descrição');
        $pdf->text($this->x0 + 300, $y, 'Qtd');
        $pdf->text($this->x0 + 350, $y, 'Valor');
        $pdf->text($this->x0 + 430, $y, 'Total');
        $y -= $this->sp(8);
        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $pdf->line($this->x0, $y, $this->x1, $y);
        $y -= $this->sp(16);

        foreach ($items as $it) {
            $y = $this->ensureBlock($pdf, $y, $this->sp(90));
            $desc = (string) ($it['description'] ?? '');
            if ((int) ($it['is_bonus'] ?? 0) === 1) {
                $desc = '[BÔNUS] ' . $desc;
            }
            $lines = $this->wrapPreserveNewlines($desc, 60);
            $qty = (float) ($it['qty'] ?? 0);
            $unit = (float) ($it['unit_price'] ?? 0);
            $tot = (float) ($it['total'] ?? 0);
            $this->applyBodyText($pdf);
            $this->setFontScaled($pdf, 'F1', 11);
            $pdf->text($this->x0, $y, $lines[0] ?? '');
            $pdf->text($this->x0 + 300, $y, (string) $qty);
            $pdf->text($this->x0 + 350, $y, $this->brl($unit));
            $pdf->text($this->x0 + 430, $y, $this->brl($tot));
            $y -= $this->sp(12);
            for ($i = 1; $i < count($lines); $i++) {
                $y = $this->ensureBlock($pdf, $y, $this->sp(70));
                if ($lines[$i] === '') {
                    $y -= $this->sp(12);
                    continue;
                }
                $pdf->text($this->x0, $y, $lines[$i]);
                $y -= $this->sp(12);
            }
            $y -= $this->sp(6);
        }

        return $y - $this->sp(6);
    }

    private function financialSummary(ProfessionalPdf $pdf, int $y, array $proposal, array $paymentOptions, int $selectedIndex): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(160));
        $this->applyHeadingText($pdf);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, 'Resumo financeiro');
        $y -= $this->sp(24);
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 11);

        $subtotal = (float) ($proposal['subtotal'] ?? 0);
        $discountP = (float) ($proposal['discount_percent'] ?? 0);
        $discountA = (float) ($proposal['discount_amount'] ?? 0);
        $total = (float) ($proposal['total'] ?? 0);

        $pdf->text($this->x0, $y, 'Subtotal: ' . $this->brl($subtotal));
        $y -= $this->sp(12);
        $pdf->text($this->x0, $y, 'Desconto (' . number_format($discountP, 2, ',', '.') . '%): ' . $this->brl($discountA));
        $y -= $this->sp(12);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, 'Total: ' . $this->brl($total));
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 11);
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

            $y = $this->ensureBlock($pdf, $y, $this->sp(110));
            $this->applyHeadingText($pdf);
            $this->setFontScaled($pdf, 'F2', 12);
            $pdf->text($this->x0, $y, 'Forma de pagamento ' . ($idx + 1) . $tag);
            $y -= $this->sp(14);
            $this->applyBodyText($pdf);
            $this->setFontScaled($pdf, 'F1', 11);
            $pdf->text($this->x0, $y, $label);
            $y -= $this->sp(24);
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
                $y = $this->ensureBlock($pdf, $y, $this->sp(70));
                $pdf->text($this->x0 + 12, $y, $pLabel . ' (' . $due . '): ' . $this->brl($amount));
                $y -= $this->sp(12);
            }

            $special = trim((string) ($snap['special_terms'] ?? ''));
            if ($special !== '') {
                $y = $this->block($pdf, $y - $this->sp(4), 'Condições especiais (opção ' . ($idx + 1) . ')', $special);
            }
        }

        return $y - 6;
    }

    private function deliveryBlock(ProfessionalPdf $pdf, int $y, array $proposal, array $milestones): int
    {
        $y = $this->ensureBlock($pdf, $y, $this->sp(160));
        $this->applyHeadingText($pdf);
        $this->setFontScaled($pdf, 'F2', 12);
        $pdf->text($this->x0, $y, 'Prazos de entrega');
        $y -= $this->sp(24);
        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 11);

        $start = (string) ($proposal['delivery_start'] ?? '');
        $end = (string) ($proposal['delivery_end'] ?? '');
        $startTxt = $start !== '' ? date('d/m/Y', strtotime($start)) : '—';
        $endTxt = $end !== '' ? date('d/m/Y', strtotime($end)) : '—';
        $pdf->text($this->x0, $y, 'Início estimado: ' . $startTxt);
        $y -= $this->sp(12);
        $pdf->text($this->x0, $y, 'Término estimado: ' . $endTxt);
        $y -= $this->sp(14);

        foreach ($milestones as $m) {
            $title = (string) ($m['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $due = (string) ($m['due_date'] ?? '');
            $dueTxt = $due !== '' ? date('d/m/Y', strtotime($due)) : '—';
            $y = $this->ensureBlock($pdf, $y, $this->sp(70));
            $pdf->text($this->x0 + 12, $y, 'Marco: ' . $title . ' (' . $dueTxt . ')');
            $y -= $this->sp(12);
        }

        $penalty = trim((string) ($proposal['penalty_terms'] ?? ''));
        if ($penalty !== '') {
            $y = $this->block($pdf, $y - $this->sp(6), 'Penalidades por atraso', $penalty);
        }

        return $y - 6;
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
        $minY = $this->yBottom + $this->footerReserve + 20;
        if ($y < $minY) {
            $pdf->addPage();
            $this->renderHeader($pdf);
        }

        $this->applyBodyText($pdf);
        $this->setFontScaled($pdf, 'F1', 11);
        $pdf->text($this->x0, $this->yBottom + $this->sp(120), 'Campo Grande, MS - ' . $issueDate);
        $pdf->text($this->x0, $this->yBottom + $this->sp(104), '"Proposta válida por 30 dias"');

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $half = (int) floor($this->contentW / 2);
        $leftStart = $this->x0;
        $leftEnd = $this->x0 + $half - 10;
        $rightStart = $this->x0 + $half + 10;
        $rightEnd = $this->x1;

        $inset = 12;
        $lineY = $this->yBottom + $this->sp(72);
        $pdf->line($leftStart + $inset, $lineY, $leftEnd - $inset, $lineY);
        $pdf->line($rightStart + $inset, $lineY, $rightEnd - $inset, $lineY);

        $labelY = $lineY - $this->sp(14);
        $leftCx = (int) floor(($leftStart + $leftEnd) / 2);
        $rightCx = (int) floor(($rightStart + $rightEnd) / 2);

        $this->applyBodyText($pdf);
        $this->centerText($pdf, $leftCx, $labelY, 'Assinatura do cliente', 'F1', 11);

        $this->centerText($pdf, $rightCx, $labelY, 'TRAXTER. Automações e Sistemas', 'F1', 11);
        $this->centerText($pdf, $rightCx, $labelY - $this->sp(12), '30.358.115/0001-13', 'F1', 11);
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
        return max(0.0, min(3.0, $per));
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
