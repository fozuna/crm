<?php
declare(strict_types=1);

namespace App\DTOs;

final class FinancialReceiptData
{
    public function __construct(
        public readonly int $receivableId,
        public readonly float $amountReceived,
        public readonly float $interestAmount,
        public readonly float $fineAmount,
        public readonly float $discountAmount,
        public readonly ?string $paymentMethod,
        public readonly string $paymentDate,
        public readonly ?string $transactionReference,
        public readonly ?string $bankReference,
        public readonly ?string $receiptFilePath,
        public readonly ?string $observation,
        public readonly int $createdBy
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            receivableId: max(0, (int) ($data['receivable_id'] ?? 0)),
            amountReceived: round((float) ($data['amount_received'] ?? 0), 2),
            interestAmount: round((float) ($data['interest_amount'] ?? 0), 2),
            fineAmount: round((float) ($data['fine_amount'] ?? 0), 2),
            discountAmount: round((float) ($data['discount_amount'] ?? 0), 2),
            paymentMethod: self::nullableString($data['payment_method'] ?? null),
            paymentDate: trim((string) ($data['payment_date'] ?? '')),
            transactionReference: self::nullableString($data['transaction_reference'] ?? null),
            bankReference: self::nullableString($data['bank_reference'] ?? null),
            receiptFilePath: self::nullableString($data['receipt_file_path'] ?? null),
            observation: self::nullableString($data['observation'] ?? null),
            createdBy: max(0, (int) ($data['created_by'] ?? 0)),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v !== '' ? $v : null;
    }
}
