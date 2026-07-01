<?php
declare(strict_types=1);

namespace App\Services;

final class LeadValidator
{
    public function validate(array $in, array $duplicates = []): array
    {
        $errors = [];

        $name = trim((string) ($in['name'] ?? ''));
        $company = trim((string) ($in['company'] ?? ''));
        $contactPerson = trim((string) ($in['contact_person'] ?? ''));
        $personType = trim((string) ($in['person_type'] ?? 'pj'));
        $documentNumber = $this->digits((string) ($in['document_number'] ?? ''));
        $email = trim((string) ($in['email'] ?? ''));
        $phone = $this->digits((string) ($in['phone'] ?? ''));
        $secondaryPhone = $this->digits((string) ($in['secondary_phone'] ?? ''));
        $postalCode = $this->digits((string) ($in['postal_code'] ?? ''));
        $street = trim((string) ($in['street'] ?? ''));
        $streetNumber = trim((string) ($in['street_number'] ?? ''));
        $addressComplement = trim((string) ($in['address_complement'] ?? ''));
        $neighborhood = trim((string) ($in['neighborhood'] ?? ''));
        $city = trim((string) ($in['city'] ?? ''));
        $state = strtoupper(trim((string) ($in['state'] ?? '')));
        $birthOrOpeningDate = trim((string) ($in['birth_or_opening_date'] ?? ''));
        $marketSegment = trim((string) ($in['market_segment'] ?? ''));
        $acquisitionSource = trim((string) ($in['acquisition_source'] ?? ''));
        $stage = trim((string) ($in['stage'] ?? LeadStages::CADASTRO_REALIZADO));
        $notes = trim((string) ($in['notes'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Nome do lead é obrigatório.';
        }

        if (!in_array($personType, ['pf', 'pj'], true)) {
            $errors['person_type'] = 'Tipo de pessoa inválido.';
        }

        if ($documentNumber === '') {
            $errors['document_number'] = 'CPF ou CNPJ é obrigatório.';
        } elseif (($personType === 'pf' && !$this->isValidCpf($documentNumber)) || ($personType === 'pj' && !$this->isValidCnpj($documentNumber))) {
            $errors['document_number'] = $personType === 'pf'
                ? 'Informe um CPF válido.'
                : 'Informe um CNPJ válido.';
        }

        if ($email === '') {
            $errors['email'] = 'E-mail principal é obrigatório.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }

        if ($phone === '' || strlen($phone) < 10 || strlen($phone) > 11) {
            $errors['phone'] = 'Informe um telefone principal válido.';
        }

        if ($secondaryPhone !== '' && (strlen($secondaryPhone) < 10 || strlen($secondaryPhone) > 11)) {
            $errors['secondary_phone'] = 'Informe um telefone secundário válido.';
        }

        if ($postalCode === '' || strlen($postalCode) !== 8) {
            $errors['postal_code'] = 'Informe um CEP válido.';
        }

        if ($street === '') {
            $errors['street'] = 'Logradouro é obrigatório.';
        }
        if ($streetNumber === '') {
            $errors['street_number'] = 'Número é obrigatório.';
        }
        if ($neighborhood === '') {
            $errors['neighborhood'] = 'Bairro é obrigatório.';
        }
        if ($city === '') {
            $errors['city'] = 'Cidade é obrigatória.';
        }
        if ($state === '' || strlen($state) !== 2) {
            $errors['state'] = 'UF é obrigatória.';
        }

        $date = $this->parseDate($birthOrOpeningDate);
        if ($date === null) {
            $errors['birth_or_opening_date'] = $personType === 'pf'
                ? 'Informe a data de nascimento.'
                : 'Informe a data de abertura.';
        }

        if ($marketSegment === '') {
            $errors['market_segment'] = 'Segmento de mercado é obrigatório.';
        }

        if ($acquisitionSource === '') {
            $errors['acquisition_source'] = 'Fonte de aquisição é obrigatória.';
        }

        if (!LeadStages::isValid($stage)) {
            $errors['stage'] = 'Estágio do Kanban inválido.';
        }

        if (($duplicates['document_number'] ?? 0) > 0) {
            $errors['document_number'] = 'Já existe lead ou cliente com este CPF/CNPJ.';
        }
        if (($duplicates['email'] ?? 0) > 0) {
            $errors['email'] = 'Já existe lead ou cliente com este e-mail.';
        }
        if (($duplicates['phone'] ?? 0) > 0) {
            $errors['phone'] = 'Já existe lead ou cliente com este telefone.';
        }
        if ($secondaryPhone !== '' && ($duplicates['secondary_phone'] ?? 0) > 0) {
            $errors['secondary_phone'] = 'Já existe lead ou cliente com este telefone secundário.';
        }

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'company' => $company === '' ? null : $company,
                'contact_person' => $contactPerson === '' ? null : $contactPerson,
                'person_type' => $personType,
                'document_number' => $documentNumber,
                'email' => strtolower($email),
                'phone' => $phone,
                'secondary_phone' => $secondaryPhone === '' ? null : $secondaryPhone,
                'postal_code' => $postalCode,
                'street' => $street,
                'street_number' => $streetNumber,
                'address_complement' => $addressComplement === '' ? null : $addressComplement,
                'neighborhood' => $neighborhood,
                'city' => $city,
                'state' => $state,
                'birth_or_opening_date' => $date?->format('Y-m-d'),
                'market_segment' => $marketSegment,
                'acquisition_source' => $acquisitionSource,
                'stage' => $stage,
                'notes' => $notes === '' ? null : $notes,
            ],
        ];
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $raw) {
                return $date;
            }
        }

        return null;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += ((int) $cpf[$i]) * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += ((int) $cnpj[$i]) * $weights1[$i];
        }
        $digit1 = $sum % 11;
        $digit1 = $digit1 < 2 ? 0 : 11 - $digit1;

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += ((int) $cnpj[$i]) * $weights2[$i];
        }
        $digit2 = $sum % 11;
        $digit2 = $digit2 < 2 ? 0 : 11 - $digit2;

        return (int) $cnpj[12] === $digit1 && (int) $cnpj[13] === $digit2;
    }
}
