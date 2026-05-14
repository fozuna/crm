<?php
declare(strict_types=1);

namespace App\Services;

final class PaymentPlanCalculator
{
    public function calculate(array $method, float $subtotal, float $discountPercent, ?string $startDate = null): array
    {
        $discountPercent = max(0.0, min(100.0, $discountPercent));
        $discountAmount = round($subtotal * ($discountPercent / 100), 2);
        $total = round($subtotal - $discountAmount, 2);

        $schedule = [];
        $baseDate = $this->normalizeDate($startDate) ?? date('Y-m-d');

        $type = (string) ($method['type'] ?? 'avista');
        $installmentsCount = (int) ($method['installments_count'] ?? 1);
        $intervalDays = (int) ($method['interval_days'] ?? 30);
        $hasDown = (int) ($method['has_down_payment'] ?? 0) === 1;
        $downPercent = (float) ($method['down_payment_percent'] ?? 0);
        $downPercent = max(0.0, min(100.0, $downPercent));

        if ($type === 'avista') {
            $schedule[] = [
                'no' => 1,
                'kind' => 'avista',
                'due_date' => $baseDate,
                'amount' => $total,
            ];
        } else {
            $remainingTotal = $total;
            $offset = 0;

            if ($hasDown && $downPercent > 0) {
                $downAmount = round($total * ($downPercent / 100), 2);
                $downAmount = min($downAmount, $total);
                $remainingTotal = round($total - $downAmount, 2);
                $schedule[] = [
                    'no' => 0,
                    'kind' => 'entrada',
                    'due_date' => $baseDate,
                    'amount' => $downAmount,
                ];
                $offset = $intervalDays;
            }

            $count = max(1, $installmentsCount);
            $base = floor(($remainingTotal / $count) * 100) / 100;
            $sum = 0.0;
            for ($i = 1; $i <= $count; $i++) {
                $amount = $base;
                $sum += $amount;
                $schedule[] = [
                    'no' => $i,
                    'kind' => 'parcela',
                    'due_date' => $this->addDays($baseDate, $offset + ($intervalDays * ($i - 1))),
                    'amount' => $amount,
                ];
            }

            $diff = round($remainingTotal - $sum, 2);
            if ($diff !== 0.0) {
                $lastIndex = count($schedule) - 1;
                $schedule[$lastIndex]['amount'] = round(((float) $schedule[$lastIndex]['amount']) + $diff, 2);
            }
        }

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'schedule' => $schedule,
        ];
    }

    private function normalizeDate(?string $date): ?string
    {
        if (!is_string($date)) {
            return null;
        }
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }
        return $date;
    }

    private function addDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime($date . ' +' . max(0, $days) . ' days'));
    }
}

