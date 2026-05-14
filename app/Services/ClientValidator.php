<?php
declare(strict_types=1);

namespace App\Services;

final class ClientValidator
{
    public function __construct(private readonly ?\DateTimeImmutable $today = null)
    {
    }

    public function validate(array $in): array
    {
        $errors = [];

        $name = trim((string) ($in['name'] ?? ''));
        $email = trim((string) ($in['email'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? ''));
        $company = trim((string) ($in['company'] ?? ''));
        $contactPerson = trim((string) ($in['contact_person'] ?? ''));
        $status = trim((string) ($in['status'] ?? 'lead'));
        $projectReference = trim((string) ($in['project_reference'] ?? ''));

        $hasHostingContract = $this->isChecked($in['has_hosting_contract'] ?? null);
        $hostingAmountRaw = trim((string) ($in['hosting_contract_amount'] ?? ''));
        $hostingDueDateRaw = trim((string) ($in['hosting_due_date'] ?? ''));
        $hostingRenewalDaysRaw = trim((string) ($in['hosting_renewal_days'] ?? '45'));

        $managesDomain = $this->isChecked($in['manages_domain'] ?? null);
        $domainDueDateRaw = trim((string) ($in['domain_due_date'] ?? ''));
        $domainAmountRaw = trim((string) ($in['domain_amount'] ?? ''));

        if ($name === '') {
            $errors['name'] = 'Nome é obrigatório.';
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        }

        if (!in_array($status, ['lead', 'ativo'], true)) {
            $errors['status'] = 'Status inválido.';
        }

        $hostingAmount = null;
        $hostingDueDate = null;
        $hostingRenewalDays = null;
        $hostingRenewalDate = null;

        if ($hasHostingContract) {
            if ($hostingAmountRaw === '') {
                $errors['hosting_contract_amount'] = 'Informe o valor do contrato de hospedagem.';
            } else {
                $hostingAmount = Money::parseBRL($hostingAmountRaw);
            }

            $hostingDueDate = $this->parseDate($hostingDueDateRaw);
            if ($hostingDueDate === null) {
                $errors['hosting_due_date'] = 'Informe uma data de vencimento da hospedagem válida.';
            } elseif ($hostingDueDate < $this->today()) {
                $errors['hosting_due_date'] = 'A data de vencimento da hospedagem não pode estar no passado.';
            }

            if ($hostingRenewalDaysRaw === '' || filter_var($hostingRenewalDaysRaw, FILTER_VALIDATE_INT) === false) {
                $errors['hosting_renewal_days'] = 'Informe um prazo de renovação válido.';
            } else {
                $hostingRenewalDays = (int) $hostingRenewalDaysRaw;
                if ($hostingRenewalDays < 1 || $hostingRenewalDays > 45) {
                    $errors['hosting_renewal_days'] = 'O prazo de renovação deve estar entre 1 e 45 dias.';
                }
            }

            if ($hostingDueDate !== null && $hostingRenewalDays !== null && !isset($errors['hosting_renewal_days'])) {
                $hostingRenewalDate = $hostingDueDate->modify('+' . $hostingRenewalDays . ' days');
            }
        }

        $domainDueDate = null;
        $domainAmount = null;

        if ($managesDomain) {
            $domainDueDate = $this->parseDate($domainDueDateRaw);
            if ($domainDueDate === null) {
                $errors['domain_due_date'] = 'Informe uma data de vencimento do domínio válida.';
            } elseif ($domainDueDate < $this->today()) {
                $errors['domain_due_date'] = 'A data de vencimento do domínio não pode estar no passado.';
            }

            if ($domainAmountRaw === '') {
                $errors['domain_amount'] = 'Informe o valor do registro ou renovação do domínio.';
            } else {
                $domainAmount = Money::parseBRL($domainAmountRaw);
            }
        }

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'data' => [
                'name' => $name,
                'email' => $email === '' ? null : $email,
                'phone' => $phone === '' ? null : $phone,
                'company' => $company === '' ? null : $company,
                'contact_person' => $contactPerson === '' ? null : $contactPerson,
                'status' => $status,
                'project_reference' => $projectReference === '' ? null : $projectReference,
                'has_hosting_contract' => $hasHostingContract ? 1 : 0,
                'hosting_contract_amount' => $hasHostingContract ? $hostingAmount : null,
                'hosting_due_date' => $hasHostingContract && $hostingDueDate !== null ? $hostingDueDate->format('Y-m-d') : null,
                'hosting_renewal_days' => $hasHostingContract ? ($hostingRenewalDays ?? 45) : null,
                'hosting_renewal_suggested_date' => $hasHostingContract && $hostingRenewalDate !== null ? $hostingRenewalDate->format('Y-m-d') : null,
                'manages_domain' => $managesDomain ? 1 : 0,
                'domain_due_date' => $managesDomain && $domainDueDate !== null ? $domainDueDate->format('Y-m-d') : null,
                'domain_amount' => $managesDomain ? $domainAmount : null,
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

    private function today(): \DateTimeImmutable
    {
        return $this->today instanceof \DateTimeImmutable
            ? $this->today->setTime(0, 0)
            : new \DateTimeImmutable('today');
    }

    private function isChecked(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'on', 'yes', 'sim'], true);
    }
}
