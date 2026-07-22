<?php
declare(strict_types=1);

namespace App\Services;

final class FinancialReceivablePdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';
    private string $company = 'TRAXTER';
    private string $logoPath = '';
    private string $companyCnpj = '';
    private string $primaryHex = '#293241';
    private string $accentHex = '#ee6c4d';
    private array $primary = [41, 50, 65];
    private array $accent = [238, 108, 77];
    private array $branding = [];

    private array $textBody = [26, 26, 26];
    private array $textHeading = [17, 24, 39];
    private array $divider = [107, 114, 128];
    private float $fontScale = 1.0;

    private int $pageW = 595;
    private int $pageH = 842;
    private int $margin = 72;
    private int $x0 = 72;
    private int $x1 = 523;
    private int $contentW = 451;
    private int $yTop = 0;
    private int $yBottom = 72;
    private int $headerH = 54;
    private int $footerReserve = 140;

    public function build(array $branding, array $receivable): string
    {
        $errors = (new FinancialReceivablePdfValidator())->validate($receivable);
        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }

        $this->x0 = $this->margin;
        $this->x1 = $this->pageW - $this->margin;
        $this->contentW = $this->x1 - $this->x0;
        $this->yBottom = $this->margin;
        $this->branding = $branding;

        $this->primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $this->accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
        $this->primaryHex = (string) ($branding['primary_color'] ?? '#293241');
        $this->accentHex = (string) ($branding['accent_color'] ?? '#ee6c4d');
        $this->company = trim((string) ($branding['company_name'] ?? '')) !== '' ? trim((string) $branding['company_name']) : 'TRAXTER';
        $this->logoPath = trim((string) ($branding['logo_path'] ?? ''));
        $this->companyCnpj = (string) ($branding['company_cnpj'] ?? '');

        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $this->renderHeader($pdf);
        $y = $this->yTop;

        $y = $this->heroCard($pdf, $y, $receivable);
        $y = $this->receivableDataBox($pdf, $y, $receivable);
        $y = $this->clientBlock($pdf, $y, $receivable);
        $y = $this->itemsTable($pdf, $y, $receivable);
        $y = $this->totalsBlock($pdf, $y, $receivable);
        $y = $this->paymentBlock($pdf, $y, $receivable);

        $this->footerBlock($pdf, $y);
        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $this->pageW, $this->contactLine, 20, [71, 85, 105], 10);

        return $pdf->output();
    }

    private function renderHeader(ProfessionalPdf $pdf): void
    {
        $this->yTop = PdfStandardTheme::renderHeaderMinimal(
            $pdf,
            [
                'primary_color' => $this->primaryHex,
                'accent_color' => $this->accentHex,
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
            (int) floor(($this->margin + 14) / 2),
            160,
            28
        );
        $this->applyBodyText($pdf);
    }

    private function heroCard(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $y = $this->ensureBlock($pdf, $y, 140);
        $status = trim((string) ($receivable['status'] ?? ''));
        $due = $this->formatDate((string) ($receivable['due_date'] ?? ''));

        $meta = ['Título #' . (int) ($receivable['id'] ?? 0) . ' · Vencimento: ' . ($due !== '' ? $due : '—')];
        if ($status !== '') {
            $meta[] = 'Status: ' . $status;
        }

        return PdfStandardTheme::documentTitleBlock(
            $pdf,
            $this->x0,
            $this->x1,
            $y,
            'Documento financeiro',
            'Conta a Receber',
            $meta,
            $this->textHeading,
            $this->accent,
            PdfStandardTheme::MUTED
        );
    }

    private function receivableDataBox(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $labelW = 118;
        $pad = 16;
        $gap = 10;
        $lineH = 16;

        $id = (int) ($receivable['id'] ?? 0);
        $client = trim((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? '')));
        $project = trim((string) ($receivable['project_title'] ?? ''));
        $status = trim((string) ($receivable['status'] ?? ''));
        $issue = $this->formatDate((string) ($receivable['issue_date'] ?? ''));
        $due = $this->formatDate((string) ($receivable['due_date'] ?? ''));
        $installment = (int) ($receivable['installment_number'] ?? 1);
        $totalInstallments = (int) ($receivable['total_installments'] ?? 1);

        $clientLines = $this->wrapPreserveNewlines($client !== '' ? $client : '—', 64);
        $clientLines = array_values(array_filter($clientLines, static fn ($l) => $l !== ''));
        if ($clientLines === []) {
            $clientLines = ['—'];
        }

        $totalLines = 1 + count($clientLines) + 4;
        $boxH = ($pad * 2) + ($totalLines * $lineH) + (4 * $gap);

        $y = $this->ensureBlock($pdf, $y, $boxH + 36);
        $yTop = $y;
        $yBoxBottom = $yTop - $boxH;

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.75);
        $pdf->line($this->x0, $yTop, $this->x1, $yTop);
        $pdf->line($this->x0, $yBoxBottom, $this->x1, $yBoxBottom);

        $x = $this->x0 + 12;
        $xVal = $x + $labelW;
        $curY = $yTop - $pad - 2;

        $curY = $this->kvLine($pdf, $x, $xVal, $curY, 'Recebível:', '#' . $id);
        $curY -= $gap;

        $this->applyHeadingText($pdf);
        $pdf->setFont('F2', 12);
        $pdf->text($x, $curY, 'Cliente:');
        $this->applyBodyText($pdf);
        $pdf->setFont('F1', 11);
        $pdf->text($xVal, $curY, $clientLines[0]);
        $curY -= $lineH;
        for ($i = 1; $i < count($clientLines); $i++) {
            $pdf->text($xVal, $curY, $clientLines[$i]);
            $curY -= $lineH;
        }
        $curY -= $gap;

        $curY = $this->kvLine($pdf, $x, $xVal, $curY, 'Projeto:', $project !== '' ? $project : '—');
        $curY -= $gap;

        $curY = $this->kvLine($pdf, $x, $xVal, $curY, 'Status:', $status !== '' ? $status : '—');
        $curY -= $gap;

        $curY = $this->kvLine($pdf, $x, $xVal, $curY, 'Emissão:', $issue !== '' ? $issue : '—');
        $curY -= $gap;

        $curY = $this->kvLine($pdf, $x, $xVal, $curY, 'Vencimento:', $due !== '' ? $due : '—');
        $curY -= $gap;

        $this->kvLine($pdf, $x, $xVal, $curY, 'Parcela:', $installment . '/' . $totalInstallments);

        return $yBoxBottom - 24;
    }

    private function clientBlock(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $clientCompany = trim((string) ($receivable['client_company'] ?? ''));
        $clientName = trim((string) ($receivable['client_name'] ?? ''));
        $client = $clientCompany !== '' ? $clientCompany : ($clientName !== '' ? $clientName : '—');

        $contactParts = [];
        $contactPerson = trim((string) ($receivable['client_contact_person'] ?? ''));
        if ($contactPerson !== '') {
            $contactParts[] = $contactPerson;
        }
        $phone = trim((string) ($receivable['client_phone'] ?? ''));
        if ($phone !== '') {
            $contactParts[] = $phone;
        }
        $email = trim((string) ($receivable['client_email'] ?? ''));
        if ($email !== '') {
            $contactParts[] = $email;
        }
        $contact = $contactParts !== [] ? implode(' • ', $contactParts) : '—';

        $pairs = [
            ['Razao social / Nome', $client],
            ['CNPJ / CPF', '—'],
            ['Endereco', '—'],
            ['Contato', $contact],
        ];

        $needed = 160 + (count($pairs) * 18);
        $y = $this->ensureBlock($pdf, $y, $needed);

        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Dados do cliente', $this->textHeading, $this->accent);

        $this->applyBodyText($pdf);
        $pdf->setFont('F1', 11);

        foreach ($pairs as [$k, $v]) {
            $y = $this->ensureBlock($pdf, $y, 70);
            $this->applyHeadingText($pdf);
            $pdf->setFont('F2', 11);
            $pdf->text($this->x0, $y, (string) $k . ':');
            $this->applyBodyText($pdf);
            $pdf->setFont('F1', 11);
            $lines = $this->wrapPreserveNewlines((string) $v, 92);
            if ($lines === []) {
                $lines = ['—'];
            }
            $pdf->text($this->x0 + 150, $y, $lines[0]);
            $y -= 14;
            for ($i = 1; $i < count($lines); $i++) {
                $pdf->text($this->x0 + 150, $y, $lines[$i]);
                $y -= 14;
            }
            $y -= 6;
        }

        return $y - 10;
    }

    private function itemsTable(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $title = trim((string) ($receivable['title'] ?? ''));
        $desc = trim((string) ($receivable['description'] ?? ''));
        $fullDesc = trim($title . ($desc !== '' ? " - " . $desc : ''));

        $qty = 1;
        $unit = (float) ($receivable['original_amount'] ?? 0);
        $total = $qty * $unit;

        $y = $this->ensureBlock($pdf, $y, 220);

        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Serviços / Itens', $this->textHeading, $this->accent);

        $rowH = 24;
        $colDesc = (int) floor($this->contentW * 0.58);
        $colQty = (int) floor($this->contentW * 0.10);
        $colUnit = (int) floor($this->contentW * 0.16);
        $colTot = $this->contentW - $colDesc - $colQty - $colUnit;

        $xDesc = $this->x0;
        $xQty = $xDesc + $colDesc;
        $xUnit = $xQty + $colQty;
        $xTot = $xUnit + $colUnit;

        $tableTop = PdfStandardTheme::tableHeaderRow(
            $pdf,
            $this->x0,
            $y,
            $rowH,
            [$colDesc, $colQty, $colUnit, $colTot],
            ['Descrição', 'Qtd', 'Unit.', 'Total'],
            $this->primary,
            [false, false, true, true]
        );

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $pdf->line($this->x0, $tableTop, $this->x1, $tableTop);
        $pdf->line($this->x0, $tableTop - $rowH, $this->x1, $tableTop - $rowH);
        $pdf->setFillColor(255, 255, 255);
        $pdf->rect($this->x0, $tableTop - $rowH, $this->contentW, $rowH, 'F');
        $pdf->line($xQty, $tableTop, $xQty, $tableTop - $rowH);
        $pdf->line($xUnit, $tableTop, $xUnit, $tableTop - $rowH);
        $pdf->line($xTot, $tableTop, $xTot, $tableTop - $rowH);

        $this->applyBodyText($pdf);
        $pdf->setFont('F1', 10);
        $descLines = $this->wrapPreserveNewlines($fullDesc !== '' ? $fullDesc : '—', 56);
        $pdf->text($xDesc + 6, $tableTop - 16, $descLines[0] ?? '—');
        $pdf->text($xQty + 6, $tableTop - 16, (string) $qty);
        $this->rightAlignedTextLocal($pdf, $xTot - 6, $tableTop - 16, $this->brl($unit), 10);
        $this->rightAlignedTextLocal($pdf, $this->x1 - 6, $tableTop - 16, $this->brl($total), 10);

        $y = $tableTop - $rowH - 18;
        $this->applyBodyText($pdf);
        $pdf->setFont('F1', 10);
        for ($i = 1; $i < count($descLines) && $i < 8; $i++) {
            $y = $this->ensureBlock($pdf, $y, 70);
            $pdf->text($xDesc + 6, $y, $descLines[$i]);
            $y -= 12;
        }

        return $y - 8;
    }

    private function rightAlignedTextLocal(ProfessionalPdf $pdf, int $rightX, int $y, string $text, int $fontSize): void
    {
        $len = mb_strlen($text);
        $approx = (int) ceil($len * 0.52 * $fontSize);
        $x = max($this->x0, $rightX - $approx);
        $pdf->text($x, $y, $text);
    }

    private function totalsBlock(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $original = (float) ($receivable['original_amount'] ?? 0);
        $discount = (float) ($receivable['discount_amount'] ?? 0);
        $interest = (float) ($receivable['interest_amount'] ?? 0);
        $fine = (float) ($receivable['fine_amount'] ?? 0);
        $received = (float) ($receivable['received_amount'] ?? 0);
        $remaining = (float) ($receivable['remaining_amount'] ?? 0);
        $total = max(0, round($original + $interest + $fine - $discount, 2));

        $pairs = [
            ['Valor original', $this->brl($original)],
            ['Desconto', $this->brl($discount)],
            ['Juros', $this->brl($interest)],
            ['Multa', $this->brl($fine)],
            ['Total', $this->brl($total)],
            ['Recebido', $this->brl($received)],
            ['Saldo', $this->brl($remaining)],
        ];

        $y = $this->ensureBlock($pdf, $y, 210);

        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Resumo financeiro', $this->textHeading, $this->accent);

        $boxW = (int) floor($this->contentW * 0.58);
        $x = $this->x1 - $boxW;
        $rowH = 18;
        $boxH = 16 + (count($pairs) * $rowH) + 10;

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.75);
        $pdf->rect($x, $y - $boxH, $boxW, $boxH, 'S');

        $curY = $y - 16;
        foreach ($pairs as [$k, $v]) {
            $this->applyHeadingText($pdf);
            $pdf->setFont('F2', 11);
            $pdf->text($x + 12, $curY, (string) $k);
            $this->applyBodyText($pdf);
            $pdf->setFont('F2', 11);
            $pdf->text($x + (int) floor($boxW * 0.62), $curY, (string) $v);
            $curY -= $rowH;
        }

        return ($y - $boxH) - 18;
    }

    private function paymentBlock(ProfessionalPdf $pdf, int $y, array $receivable): int
    {
        $paymentMethod = trim((string) ($receivable['payment_method'] ?? ''));
        $paymentChannel = trim((string) ($receivable['payment_channel'] ?? ''));
        $bankName = trim((string) ($receivable['bank_name'] ?? ''));
        $accountName = trim((string) ($receivable['account_name'] ?? ''));
        $branch = trim((string) ($receivable['branch_number'] ?? ''));
        $account = trim((string) ($receivable['account_number'] ?? ''));
        $pix = trim((string) ($receivable['pix_key'] ?? ''));
        $competence = $this->formatDate((string) ($receivable['competence_date'] ?? ''));
        $category = trim((string) ($receivable['category_name'] ?? ''));
        $costCenter = trim((string) ($receivable['cost_center_name'] ?? ''));
        $contractId = (int) ($receivable['contract_id'] ?? 0);
        $recurrenceMonths = (int) ($receivable['recurrence_interval_months'] ?? 0);
        $invoice = trim((string) ($receivable['invoice_number'] ?? ''));
        $reference = trim((string) ($receivable['external_reference'] ?? ''));
        $notes = trim((string) ($receivable['notes'] ?? ''));

        $pairs = [
            ['Forma de pagamento', $paymentMethod !== '' ? $paymentMethod : '—'],
            ['Canal', $paymentChannel !== '' ? $paymentChannel : '—'],
            ['Competencia', $competence !== '' ? $competence : '—'],
            ['Categoria', $category !== '' ? $category : '—'],
            ['Centro de custo', $costCenter !== '' ? $costCenter : '—'],
            ['Contrato', $contractId > 0 ? ('Contrato #' . $contractId) : '—'],
            ['Banco', trim($bankName . ' ' . $accountName) !== '' ? trim($bankName . ' ' . $accountName) : '—'],
            ['Agencia / Conta', trim(($branch !== '' ? $branch : '—') . ' / ' . ($account !== '' ? $account : '—'))],
            ['PIX', $pix !== '' ? $pix : '—'],
            ['Documento (NF)', $invoice !== '' ? $invoice : '—'],
            ['Referencia', $reference !== '' ? $reference : '—'],
        ];
        if ($recurrenceMonths > 0) {
            $pairs[] = ['Recorrencia', 'A cada ' . $recurrenceMonths . ' mes(es)'];
        }
        if ($notes !== '') {
            $pairs[] = ['Observacoes', $notes];
        }

        $needed = 200 + (count($pairs) * 18);
        $y = $this->ensureBlock($pdf, $y, $needed);

        $y = PdfStandardTheme::sectionHeading($pdf, $this->x0, $this->x1, $y, 'Instruções para pagamento', $this->textHeading, $this->accent);

        foreach ($pairs as [$k, $v]) {
            $y = $this->ensureBlock($pdf, $y, 70);
            $this->applyHeadingText($pdf);
            $pdf->setFont('F2', 11);
            $pdf->text($this->x0, $y, (string) $k . ':');
            $this->applyBodyText($pdf);
            $pdf->setFont('F1', 11);
            $lines = $this->wrapPreserveNewlines((string) $v, 92);
            if ($lines === []) {
                $lines = ['—'];
            }
            $pdf->text($this->x0 + 170, $y, $lines[0]);
            $y -= 14;
            for ($i = 1; $i < count($lines); $i++) {
                $pdf->text($this->x0 + 170, $y, $lines[$i]);
                $y -= 14;
            }
            $y -= 6;
        }

        return $y - 10;
    }

    private function footerBlock(ProfessionalPdf $pdf, int $y): void
    {
        $footerY = max($this->yBottom + 36, $y);
        if ($footerY < 118) {
            $footerY = 118;
        }

        $this->applyDividerStroke($pdf);
        $pdf->setLineWidth(0.6);
        $pdf->line($this->x0, $footerY, $this->x0 + 220, $footerY);
        $pdf->line($this->x1 - 220, $footerY, $this->x1, $footerY);

        $this->applyBodyText($pdf);
        $pdf->setFont('F1', 10);
        $pdf->text($this->x0 + 36, $footerY - 16, 'Assinatura do cliente');
        $pdf->text($this->x1 - 220, $footerY - 16, $this->company);
    }


    private function kvLine(ProfessionalPdf $pdf, int $x, int $xVal, int $y, string $label, string $value): int
    {
        $this->applyHeadingText($pdf);
        $pdf->setFont('F2', 11);
        $pdf->text($x, $y, $label);
        $this->applyBodyText($pdf);
        $pdf->setFont('F1', 11);
        $pdf->text($xVal, $y, $value);
        return $y - 14;
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

    private function rgOp(array $rgb): string
    {
        return sprintf('%.3f %.3f %.3f rg', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
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

    private function brl(float $v): string
    {
        return 'R$ ' . number_format($v, 2, ',', '.');
    }

    private function wrapPreserveNewlines(string $text, int $maxLen): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $parts = explode("\n", $text);
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                $out[] = '';
                continue;
            }
            $out = array_merge($out, $this->wrap($p, $maxLen));
        }
        return $out;
    }

    private function wrap(string $text, int $maxLen): array
    {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }
        $words = preg_split('/\s+/', $text) ?: [];
        $lines = [];
        $cur = '';
        foreach ($words as $w) {
            $w = (string) $w;
            if ($cur === '') {
                $cur = $w;
                continue;
            }
            if (mb_strlen($cur . ' ' . $w) <= $maxLen) {
                $cur .= ' ' . $w;
            } else {
                $lines[] = $cur;
                $cur = $w;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines === [] ? [''] : $lines;
    }

    private function bestTextOn(array $bg): array
    {
        $y = (0.2126 * $bg[0]) + (0.7152 * $bg[1]) + (0.0722 * $bg[2]);
        return $y < 140 ? [255, 255, 255] : [17, 24, 39];
    }

    private function hexToRgb(string $hex): array
    {
        $hex = trim($hex);
        if ($hex === '') {
            return [41, 50, 65];
        }
        if ($hex[0] === '#') {
            $hex = substr($hex, 1);
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || preg_match('/^[0-9a-fA-F]{6}$/', $hex) !== 1) {
            return [41, 50, 65];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj);
        $digits = is_string($digits) ? $digits : '';
        if (strlen($digits) !== 14) {
            return '';
        }
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }

    private function formatDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        return date('d/m/Y', $ts);
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
}
