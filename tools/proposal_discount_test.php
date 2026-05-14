<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Request;
use App\Services\ProposalService;

final class Assert
{
    public static function eq(mixed $a, mixed $b, string $msg): void
    {
        if ($a !== $b) {
            throw new RuntimeException($msg . ' (got ' . var_export($a, true) . ', expected ' . var_export($b, true) . ')');
        }
    }

    public static function near(float $a, float $b, float $eps, string $msg): void
    {
        if (abs($a - $b) > $eps) {
            throw new RuntimeException($msg . ' (got ' . $a . ', expected ' . $b . ')');
        }
    }
}

$existing = [
    'payment_method_id' => 10,
    'payment_snapshot' => json_encode([
        'method_id' => 10,
        'method_name' => 'PIX',
        'type' => 'avista',
        'installments_count' => 1,
        'interval_days' => 30,
        'has_down_payment' => 0,
        'down_payment_percent' => 0,
        'special_terms' => 'snapshot terms',
    ], JSON_UNESCAPED_UNICODE),
    'payment_options' => json_encode([
        [
            'label' => 'PIX',
            'discount_percent' => 0,
            'discount_amount' => 0,
            'total' => 0,
            'snapshot' => [
                'method_id' => 10,
                'method_name' => 'PIX',
                'type' => 'avista',
                'installments_count' => 1,
                'interval_days' => 30,
                'has_down_payment' => 0,
                'down_payment_percent' => 0,
                'special_terms' => 'snapshot terms',
                'schedule' => [],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE),
];

$_POST = [
    'client_id' => '1',
    'title' => 'Teste',
    'description' => 'Desc',
    'notes' => '',
    'terms' => '',
    'payment_selected_index' => '0',
    'payment_option_method_id' => ['10'],
    'payment_option_label' => ['PIX'],
    'payment_option_discount_percent' => ['0'],
    'payment_option_type' => ['avista'],
    'payment_option_installments_count' => ['1'],
    'payment_option_interval_days' => ['30'],
    'payment_option_has_down_payment' => ['0'],
    'payment_option_down_payment_percent' => ['0'],
    'payment_option_special_terms' => [''],
    'delivery_start' => '2026-04-27',
    'delivery_end' => '',
    'penalty_terms' => '',
    'item_description' => ['Serviço'],
    'item_qty' => ['1'],
    'item_unit_price' => ['890'],
    'milestone_title' => [''],
    'milestone_due_date' => [''],
    'milestone_notes' => [''],
    'milestone_penalty' => [''],
];

$request = new Request();
$service = new ProposalService();
$payload = $service->validatePayload($request, $existing);
if ($payload === null) {
    throw new RuntimeException('Payload deveria ser válido (cenário desconto 0).');
}

Assert::near((float) $payload['subtotal'], 890.0, 0.01, 'Subtotal incorreto');
Assert::near((float) $payload['discount_amount'], 0.0, 0.01, 'Desconto deve ser 0 quando campo é 0');
Assert::near((float) $payload['total'], 890.0, 0.01, 'Total deve ser igual ao subtotal quando desconto é 0');

$_POST['payment_option_discount_percent'] = [''];
$payload2 = $service->validatePayload(new Request(), $existing);
if ($payload2 === null) {
    throw new RuntimeException('Payload deveria ser válido (cenário desconto vazio).');
}

Assert::near((float) $payload2['discount_amount'], 0.0, 0.01, 'Desconto deve ser 0 quando campo está vazio');
Assert::near((float) $payload2['total'], 890.0, 0.01, 'Total deve ser igual ao subtotal quando desconto está vazio');

$_POST['payment_option_discount_percent'] = ['10'];
$payload3 = $service->validatePayload(new Request(), $existing);
if ($payload3 === null) {
    throw new RuntimeException('Payload deveria ser válido (cenário desconto 10).');
}

Assert::near((float) $payload3['discount_amount'], 89.0, 0.01, 'Desconto deve ser 89 quando é 10%');
Assert::near((float) $payload3['total'], 801.0, 0.01, 'Total deve ser 801 quando desconto é 10%');

echo "OK\n";
