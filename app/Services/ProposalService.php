<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Request;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\ProposalRepository;
use App\Repositories\ServiceRepository;

final class ProposalService
{
    public function itemsFromRequest(Request $request): array
    {
        $descriptions = $request->input('item_description', []);
        $qtys = $request->input('item_qty', []);
        $prices = $request->input('item_unit_price', []);
        $serviceIds = $request->input('item_service_id', []);

        if (!is_array($descriptions) || !is_array($qtys) || !is_array($prices) || !is_array($serviceIds)) {
            return [];
        }

        $serviceRepo = new ServiceRepository();

        $items = [];
        $count = max(count($descriptions), count($qtys), count($prices), count($serviceIds));
        for ($i = 0; $i < $count; $i++) {
            $desc = trim((string) ($descriptions[$i] ?? ''));
            $qty = (float) str_replace(',', '.', (string) ($qtys[$i] ?? '0'));
            $unit = (float) str_replace(',', '.', (string) ($prices[$i] ?? '0'));

            $sid = (int) ($serviceIds[$i] ?? 0);
            $service = null;
            if ($sid > 0) {
                $service = $serviceRepo->find($sid);
            }
            $isBonus = is_array($service) && (int) ($service['is_bonus'] ?? 0) === 1;
            $catalogPrice = is_array($service) ? (float) ($service['default_price'] ?? 0) : null;
            if ($desc === '' && is_array($service)) {
                $desc = (string) ($service['name'] ?? '');
            }
            if ($desc === '' || $qty <= 0 || $unit < 0) {
                continue;
            }

            if ($unit === 0.0 && is_array($service)) {
                $unit = (float) ($service['default_price'] ?? 0);
            }

            $total = $qty * $unit;
            $items[] = [
                'service_id' => $sid > 0 ? $sid : null,
                'is_bonus' => $isBonus ? 1 : 0,
                'catalog_price' => $catalogPrice,
                'description' => $desc,
                'qty' => $qty,
                'unit_price' => $unit,
                'total' => $total,
            ];
        }

        return $items;
    }

    public function validatePayload(Request $request, ?array $existingProposal = null): ?array
    {
        $clientId = (int) $request->input('client_id', 0);
        $title = trim((string) $request->input('title', ''));
        $description = trim((string) $request->input('description', ''));
        $notes = trim((string) $request->input('notes', ''));
        $terms = trim((string) $request->input('terms', ''));

        $paymentOptions = $this->paymentOptionsFromRequest($request, $existingProposal);
        if ($paymentOptions === null || count($paymentOptions) < 1) {
            return null;
        }

        $discountRaw = trim((string) $request->input('discount_percent', ''));
        $discountPercent = $this->parsePercent($discountRaw);

        $deliveryStart = trim((string) $request->input('delivery_start', ''));
        $deliveryEnd = trim((string) $request->input('delivery_end', ''));
        $penaltyTerms = trim((string) $request->input('penalty_terms', ''));

        if ($clientId <= 0 || $title === '') {
            return null;
        }

        $items = $this->itemsFromRequest($request);
        if (count($items) < 1) {
            return null;
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            if ((int) ($item['is_bonus'] ?? 0) === 1) {
                continue;
            }
            $subtotal += (float) $item['total'];
        }

        $selectedIndex = (int) $request->input('payment_selected_index', 0);
        if ($selectedIndex < 0) {
            $selectedIndex = 0;
        }
        if ($selectedIndex >= count($paymentOptions)) {
            $selectedIndex = 0;
        }

        $calculator = new ProposalCalculator();
        $optionsSnapshots = [];
        foreach ($paymentOptions as $opt) {
            $calc = $calculator->calculate($opt['rules'], $subtotal, $opt['discount_percent'], $deliveryStart !== '' ? $deliveryStart : null);
            $snapshot = (array) ($calc['snapshot'] ?? []);
            $snapshot['special_terms'] = (string) ($opt['special_terms'] ?? '');

            $optionsSnapshots[] = [
                'label' => (string) $opt['label'],
                'subtotal' => (float) $calc['subtotal'],
                'discount_percent' => (float) $calc['discount_percent'],
                'discount_amount' => (float) $calc['discount_amount'],
                'total' => (float) $calc['total'],
                'snapshot' => $snapshot,
            ];
        }

        $primary = $optionsSnapshots[$selectedIndex];
        $primarySnap = (array) ($primary['snapshot'] ?? []);

        $milestones = $this->milestonesFromRequest($request);

        $paymentSnapshot = $primarySnap;

        return [
            'client_id' => $clientId,
            'title' => $title,
            'description' => $description === '' ? null : $description,
            'notes' => $notes === '' ? null : $notes,
            'status' => 'rascunho',
            'subtotal' => (float) $primary['subtotal'],
            'discount_percent' => (float) $primary['discount_percent'],
            'discount_amount' => (float) $primary['discount_amount'],
            'total' => (float) $primary['total'],
            'payment_method_id' => isset($paymentSnapshot['method_id']) ? (int) $paymentSnapshot['method_id'] : null,
            'payment_snapshot' => json_encode($paymentSnapshot, JSON_UNESCAPED_UNICODE),
            'payment_options' => json_encode($optionsSnapshots, JSON_UNESCAPED_UNICODE),
            'payment_selected_index' => $selectedIndex,
            'delivery_start' => $this->normalizeDate($deliveryStart),
            'delivery_end' => $this->normalizeDate($deliveryEnd),
            'penalty_terms' => $penaltyTerms === '' ? null : $penaltyTerms,
            'terms' => $terms === '' ? null : $terms,
            'milestones' => $milestones,
            'items' => $items,
        ];
    }

    private function paymentOptionsFromRequest(Request $request, ?array $existingProposal): ?array
    {
        $methodIds = $request->input('payment_option_method_id', null);
        $labels = $request->input('payment_option_label', []);
        $discounts = $request->input('payment_option_discount_percent', []);
        $types = $request->input('payment_option_type', []);
        $installments = $request->input('payment_option_installments_count', []);
        $intervals = $request->input('payment_option_interval_days', []);
        $hasDowns = $request->input('payment_option_has_down_payment', []);
        $downPercents = $request->input('payment_option_down_payment_percent', []);
        $specials = $request->input('payment_option_special_terms', []);

        $existingOptions = [];
        if (is_array($existingProposal) && !empty($existingProposal['payment_options'])) {
            $decoded = json_decode((string) $existingProposal['payment_options'], true);
            if (is_array($decoded)) {
                $existingOptions = $decoded;
            }
        }

        $out = [];

        if (!is_array($methodIds)) {
            $single = $request->input('payment_method_id', null);
            if (!is_numeric($single)) {
                return null;
            }
            $methodIds = [(int) $single];
        }

        $count = min(3, count($methodIds));
        for ($i = 0; $i < $count; $i++) {
            $methodId = is_numeric($methodIds[$i] ?? null) ? (int) $methodIds[$i] : 0;
            if ($methodId <= 0) {
                continue;
            }

            $label = trim((string) ($labels[$i] ?? ''));
            $discountPercent = $this->parsePercent(trim((string) ($discounts[$i] ?? '')));
            $type = trim((string) ($types[$i] ?? ''));
            $installmentCount = (int) ($installments[$i] ?? 0);
            $intervalDays = (int) ($intervals[$i] ?? 0);
            $hasDown = (int) ($hasDowns[$i] ?? 0);
            $downPercent = (float) str_replace(',', '.', (string) ($downPercents[$i] ?? '0'));
            $special = trim((string) ($specials[$i] ?? ''));

            $rules = $this->resolveOptionRules($methodId, $existingOptions, $i);
            if ($rules === null) {
                return null;
            }

            if ($type === '' && isset($rules['type'])) {
                $type = (string) $rules['type'];
            }
            if (!in_array($type, ['avista', 'parcelado'], true)) {
                $type = (string) $rules['type'];
            }

            if ($installmentCount <= 0) {
                $installmentCount = (int) ($rules['installments_count'] ?? 1);
            }
            if ($intervalDays <= 0) {
                $intervalDays = (int) ($rules['interval_days'] ?? 30);
            }
            if (!in_array($hasDown, [0, 1], true)) {
                $hasDown = (int) ($rules['has_down_payment'] ?? 0);
            }
            if ($downPercent <= 0) {
                $downPercent = (float) ($rules['down_payment_percent'] ?? 0);
            }
            $downPercent = max(0.0, min(100.0, $downPercent));

            if ($type === 'avista') {
                $installmentCount = 1;
                $intervalDays = 30;
                $hasDown = 0;
                $downPercent = 0.0;
            }

            if ($label === '') {
                $label = (string) ($rules['name'] ?? 'Opção ' . ($i + 1));
            }

            $out[] = [
                'label' => $label,
                'discount_percent' => $discountPercent,
                'special_terms' => $special,
                'rules' => [
                    'id' => (int) ($rules['id'] ?? $methodId),
                    'name' => (string) ($rules['name'] ?? $label),
                    'type' => $type,
                    'installments_count' => $installmentCount,
                    'interval_days' => $intervalDays,
                    'has_down_payment' => $hasDown,
                    'down_payment_percent' => $downPercent,
                    'special_terms' => $special,
                ],
            ];
        }

        if (count($out) === 0) {
            return null;
        }

        return $out;
    }

    private function resolveOptionRules(int $paymentMethodId, array $existingOptions, int $index): ?array
    {
        $existing = $existingOptions[$index] ?? null;
        if (is_array($existing)) {
            $snap = $existing['snapshot'] ?? null;
            if (is_array($snap) && (int) ($snap['method_id'] ?? 0) === $paymentMethodId) {
                return [
                    'id' => (int) ($snap['method_id'] ?? $paymentMethodId),
                    'name' => (string) ($snap['method_name'] ?? ''),
                    'type' => (string) ($snap['type'] ?? 'avista'),
                    'installments_count' => (int) ($snap['installments_count'] ?? 1),
                    'interval_days' => (int) ($snap['interval_days'] ?? 30),
                    'has_down_payment' => (int) ($snap['has_down_payment'] ?? 0),
                    'down_payment_percent' => (float) ($snap['down_payment_percent'] ?? 0),
                    'special_terms' => (string) ($snap['special_terms'] ?? ''),
                ];
            }
        }

        $method = (new PaymentMethodRepository())->find($paymentMethodId);
        if ($method === null || (int) ($method['active'] ?? 0) !== 1) {
            return null;
        }

        return [
            'id' => (int) $method['id'],
            'name' => (string) $method['name'],
            'type' => (string) $method['type'],
            'installments_count' => (int) $method['installments_count'],
            'interval_days' => (int) $method['interval_days'],
            'has_down_payment' => (int) $method['has_down_payment'],
            'down_payment_percent' => (float) $method['down_payment_percent'],
            'special_terms' => (string) ($method['special_terms'] ?? ''),
        ];
    }

    private function resolvePaymentRules(int $paymentMethodId, ?array $existingProposal): ?array
    {
        if (is_array($existingProposal) && (int) ($existingProposal['payment_method_id'] ?? 0) === $paymentMethodId) {
            $snapRaw = (string) ($existingProposal['payment_snapshot'] ?? '');
            $decoded = $snapRaw !== '' ? json_decode($snapRaw, true) : null;
            if (is_array($decoded)) {
                $type = (string) ($decoded['type'] ?? '');
                $installments = (int) ($decoded['installments_count'] ?? 0);
                $interval = (int) ($decoded['interval_days'] ?? 0);
                $hasDown = (int) ($decoded['has_down_payment'] ?? 0);
                $down = (float) ($decoded['down_payment_percent'] ?? 0);
                if (in_array($type, ['avista', 'parcelado'], true) && $installments >= 1 && $interval >= 1) {
                    return [
                        'id' => (int) ($decoded['method_id'] ?? $paymentMethodId),
                        'name' => (string) ($decoded['method_name'] ?? ''),
                        'type' => $type,
                        'installments_count' => $installments,
                        'interval_days' => $interval,
                        'has_down_payment' => $hasDown,
                        'down_payment_percent' => $down,
                        'special_terms' => (string) ($decoded['special_terms'] ?? ''),
                    ];
                }
            }
        }

        $method = (new PaymentMethodRepository())->find($paymentMethodId);
        if ($method === null || (int) ($method['active'] ?? 0) !== 1) {
            return null;
        }

        return [
            'id' => (int) $method['id'],
            'name' => (string) $method['name'],
            'type' => (string) $method['type'],
            'installments_count' => (int) $method['installments_count'],
            'interval_days' => (int) $method['interval_days'],
            'has_down_payment' => (int) $method['has_down_payment'],
            'down_payment_percent' => (float) $method['down_payment_percent'],
            'special_terms' => (string) ($method['special_terms'] ?? ''),
        ];
    }

    private function parsePercent(string $raw): float
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0.0;
        }

        $n = (float) str_replace(',', '.', $raw);
        if (!is_finite($n)) {
            return 0.0;
        }
        $n = max(0.0, min(100.0, $n));
        if ($n === 0.0) {
            return 0.0;
        }
        return $n;
    }

    public function milestonesFromRequest(Request $request): array
    {
        $titles = $request->input('milestone_title', []);
        $dates = $request->input('milestone_due_date', []);
        $notes = $request->input('milestone_notes', []);
        $penalties = $request->input('milestone_penalty', []);

        if (!is_array($titles) || !is_array($dates) || !is_array($notes) || !is_array($penalties)) {
            return [];
        }

        $out = [];
        $count = max(count($titles), count($dates), count($notes), count($penalties));
        for ($i = 0; $i < $count; $i++) {
            $t = trim((string) ($titles[$i] ?? ''));
            $d = $this->normalizeDate((string) ($dates[$i] ?? ''));
            $n = trim((string) ($notes[$i] ?? ''));
            $p = trim((string) ($penalties[$i] ?? ''));
            if ($t === '') {
                continue;
            }
            $out[] = [
                'title' => $t,
                'due_date' => $d,
                'notes' => $n === '' ? null : $n,
                'penalty_terms' => $p === '' ? null : $p,
            ];
        }

        return $out;
    }

    private function normalizeDate(string $date): ?string
    {
        $date = trim($date);
        if ($date === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return null;
        }
        return $date;
    }

    public function convertToProject(int $proposalId, int $actorId): int
    {
        return (new ProjectAutomationService())->createFromApprovedProposal($proposalId, $actorId);
    }
}
