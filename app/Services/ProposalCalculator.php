<?php
declare(strict_types=1);

namespace App\Services;

final class ProposalCalculator
{
    public function calculate(array $methodRules, float $subtotal, float $discountPercent, ?string $startDate = null): array
    {
        $calc = (new PaymentPlanCalculator())->calculate($methodRules, $subtotal, $discountPercent, $startDate);

        $snapshot = [
            'method_id' => (int) ($methodRules['id'] ?? 0),
            'method_name' => (string) ($methodRules['name'] ?? ''),
            'type' => (string) ($methodRules['type'] ?? 'avista'),
            'discount_percent' => (float) $calc['discount_percent'],
            'has_down_payment' => (int) ($methodRules['has_down_payment'] ?? 0),
            'down_payment_percent' => (float) ($methodRules['down_payment_percent'] ?? 0),
            'installments_count' => (int) ($methodRules['installments_count'] ?? 1),
            'interval_days' => (int) ($methodRules['interval_days'] ?? 30),
            'special_terms' => (string) ($methodRules['special_terms'] ?? ''),
            'schedule' => $calc['schedule'],
        ];

        return [
            'subtotal' => (float) $calc['subtotal'],
            'discount_percent' => (float) $calc['discount_percent'],
            'discount_amount' => (float) $calc['discount_amount'],
            'total' => (float) $calc['total'],
            'schedule' => $calc['schedule'],
            'snapshot' => $snapshot,
        ];
    }
}

