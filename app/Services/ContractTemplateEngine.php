<?php
declare(strict_types=1);

namespace App\Services;

final class ContractTemplateEngine
{
    public function render(array $template, array $context): array
    {
        $map = $this->placeholders($context);
        $body = (string) ($template['body_template'] ?? '');
        $footer = (string) ($template['footer_notes'] ?? '');

        return [
            'title' => $this->replace((string) ($template['header_title'] ?? 'Contrato de Prestacao de Servicos'), $map),
            'body' => $this->replace($body, $map),
            'footer' => $this->replace($footer, $map),
            'placeholders' => $map,
        ];
    }

    public function availablePlaceholders(): array
    {
        return [
            '{{proposal_id}}',
            '{{proposal_title}}',
            '{{proposal_total}}',
            '{{proposal_terms}}',
            '{{proposal_notes}}',
            '{{delivery_start}}',
            '{{delivery_end}}',
            '{{client_name}}',
            '{{client_company}}',
            '{{client_email}}',
            '{{client_phone}}',
            '{{company_legal_name}}',
            '{{company_trade_name}}',
            '{{company_cnpj}}',
            '{{company_email}}',
            '{{company_website}}',
            '{{services_summary}}',
            '{{payment_schedule}}',
            '{{milestones_summary}}',
            '{{contract_number}}',
            '{{signature_mode}}',
            '{{current_date}}',
        ];
    }

    private function placeholders(array $context): array
    {
        return [
            '{{proposal_id}}' => (string) ($context['proposal']['id'] ?? ''),
            '{{proposal_title}}' => (string) ($context['proposal']['title'] ?? ''),
            '{{proposal_total}}' => $this->money((float) ($context['proposal']['total'] ?? 0)),
            '{{proposal_terms}}' => $this->fallback((string) ($context['proposal']['terms'] ?? '')),
            '{{proposal_notes}}' => $this->fallback((string) ($context['proposal']['notes'] ?? '')),
            '{{delivery_start}}' => $this->date((string) ($context['proposal']['delivery_start'] ?? '')),
            '{{delivery_end}}' => $this->date((string) ($context['proposal']['delivery_end'] ?? '')),
            '{{client_name}}' => (string) ($context['client']['name'] ?? ''),
            '{{client_company}}' => $this->fallback((string) ($context['client']['company'] ?? '')),
            '{{client_email}}' => $this->fallback((string) ($context['client']['email'] ?? '')),
            '{{client_phone}}' => $this->fallback((string) ($context['client']['phone'] ?? '')),
            '{{company_legal_name}}' => $this->fallback((string) ($context['company']['legal_name'] ?? '')),
            '{{company_trade_name}}' => $this->fallback((string) ($context['company']['trade_name'] ?? '')),
            '{{company_cnpj}}' => $this->fallback($this->formatCnpj((string) ($context['company']['cnpj'] ?? ''))),
            '{{company_email}}' => $this->fallback((string) ($context['company']['email'] ?? '')),
            '{{company_website}}' => $this->fallback((string) ($context['company']['website'] ?? '')),
            '{{services_summary}}' => $this->servicesSummary((array) ($context['items'] ?? [])),
            '{{payment_schedule}}' => $this->paymentSchedule((array) ($context['payment_schedule'] ?? [])),
            '{{milestones_summary}}' => $this->milestonesSummary((array) ($context['milestones'] ?? [])),
            '{{contract_number}}' => (string) ($context['contract_number'] ?? ''),
            '{{signature_mode}}' => (string) ($context['signature_mode_label'] ?? ''),
            '{{current_date}}' => date('d/m/Y'),
        ];
    }

    private function replace(string $text, array $map): string
    {
        return strtr($text, $map);
    }

    private function fallback(string $value): string
    {
        $value = trim($value);
        return $value !== '' ? $value : 'Nao informado';
    }

    private function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function date(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Nao definido';
        }
        $ts = strtotime($value);
        return $ts !== false ? date('d/m/Y', $ts) : $value;
    }

    private function servicesSummary(array $items): string
    {
        if (count($items) === 0) {
            return 'Sem servicos cadastrados.';
        }

        $lines = [];
        foreach ($items as $item) {
            $desc = trim((string) ($item['description'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $qty = (float) ($item['qty'] ?? 0);
            $total = (float) ($item['total'] ?? 0);
            $lines[] = '- ' . $desc . ' | Qtd: ' . rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') . ' | Total: ' . $this->money($total);
        }

        return count($lines) > 0 ? implode("\n", $lines) : 'Sem servicos cadastrados.';
    }

    private function paymentSchedule(array $schedule): string
    {
        if (count($schedule) === 0) {
            return 'Sem cronograma financeiro registrado.';
        }

        $lines = [];
        foreach ($schedule as $row) {
            $kind = (string) ($row['kind'] ?? 'parcela');
            $no = (int) ($row['no'] ?? 0);
            $label = match ($kind) {
                'entrada' => 'Entrada',
                'avista' => 'Pagamento a vista',
                default => 'Parcela ' . max(1, $no),
            };
            $lines[] = '- ' . $label . ' | Vencimento: ' . $this->date((string) ($row['due_date'] ?? '')) . ' | Valor: ' . $this->money((float) ($row['amount'] ?? 0));
        }

        return implode("\n", $lines);
    }

    private function milestonesSummary(array $milestones): string
    {
        if (count($milestones) === 0) {
            return 'Sem marcos definidos.';
        }

        $lines = [];
        foreach ($milestones as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $lines[] = '- ' . $title . ' | Prazo: ' . $this->date((string) ($row['due_date'] ?? ''));
        }

        return count($lines) > 0 ? implode("\n", $lines) : 'Sem marcos definidos.';
    }

    private function formatCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj);
        $digits = is_string($digits) ? $digits : '';
        if (strlen($digits) !== 14) {
            return $cnpj;
        }

        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }
}
