<?php
declare(strict_types=1);

namespace App\Services;

final class InstallmentCharges
{
    public static function compute(float $openAmount, string $dueDate, ?string $today = null): array
    {
        $openAmount = round(max(0.0, $openAmount), 2);
        $today = is_string($today) && $today !== '' ? $today : date('Y-m-d');
        if ($openAmount <= 0 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) !== 1) {
            return ['days_overdue' => 0, 'penalty' => 0.0, 'interest' => 0.0, 'total' => $openAmount];
        }

        $days = (int) floor((strtotime($today . ' 00:00:00') - strtotime($dueDate . ' 00:00:00')) / 86400);
        if ($days <= 0) {
            return ['days_overdue' => 0, 'penalty' => 0.0, 'interest' => 0.0, 'total' => $openAmount];
        }

        $penalty = round($openAmount * 0.02, 2);
        $interest = round($openAmount * 0.00033 * $days, 2);
        $total = round($openAmount + $penalty + $interest, 2);

        return ['days_overdue' => $days, 'penalty' => $penalty, 'interest' => $interest, 'total' => $total];
    }
}

