<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderApprovalPdfGenerator
{
    public function build(array $branding, array $serviceOrder, array $approval): string
    {
        $pdf = new ProfessionalPdf();
        $pdf->addPage();

        $pageW = 595;
        $pageH = 842;
        $margin = 48;
        $x1 = $pageW - $margin;
        $accent = $this->hexToRgb((string) ($branding['accent_color'] ?? '#ee6c4d'));
        $cursorY = PdfStandardTheme::renderHeaderMinimal($pdf, $branding, $pageW, $pageH, $margin, 60, 5, 30, 140, 32);

        $status = (string) ($approval['status'] ?? 'pendente');
        $cursorY = PdfStandardTheme::documentTitleBlock(
            $pdf,
            $margin,
            $x1,
            $cursorY,
            'Ordem de serviço',
            'Comprovante de Aprovação',
            ['Status registrado: ' . $this->labelStatus($status)],
            PdfStandardTheme::INK,
            $accent,
            PdfStandardTheme::MUTED,
            18
        );

        $blocks = [
            'Identificação da OS' => [
                'Número da OS: ' . (string) ($serviceOrder['numero_os'] ?? 'Não informado'),
                'Serviço: ' . (string) ($serviceOrder['service_name'] ?? 'Não informado'),
                'Cliente: ' . (string) (($serviceOrder['client_company'] ?? '') !== '' ? $serviceOrder['client_company'] : ($serviceOrder['client_name'] ?? 'Não informado')),
                'Valor final: ' . $this->formatMoney((float) ($serviceOrder['final_amount'] ?? 0)),
            ],
            'Manifestação do cliente' => [
                'Nome informado: ' . (string) ($approval['requester_name'] ?? 'Não informado'),
                'E-mail informado: ' . (string) ($approval['requester_email'] ?? 'Não informado'),
                'Telefone informado: ' . (string) ($approval['requester_phone'] ?? 'Não informado'),
                'Data da decisão: ' . $this->formatDateTime((string) ($approval['decision_at'] ?? '')),
            ],
            'Rastreabilidade' => [
                'Identificador do ator: ' . (string) ($approval['actor_identifier'] ?? 'Não informado'),
                'IP: ' . (string) ($approval['actor_ip'] ?? 'Não informado'),
                'Geolocalização aproximada: ' . (string) ($approval['actor_geo_summary'] ?? 'Não informada'),
                'Primeiro acesso: ' . $this->formatDateTime((string) ($approval['first_access_at'] ?? '')),
            ],
        ];

        foreach ($blocks as $title => $lines) {
            $cursorY = $this->renderBlock($pdf, $margin, $x1, $accent, $cursorY, $title, $lines);
        }

        $description = [
            'Descrição da solicitação',
            $this->plainText((string) ($serviceOrder['request_description'] ?? '')),
            '',
            'Atividades executadas',
            $this->plainText((string) ($serviceOrder['executed_activities'] ?? '')),
            '',
            'Observações técnicas',
            $this->plainText((string) ($serviceOrder['technical_notes'] ?? '')),
        ];
        $cursorY = $this->renderBlock($pdf, $margin, $x1, $accent, $cursorY, 'Resumo técnico', $description);

        $justification = trim((string) ($approval['justification'] ?? ''));
        if ($justification !== '') {
            $cursorY = $this->renderBlock($pdf, $margin, $x1, $accent, $cursorY, 'Justificativa do cliente', [$justification]);
        }

        $contactLine = trim(implode(' • ', array_filter([
            (string) ($branding['company_email'] ?? ''),
            (string) ($branding['company_whatsapp'] ?? ''),
            (string) ($branding['company_website'] ?? ''),
        ], static fn(string $value): bool => $value !== '')));
        if ($contactLine === '') {
            $contactLine = 'TRAXTER CRM';
        }
        PdfStandardTheme::appendCenteredFooterPaginationAndContact($pdf, $pageW, $contactLine, 24, [100, 116, 139], 9);

        return $pdf->output();
    }

    private function renderBlock(ProfessionalPdf $pdf, int $margin, int $x1, array $accent, int $cursorY, string $title, array $lines): int
    {
        $cursorY = PdfStandardTheme::sectionHeading($pdf, $margin, $x1, $cursorY, $title, PdfStandardTheme::INK, $accent);

        $pdf->setFont('F1', 10);
        $pdf->setFillColor(PdfStandardTheme::BODY[0], PdfStandardTheme::BODY[1], PdfStandardTheme::BODY[2]);
        foreach ($lines as $line) {
            if (trim((string) $line) === '') {
                $cursorY -= 8;
                continue;
            }
            $wrapped = $this->wrap((string) $line, 92);
            foreach ($wrapped as $row) {
                $pdf->text($margin, $cursorY, $row);
                $cursorY -= 13;
            }
            $cursorY -= 4;
        }

        return $cursorY - 4;
    }

    private function wrap(string $text, int $maxChars): array
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return ['-'];
        }

        $words = explode(' ', $text);
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : ($current . ' ' . $word);
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

        return $lines === [] ? ['-'] : $lines;
    }

    private function plainText(string $html): string
    {
        return trim((new ServiceOrderRichText())->toPlainText($html));
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'aprovada' => 'Aprovada',
            'ajustes_solicitados' => 'Solicitação de ajustes',
            'expirada' => 'Expirada',
            'revogada' => 'Revogada',
            default => 'Pendente',
        };
    }

    private function formatMoney(float $value): string
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
            return [238, 108, 77];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function formatDateTime(string $value): string
    {
        if ($value === '') {
            return 'Não informado';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return $value;
        }
        return date('d/m/Y H:i:s', $ts);
    }
}
