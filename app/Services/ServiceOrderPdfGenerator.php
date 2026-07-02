<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderPdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';
    private array $branding = [];
    private int $pageW = 595;
    private int $pageH = 842;
    private int $margin = 48;
    private int $contentW = 499;
    private int $yTop = 0;
    private int $yBottom = 56;
    private array $primary = [41, 50, 65];
    private array $accent = [238, 108, 77];
    private array $surface = [248, 250, 252];
    private array $surfaceStrong = [241, 245, 249];
    private array $border = [203, 213, 225];
    private array $text = [26, 26, 26];
    private array $muted = [71, 85, 105];

    public function build(array $branding, array $serviceOrder, array $attachments, array $history): string
    {
        $this->branding = $branding;
        $this->primary = $this->hexToRgb((string) ($branding['primary_color'] ?? '#293241'));
        $this->accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));

        $pdf = new ProfessionalPdf();
        $pdf->addPage();
        $y = $this->renderHeader($pdf);
        $y = $this->titleCard($pdf, $y, $serviceOrder);
        $y = $this->overviewTable($pdf, $y, $serviceOrder);
        $y = $this->scheduleTable($pdf, $y, $serviceOrder);

        $rich = new ServiceOrderRichText();
        $y = $this->textPanel($pdf, $y, 'Descrição da solicitação', $rich->toPlainText((string) ($serviceOrder['request_description'] ?? '')));
        $y = $this->textPanel($pdf, $y, 'Atividades executadas', $rich->toPlainText((string) ($serviceOrder['executed_activities'] ?? '')));
        $y = $this->textPanel($pdf, $y, 'Observações técnicas', $rich->toPlainText((string) ($serviceOrder['technical_notes'] ?? '')));

        $financialRows = [
            ['Possui cobrança', (int) ($serviceOrder['billable'] ?? 0) === 1 ? 'Sim' : 'Não'],
            ['Serviço base', (string) ($serviceOrder['base_service_name'] ?? 'Não informado')],
            ['Valor base', $this->money($serviceOrder['base_amount'] ?? 0)],
            ['Desconto', $this->money($serviceOrder['discount_amount'] ?? 0)],
            ['Acréscimo', $this->money($serviceOrder['surcharge_amount'] ?? 0)],
            ['Valor final', $this->money($serviceOrder['final_amount'] ?? 0)],
        ];
        $y = $this->tableSection($pdf, $y, 'Resumo financeiro', ['Campo', 'Valor'], [300, 199], $financialRows, [false, true]);

        $attachmentRows = [];
        foreach ($attachments as $attachment) {
            $attachmentRows[] = [
                (string) ($attachment['original_name'] ?? 'Arquivo'),
                strtoupper((string) ($attachment['file_extension'] ?? '—')),
                $this->formatBytes((int) ($attachment['file_size'] ?? 0)),
                (string) ($attachment['uploaded_by_name'] ?? 'Sistema'),
            ];
        }
        $y = $this->tableSection($pdf, $y, 'Anexos', ['Arquivo', 'Tipo', 'Tamanho', 'Responsável'], [275, 60, 72, 92], $attachmentRows);

        $historyRows = [];
        foreach (array_slice($history, 0, 12) as $item) {
            $historyRows[] = [
                $this->formatDateTime($item['created_at'] ?? ''),
                trim((string) ($item['message'] ?? 'Atualização registrada.')),
                (string) ($item['actor_name'] ?? 'Sistema'),
            ];
        }
        $y = $this->tableSection($pdf, $y, 'Histórico resumido', ['Data/Hora', 'Evento', 'Responsável'], [105, 304, 90], $historyRows);
        $this->signatureBlock($pdf, $y, $serviceOrder);

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $this->pageW, $this->contactLine, 20, [71, 85, 105], 10);
        return $pdf->output();
    }

    private function renderHeader(ProfessionalPdf $pdf): int
    {
        return PdfStandardTheme::renderHeaderMinimal(
            $pdf,
            $this->branding,
            $this->pageW,
            $this->pageH,
            $this->margin,
            56,
            4,
            32,
            180,
            30
        );
    }

    private function titleCard(ProfessionalPdf $pdf, int $y, array $serviceOrder): int
    {
        $y = $this->ensureSpace($pdf, $y, 144);
        $boxH = 104;
        $top = $y;
        $rightBoxW = 170;
        $rightX = $this->margin + $this->contentW - $rightBoxW - 18;
        $availableChars = max(20, (int) floor(($this->contentW - $rightBoxW - 78) / 5.7));
        $subtitleLines = $this->wrapText(
            'Documento técnico-operacional para acompanhamento, execução e faturamento do serviço.',
            $availableChars
        );

        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->rect($this->margin, $top - $boxH, $this->contentW, $boxH, 'F');
        $pdf->setFillColor(255, 255, 255);
        $pdf->setFont('F2', 18);
        $pdf->text($this->margin + 18, $top - 28, 'Ordem de Serviço');
        $pdf->setFont('F1', 11);
        foreach (array_slice($subtitleLines, 0, 3) as $idx => $line) {
            $pdf->text($this->margin + 18, $top - 48 - ($idx * 13), $line);
        }

        $pdf->setFillColor(255, 255, 255);
        $pdf->setStrokeColor(255, 255, 255);
        $pdf->rect($rightX, $top - 74, $rightBoxW, 50, 'S');
        $pdf->setFont('F2', 11);
        $pdf->text($rightX + 12, $top - 42, 'OS: ' . (string) ($serviceOrder['numero_os'] ?? '—'));
        $pdf->setFont('F1', 10);
        $pdf->text($rightX + 12, $top - 58, 'Status: ' . ServiceOrderStatus::label((string) ($serviceOrder['status'] ?? '')));

        return $top - $boxH - 18;
    }

    private function overviewTable(ProfessionalPdf $pdf, int $y, array $serviceOrder): int
    {
        $client = trim((string) (($serviceOrder['client_company'] ?? '') !== '' ? $serviceOrder['client_company'] : ($serviceOrder['client_name'] ?? '')));
        $rows = [
            ['Nome do serviço', (string) ($serviceOrder['service_name'] ?? 'Não informado')],
            ['Cliente', $client !== '' ? $client : 'Não informado'],
            ['Contato responsável', (string) ($serviceOrder['contact_name'] ?? ($serviceOrder['client_contact_person'] ?? 'Não informado'))],
            ['Responsável interno', (string) ($serviceOrder['assigned_user_name'] ?? 'Não informado')],
            ['Tipo', $this->typeLabel($serviceOrder)],
            ['Recebível vinculado', (int) ($serviceOrder['financial_receivable_id'] ?? 0) > 0 ? '#' . (int) $serviceOrder['financial_receivable_id'] : 'Não gerado'],
        ];

        return $this->tableSection($pdf, $y, 'Identificação', ['Campo', 'Valor'], [180, 319], $rows);
    }

    private function scheduleTable(ProfessionalPdf $pdf, int $y, array $serviceOrder): int
    {
        $rows = [
            ['Data de abertura', $this->formatDateTime($serviceOrder['opened_at'] ?? ''), 'Data prevista', $this->formatDateTime($serviceOrder['due_at'] ?? '')],
            ['Data de conclusão', $this->formatDateTime($serviceOrder['completed_at'] ?? ''), 'Horas previstas', $this->formatHours($serviceOrder['estimated_hours'] ?? null)],
            ['Horas executadas', $this->formatHours($serviceOrder['executed_hours'] ?? null), 'Situação', ServiceOrderStatus::label((string) ($serviceOrder['status'] ?? ''))],
        ];

        return $this->tableSection(
            $pdf,
            $y,
            'Cronograma e horas',
            ['Campo', 'Valor', 'Campo', 'Valor'],
            [120, 129, 120, 130],
            $rows,
            [false, false, false, false]
        );
    }

    private function textPanel(ProfessionalPdf $pdf, int $y, string $title, string $text): int
    {
        $lines = $this->wrapText($text !== '' ? $text : 'Não informado', 88);
        $height = 54 + (count($lines) * 12);
        $y = $this->ensureSpace($pdf, $y, $height + 10);
        $top = $y;

        $pdf->setFillColor($this->surface[0], $this->surface[1], $this->surface[2]);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->rect($this->margin, $top - $height, $this->contentW, $height, 'DF');

        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->setFont('F2', 12);
        $pdf->text($this->margin + 16, $top - 22, $title);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->line($this->margin + 16, $top - 30, $this->margin + $this->contentW - 16, $top - 30);

        $pdf->setFillColor($this->text[0], $this->text[1], $this->text[2]);
        $pdf->setFont('F1', 10);
        $lineY = $top - 46;
        foreach ($lines as $line) {
            $pdf->text($this->margin + 16, $lineY, $line === '' ? ' ' : $line);
            $lineY -= 12;
        }

        return $top - $height - 16;
    }

    private function tableSection(
        ProfessionalPdf $pdf,
        int $y,
        string $title,
        array $headers,
        array $widths,
        array $rows,
        ?array $rightAlign = null
    ): int {
        $rightAlign = is_array($rightAlign) ? $rightAlign : array_fill(0, count($headers), false);
        $y = $this->ensureSpace($pdf, $y, 110);
        $this->sectionHeading($pdf, $y, $title);
        $y -= 22;

        $tableTop = $y;
        $headerH = 24;
        $x = $this->margin;

        $pdf->setFillColor($this->surfaceStrong[0], $this->surfaceStrong[1], $this->surfaceStrong[2]);
        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->rect($this->margin, $tableTop - $headerH, $this->contentW, $headerH, 'DF');
        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->setFont('F2', 10);
        foreach ($headers as $index => $header) {
            $pdf->text($x + 8, $tableTop - 16, (string) $header);
            $x += (int) ($widths[$index] ?? 0);
        }

        $currentY = $tableTop - $headerH;
        $rowIndex = 0;
        if (count($rows) === 0) {
            $rows = [['Nenhum registro disponível', '', '', '']];
        }

        foreach ($rows as $row) {
            $prepared = [];
            $lineCount = 1;
            foreach ($headers as $index => $_header) {
                $cellText = (string) ($row[$index] ?? '');
                $maxChars = max(8, (int) floor(((int) ($widths[$index] ?? 80)) / 5.6));
                $prepared[$index] = $this->wrapText($cellText !== '' ? $cellText : '—', $maxChars);
                $lineCount = max($lineCount, count($prepared[$index]));
            }

            $rowH = 14 + ($lineCount * 12);
            if (($currentY - $rowH) < ($this->yBottom + 44)) {
                $pdf->addPage();
                $currentY = $this->renderHeader($pdf);
                $this->sectionHeading($pdf, $currentY, $title . ' (continuação)');
                $currentY -= 22;
                $tableTop = $currentY;
                $x = $this->margin;
                $pdf->setFillColor($this->surfaceStrong[0], $this->surfaceStrong[1], $this->surfaceStrong[2]);
                $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
                $pdf->rect($this->margin, $tableTop - $headerH, $this->contentW, $headerH, 'DF');
                $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
                $pdf->setFont('F2', 10);
                foreach ($headers as $index => $header) {
                    $pdf->text($x + 8, $tableTop - 16, (string) $header);
                    $x += (int) ($widths[$index] ?? 0);
                }
                $currentY = $tableTop - $headerH;
            }

            $fill = ($rowIndex % 2 === 0) ? [255, 255, 255] : $this->surface;
            $pdf->setFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
            $pdf->rect($this->margin, $currentY - $rowH, $this->contentW, $rowH, 'DF');

            $cellX = $this->margin;
            foreach ($headers as $index => $_header) {
                $width = (int) ($widths[$index] ?? 0);
                $cellLines = $prepared[$index] ?? ['—'];
                $textY = $currentY - 16;
                foreach ($cellLines as $line) {
                    $pdf->setFillColor($this->text[0], $this->text[1], $this->text[2]);
                    $pdf->setFont('F1', 10);
                    $textX = $cellX + 8;
                    if (($rightAlign[$index] ?? false) === true) {
                        $approx = (int) floor(strlen($line) * 5.1);
                        $textX = max($cellX + 8, ($cellX + $width) - $approx - 8);
                    }
                    $pdf->text($textX, $textY, $line);
                    $textY -= 12;
                }
                $cellX += $width;
            }

            $currentY -= $rowH;
            $rowIndex++;
        }

        return $currentY - 16;
    }

    private function signatureBlock(ProfessionalPdf $pdf, int $y, array $serviceOrder): void
    {
        $y = $this->ensureSpace($pdf, $y, 100);
        $lineY = max($this->yBottom + 52, $y - 20);
        $leftStart = $this->margin;
        $leftEnd = $this->margin + 215;
        $rightStart = $this->margin + 284;
        $rightEnd = $this->margin + $this->contentW;

        $pdf->setStrokeColor($this->border[0], $this->border[1], $this->border[2]);
        $pdf->line($leftStart, $lineY, $leftEnd, $lineY);
        $pdf->line($rightStart, $lineY, $rightEnd, $lineY);

        $pdf->setFillColor($this->muted[0], $this->muted[1], $this->muted[2]);
        $pdf->setFont('F1', 10);
        $pdf->text($leftStart + 30, $lineY - 16, 'Assinatura do cliente');
        $responsavel = (string) ($serviceOrder['assigned_user_name'] ?? 'Responsável técnico');
        $pdf->text($rightStart + 18, $lineY - 16, $responsavel);
    }

    private function sectionHeading(ProfessionalPdf $pdf, int $y, string $title): void
    {
        $pdf->setFillColor($this->primary[0], $this->primary[1], $this->primary[2]);
        $pdf->setFont('F2', 12);
        $pdf->text($this->margin, $y, $title);
    }

    private function ensureSpace(ProfessionalPdf $pdf, int $y, int $needed): int
    {
        if ($y >= ($this->yBottom + $needed)) {
            return $y;
        }

        $pdf->addPage();
        return $this->renderHeader($pdf);
    }

    private function wrapText(string $text, int $limit = 90): array
    {
        $text = trim(str_replace("\r", '', $text));
        if ($text === '') {
            return [];
        }

        $lines = [];
        foreach (explode("\n", $text) as $part) {
            $part = trim($part);
            if ($part === '') {
                $lines[] = '';
                continue;
            }
            $wrapped = wordwrap($part, $limit, "\n", true);
            foreach (explode("\n", $wrapped) as $chunk) {
                $lines[] = trim($chunk);
            }
        }

        return $lines;
    }

    private function typeLabel(array $serviceOrder): string
    {
        $type = (string) ($serviceOrder['type'] ?? '');
        if ($type === ServiceOrderType::OUTRO && trim((string) ($serviceOrder['type_other_description'] ?? '')) !== '') {
            return 'Outro - ' . trim((string) ($serviceOrder['type_other_description'] ?? ''));
        }
        return ServiceOrderType::label($type);
    }

    private function formatDateTime(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return 'Não informado';
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('d/m/Y H:i', $ts);
    }

    private function formatHours(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }
        return number_format((float) $value, 2, ',', '.') . ' h';
    }

    private function money(mixed $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, ',', '.') . ' KB';
        }
        return $bytes . ' B';
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
