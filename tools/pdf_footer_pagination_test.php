<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ProposalPdfGenerator;

$branding = [
    'company_name' => 'TRAXTER.',
    'logo_path' => null,
    'primary_color' => '#293241',
    'accent_color' => '#ee6c4d',
    'font_name' => 'Helvetica',
];

$long = str_repeat("Linha longa com acentos: Gestão, Organização, Currículos, captação, formulário, Área, análise, visualização.\n", 90);

$proposal = [
    'id' => 2,
    'title' => 'Teste paginação',
    'client_name' => 'Cliente de Teste',
    'created_at' => '2026-04-27 10:00:00',
    'description' => $long,
    'notes' => $long,
    'terms' => $long,
    'subtotal' => 100.00,
    'discount_percent' => 0.0,
    'discount_amount' => 0.0,
    'total' => 100.00,
    'delivery_start' => '2026-04-27',
    'delivery_end' => '2026-05-10',
    'penalty_terms' => $long,
];

$items = [
    ['description' => 'Serviço', 'qty' => 1, 'unit_price' => 100, 'total' => 100],
];

$milestones = [];

$paymentOptions = [[
    'label' => 'PIX',
    'total' => 100.00,
    'snapshot' => [
        'method_id' => 1,
        'method_name' => 'PIX',
        'type' => 'avista',
        'installments_count' => 1,
        'interval_days' => 30,
        'has_down_payment' => 0,
        'down_payment_percent' => 0,
        'special_terms' => '',
        'schedule' => [
            ['no' => 1, 'kind' => 'avista', 'due_date' => '2026-04-27', 'amount' => 100.00],
        ],
    ],
]];

$pdf = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, 0);

try {
    if (strpos($pdf, 'Campo Grande, MS - 27/04/2026') === false) {
        throw new RuntimeException('Texto padrão de rodapé não encontrado.');
    }

    $validNeedle = 'Proposta v' . chr(0xE1) . 'lida por 30 dias';
    if (strpos($pdf, $validNeedle) === false) {
        throw new RuntimeException('Mensagem de validade não encontrada.');
    }

    $companyNeedle = 'TRAXTER. Automa' . chr(0xE7) . chr(0xF5) . 'es e Sistemas';
    if (strpos($pdf, $companyNeedle) === false) {
        throw new RuntimeException('Texto de assinatura da empresa não encontrado.');
    }
    if (strpos($pdf, '30.358.115/0001-13') === false) {
        throw new RuntimeException('CNPJ da empresa não encontrado na assinatura.');
    }

    if (substr_count($pdf, '/Type /Page') < 2) {
        throw new RuntimeException('Esperado pelo menos 2 páginas para validar quebra automática.');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
