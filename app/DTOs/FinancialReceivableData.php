<?php
declare(strict_types=1);

namespace App\DTOs;

final class FinancialReceivableData
{
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $projectId,
        public readonly int $clientId,
        public readonly ?int $contractId,
        public readonly ?int $serviceOrderId,
        public readonly ?int $sourceInstallmentId,
        public readonly int $installmentNumber,
        public readonly int $totalInstallments,
        public readonly string $title,
        public readonly ?string $description,
        public readonly float $originalAmount,
        public readonly float $discountAmount,
        public readonly float $interestAmount,
        public readonly float $fineAmount,
        public readonly string $dueDate,
        public readonly ?string $issueDate,
        public readonly ?string $paymentDate,
        public readonly ?string $competenceDate,
        public readonly string $status,
        public readonly ?string $paymentMethod,
        public readonly ?string $paymentChannel,
        public readonly ?int $bankAccountId,
        public readonly ?int $categoryId,
        public readonly ?int $costCenterId,
        public readonly ?string $invoiceNumber,
        public readonly ?string $externalReference,
        public readonly ?string $notes,
        public readonly ?string $recurrenceGroup,
        public readonly int $recurrenceIntervalMonths,
        public readonly int $createdBy,
        public readonly int $updatedBy
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: max(1, (int) ($data['company_id'] ?? 1)),
            projectId: isset($data['project_id']) && (int) $data['project_id'] > 0 ? (int) $data['project_id'] : null,
            clientId: max(0, (int) ($data['client_id'] ?? 0)),
            contractId: isset($data['contract_id']) && (int) $data['contract_id'] > 0 ? (int) $data['contract_id'] : null,
            serviceOrderId: isset($data['service_order_id']) && (int) $data['service_order_id'] > 0 ? (int) $data['service_order_id'] : null,
            sourceInstallmentId: isset($data['source_installment_id']) && (int) $data['source_installment_id'] > 0 ? (int) $data['source_installment_id'] : null,
            installmentNumber: max(1, (int) ($data['installment_number'] ?? 1)),
            totalInstallments: max(1, (int) ($data['total_installments'] ?? 1)),
            title: trim((string) ($data['title'] ?? '')),
            description: self::nullableString($data['description'] ?? null),
            originalAmount: round((float) ($data['original_amount'] ?? 0), 2),
            discountAmount: round((float) ($data['discount_amount'] ?? 0), 2),
            interestAmount: round((float) ($data['interest_amount'] ?? 0), 2),
            fineAmount: round((float) ($data['fine_amount'] ?? 0), 2),
            dueDate: trim((string) ($data['due_date'] ?? '')),
            issueDate: self::nullableString($data['issue_date'] ?? null),
            paymentDate: self::nullableString($data['payment_date'] ?? null),
            competenceDate: self::nullableString($data['competence_date'] ?? null),
            status: trim((string) ($data['status'] ?? 'pending')),
            paymentMethod: self::nullableString($data['payment_method'] ?? null),
            paymentChannel: self::nullableString($data['payment_channel'] ?? null),
            bankAccountId: isset($data['bank_account_id']) && (int) $data['bank_account_id'] > 0 ? (int) $data['bank_account_id'] : null,
            categoryId: isset($data['category_id']) && (int) $data['category_id'] > 0 ? (int) $data['category_id'] : null,
            costCenterId: isset($data['cost_center_id']) && (int) $data['cost_center_id'] > 0 ? (int) $data['cost_center_id'] : null,
            invoiceNumber: self::nullableString($data['invoice_number'] ?? null),
            externalReference: self::nullableString($data['external_reference'] ?? null),
            notes: self::nullableString($data['notes'] ?? null),
            recurrenceGroup: self::nullableString($data['recurrence_group'] ?? null),
            recurrenceIntervalMonths: max(0, (int) ($data['recurrence_interval_months'] ?? 0)),
            createdBy: max(0, (int) ($data['created_by'] ?? 0)),
            updatedBy: max(0, (int) ($data['updated_by'] ?? ($data['created_by'] ?? 0))),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v !== '' ? $v : null;
    }
}
