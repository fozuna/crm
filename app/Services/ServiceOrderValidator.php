<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderValidator
{
    public function validate(array $payload): array
    {
        $richText = new ServiceOrderRichText();

        $data = [
            'service_name' => trim((string) ($payload['service_name'] ?? '')),
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'contact_name' => $this->nullableText($payload['contact_name'] ?? null, 190),
            'assigned_user_id' => $this->nullableInt($payload['assigned_user_id'] ?? null),
            'type' => trim((string) ($payload['type'] ?? ServiceOrderType::SUPORTE)),
            'type_other_description' => $this->nullableText($payload['type_other_description'] ?? null, 190),
            'status' => trim((string) ($payload['status'] ?? ServiceOrderStatus::ABERTO)),
            'request_description' => $richText->sanitize($payload['request_description'] ?? ''),
            'executed_activities' => $richText->sanitize($payload['executed_activities'] ?? ''),
            'technical_notes' => $richText->sanitize($payload['technical_notes'] ?? ''),
            'internal_notes' => trim((string) ($payload['internal_notes'] ?? '')),
            'opened_at' => $this->normalizeDateTimeInput((string) ($payload['opened_at'] ?? '')),
            'due_at' => $this->normalizeDateTimeInput((string) ($payload['due_at'] ?? '')),
            'completed_at' => $this->normalizeDateTimeInput((string) ($payload['completed_at'] ?? '')),
            'estimated_hours' => $this->nullableDecimal($payload['estimated_hours'] ?? null),
            'executed_hours' => $this->nullableDecimal($payload['executed_hours'] ?? null),
            'billable' => $this->truthy($payload['billable'] ?? '0') ? 1 : 0,
            'base_service_id' => $this->nullableInt($payload['base_service_id'] ?? null),
            'base_amount' => round(Money::parseBRL((string) ($payload['base_amount'] ?? '0')), 2),
            'discount_amount' => round(Money::parseBRL((string) ($payload['discount_amount'] ?? '0')), 2),
            'surcharge_amount' => round(Money::parseBRL((string) ($payload['surcharge_amount'] ?? '0')), 2),
        ];

        $data['final_amount'] = round($data['base_amount'] - $data['discount_amount'] + $data['surcharge_amount'], 2);
        $errors = [];

        if ($data['service_name'] === '' || mb_strlen($data['service_name']) > 190) {
            $errors['service_name'] = 'Informe um nome válido para a ordem de serviço.';
        }
        if ($data['client_id'] <= 0) {
            $errors['client_id'] = 'Cliente obrigatório.';
        }
        if ($data['assigned_user_id'] !== null && $data['assigned_user_id'] <= 0) {
            $errors['assigned_user_id'] = 'Responsável interno inválido.';
        }
        if (!ServiceOrderType::isValid($data['type'])) {
            $errors['type'] = 'Tipo de serviço inválido.';
        }
        if ($data['type'] === ServiceOrderType::OUTRO && $data['type_other_description'] === null) {
            $errors['type_other_description'] = 'Descreva o tipo quando selecionar "Outro".';
        }
        if (!ServiceOrderStatus::isValid($data['status'])) {
            $errors['status'] = 'Status inválido.';
        }
        if ($data['opened_at'] === null) {
            $errors['opened_at'] = 'Data de abertura obrigatória.';
        }
        if ($data['due_at'] !== null && $data['opened_at'] !== null && $data['due_at'] < $data['opened_at']) {
            $errors['due_at'] = 'A data prevista não pode ser anterior à abertura.';
        }
        if ($data['completed_at'] !== null && $data['opened_at'] !== null && $data['completed_at'] < $data['opened_at']) {
            $errors['completed_at'] = 'A data de conclusão não pode ser anterior à abertura.';
        }
        if ($data['estimated_hours'] !== null && $data['estimated_hours'] < 0) {
            $errors['estimated_hours'] = 'Horas previstas inválidas.';
        }
        if ($data['executed_hours'] !== null && $data['executed_hours'] < 0) {
            $errors['executed_hours'] = 'Horas executadas inválidas.';
        }
        if ($data['billable'] === 1) {
            if ($data['final_amount'] <= 0) {
                $errors['final_amount'] = 'Serviços faturáveis precisam ter valor final maior que zero.';
            }
            if ($data['base_service_id'] === null || $data['base_service_id'] <= 0) {
                $errors['base_service_id'] = 'Selecione o serviço base para gerar cobrança.';
            }
        } else {
            $data['base_service_id'] = null;
            $data['base_amount'] = 0.0;
            $data['discount_amount'] = 0.0;
            $data['surcharge_amount'] = 0.0;
            $data['final_amount'] = 0.0;
        }

        if (ServiceOrderStatus::isClosed($data['status']) && $data['completed_at'] === null && $data['status'] !== ServiceOrderStatus::CANCELADO) {
            $data['completed_at'] = date('Y-m-d H:i:s');
        }
        if ($data['status'] === ServiceOrderStatus::CANCELADO) {
            $data['completed_at'] = null;
        }

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }
        return (int) $value;
    }

    private function nullableText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength);
        }
        return $text;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        return round((float) str_replace(',', '.', $text), 2);
    }

    private function truthy(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
    }

    private function normalizeDateTimeInput(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format($format === 'Y-m-d' ? 'Y-m-d 00:00:00' : 'Y-m-d H:i:s');
            }
        }

        return null;
    }
}
