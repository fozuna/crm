<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderPdfGenerator
{
    private string $contactLine = '+5567993256260 • comercial@traxter.com.br';

    public function build(array $branding, array $serviceOrder, array $attachments, array $history): string
    {
        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $pageW = 595;
        $pageH = 842;
        $margin = 50;
        $y = PdfStandardTheme::renderHeaderMinimal($pdf, $branding, $pageW, $pageH, $margin, 54, 4, 28, 200, 32);

        $pdf->setFillColor(26, 26, 26);
        $pdf->setFont('F2', 16);
        $pdf->text($margin, $y, 'Ordem de Serviço');
        $y -= 22;

        $pdf->setFont('F1', 11);
        $pdf->text($margin, $y, 'Número: ' . (string) ($serviceOrder['numero_os'] ?? ''));
        $pdf->text(320, $y, 'Status: ' . ServiceOrderStatus::label((string) ($serviceOrder['status'] ?? '')));
        $y -= 16;
        $pdf->text($margin, $y, 'Cliente: ' . (string) (($serviceOrder['client_company'] ?? '') !== '' ? $serviceOrder['client_company'] : ($serviceOrder['client_name'] ?? '')));
        $pdf->text(320, $y, 'Responsável: ' . (string) ($serviceOrder['assigned_user_name'] ?? 'Não informado'));
        $y -= 16;
        $pdf->text($margin, $y, 'Tipo: ' . $this->typeLabel($serviceOrder));
        $pdf->text(320, $y, 'Contato: ' . (string) ($serviceOrder['contact_name'] ?? ($serviceOrder['client_contact_person'] ?? 'Não informado')));
        $y -= 24;

        $y = $this->section($pdf, $margin, $y, 'Datas', [
            'Abertura: ' . $this->formatDateTime($serviceOrder['opened_at'] ?? ''),
            'Previsão: ' . $this->formatDateTime($serviceOrder['due_at'] ?? ''),
            'Conclusão: ' . $this->formatDateTime($serviceOrder['completed_at'] ?? ''),
            'Horas previstas: ' . $this->formatHours($serviceOrder['estimated_hours'] ?? null),
            'Horas executadas: ' . $this->formatHours($serviceOrder['executed_hours'] ?? null),
        ]);

        $rich = new ServiceOrderRichText();
        $y = $this->section($pdf, $margin, $y, 'Descrição da solicitação', $this->lines($rich->toPlainText($serviceOrder['request_description'] ?? '')));
        $y = $this->section($pdf, $margin, $y, 'Atividades executadas', $this->lines($rich->toPlainText($serviceOrder['executed_activities'] ?? '')));
        $y = $this->section($pdf, $margin, $y, 'Observações técnicas', $this->lines($rich->toPlainText($serviceOrder['technical_notes'] ?? '')));

        $financialLines = [
            'Gera cobrança: ' . ((int) ($serviceOrder['billable'] ?? 0) === 1 ? 'Sim' : 'Não'),
            'Serviço base: ' . (string) ($serviceOrder['base_service_name'] ?? 'Não informado'),
            'Valor base: ' . $this->money($serviceOrder['base_amount'] ?? 0),
            'Desconto: ' . $this->money($serviceOrder['discount_amount'] ?? 0),
            'Acréscimo: ' . $this->money($serviceOrder['surcharge_amount'] ?? 0),
            'Valor final: ' . $this->money($serviceOrder['final_amount'] ?? 0),
        ];
        $y = $this->section($pdf, $margin, $y, 'Financeiro', $financialLines);

        $attachmentLines = [];
        foreach ($attachments as $attachment) {
            $attachmentLines[] = (string) ($attachment['original_name'] ?? 'Arquivo')
                . ' • ' . $this->formatBytes((int) ($attachment['file_size'] ?? 0))
                . ' • ' . (string) ($attachment['uploaded_by_name'] ?? 'Sistema');
        }
        $y = $this->section($pdf, $margin, $y, 'Anexos', $attachmentLines);

        $historyLines = [];
        foreach (array_slice($history, 0, 10) as $item) {
            $message = trim((string) ($item['message'] ?? 'Atualização registrada.'));
            $historyLines[] = $this->formatDateTime($item['created_at'] ?? '') . ' • ' . $message;
        }
        $y = $this->section($pdf, $margin, $y, 'Histórico resumido', $historyLines);

        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $pageW, $this->contactLine, 20, [71, 85, 105], 10);
        return $pdf->output();
    }

    private function section(ProfessionalPdf $pdf, int $x, int $y, string $title, array $lines): int
    {
        $y = $this->ensureSpace($pdf, $y, 120);
        $pdf->setFont('F2', 12);
        $pdf->text($x, $y, $title);
        $y -= 14;
        $pdf->setFont('F1', 11);

        if (count($lines) === 0) {
            $lines = ['Não informado'];
        }

        foreach ($lines as $line) {
            foreach ($this->lines((string) $line, 92) as $wrapped) {
                $y = $this->ensureSpace($pdf, $y, 80);
                $pdf->text($x, $y, $wrapped === '' ? ' ' : $wrapped);
                $y -= 12;
            }
        }

        return $y - 12;
    }

    private function ensureSpace(ProfessionalPdf $pdf, int $y, int $needed): int
    {
        if ($y >= 60 + $needed) {
            return $y;
        }

        $pdf->addPage();
        return PdfStandardTheme::renderHeaderMinimal($pdf, [], 595, 842, 50, 54, 4, 28, 200, 32);
    }

    private function lines(string $text, int $limit = 90): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        $text = str_replace("\r", '', $text);
        $parts = explode("\n", $text);
        $lines = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                $lines[] = '';
                continue;
            }
            $wrapped = wordwrap($part, $limit, "\n", true);
            foreach (explode("\n", $wrapped) as $chunk) {
                $lines[] = $chunk;
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
}
