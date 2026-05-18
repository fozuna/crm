<?php
declare(strict_types=1);

namespace App\Services;

final class FinancialReceivablePdfValidator
{
    public function validate(array $receivable): array
    {
        $errors = [];

        $id = (int) ($receivable['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Recebível inválido.';
        }

        $clientName = trim((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? '')));
        if ($clientName === '') {
            $errors[] = 'Cliente obrigatório.';
        }

        $title = trim((string) ($receivable['title'] ?? ''));
        if ($title === '') {
            $errors[] = 'Título obrigatório.';
        }

        $issueDate = trim((string) ($receivable['issue_date'] ?? ''));
        if ($issueDate === '' || !$this->isIsoDate($issueDate)) {
            $errors[] = 'Data de emissão obrigatória.';
        }

        $dueDate = trim((string) ($receivable['due_date'] ?? ''));
        if ($dueDate === '' || !$this->isIsoDate($dueDate)) {
            $errors[] = 'Data de vencimento obrigatória.';
        }

        $original = (float) ($receivable['original_amount'] ?? 0);
        if ($original <= 0) {
            $errors[] = 'Valor original deve ser maior que zero.';
        }

        return $errors;
    }

    private function isIsoDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        $ts = strtotime($value);
        return $ts !== false;
    }
}

