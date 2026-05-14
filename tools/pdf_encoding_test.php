<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

set_exception_handler(static function (Throwable $e): void {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
});

use App\Services\ProposalPdfGenerator;

$branding = [
    'company_name' => 'TRAXTER.',
    'logo_path' => null,
    'primary_color' => '#293241',
    'accent_color' => '#ee6c4d',
    'font_name' => 'Helvetica',
];

$proposal = [
    'id' => 1,
    'title' => 'Sistema de Gestão de Currículos',
    'client_name' => 'Organização Contábil',
    'description' => "Descrição com acentos: á é í ó ú ã õ ç Á É Í Ó Ú Ã Õ Ç\nLinha 2 com quebra preservada.",
    'notes' => null,
    'terms' => 'Termos padrão.',
    'subtotal' => 890.00,
    'discount_percent' => 0.0,
    'discount_amount' => 0.0,
    'total' => 890.00,
    'delivery_start' => '2026-04-27',
    'delivery_end' => '2026-05-10',
    'penalty_terms' => 'Multa de 2% + 1% ao mês.',
];

$cp1252Line = 'CP1252: Gest' . chr(0xE3) . 'o / Organiza' . chr(0xE7) . chr(0xE3) . 'o';
$proposal['description'] .= "\n" . $cp1252Line;

$items = [
    ['description' => 'Serviço com acento: Remodelação do site', 'qty' => 1, 'unit_price' => 890, 'total' => 890],
];

$milestones = [
    ['title' => 'Início', 'due_date' => '2026-04-27'],
    ['title' => 'Entrega', 'due_date' => '2026-05-10'],
];

$paymentOptions = [
    [
        'label' => 'PIX à vista',
        'total' => 890.00,
        'snapshot' => [
            'method_id' => 1,
            'method_name' => 'PIX',
            'type' => 'avista',
            'installments_count' => 1,
            'interval_days' => 30,
            'has_down_payment' => 0,
            'down_payment_percent' => 0,
            'special_terms' => "Condição especial: emissão imediata.",
            'schedule' => [
                ['no' => 1, 'kind' => 'avista', 'due_date' => '2026-04-27', 'amount' => 890.00],
            ],
        ],
    ],
    [
        'label' => 'Boleto 3x',
        'total' => 890.00,
        'snapshot' => [
            'method_id' => 2,
            'method_name' => 'Boleto',
            'type' => 'parcelado',
            'installments_count' => 3,
            'interval_days' => 30,
            'has_down_payment' => 0,
            'down_payment_percent' => 0,
            'special_terms' => "Condição especial: sem juros.",
            'schedule' => [
                ['no' => 1, 'kind' => 'parcela', 'due_date' => '2026-04-27', 'amount' => 296.67],
                ['no' => 2, 'kind' => 'parcela', 'due_date' => '2026-05-27', 'amount' => 296.66],
                ['no' => 3, 'kind' => 'parcela', 'due_date' => '2026-06-26', 'amount' => 296.67],
            ],
        ],
    ],
];

$pdf = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, 0);

if (!is_string($pdf) || $pdf === '' || strncmp($pdf, "%PDF", 4) !== 0) {
    throw new RuntimeException('PDF inválido.');
}

if (strpos($pdf, '/WinAnsiEncoding') === false) {
    throw new RuntimeException('Fonte sem /WinAnsiEncoding; acentuação pode quebrar em leitores de PDF.');
}

$needle = 'Gest' . chr(0xE3) . 'o';
if (strpos($pdf, $needle) === false) {
    throw new RuntimeException('Acentos não encontrados no PDF (Windows-1252 esperado).');
}

if (strpos($pdf, 'CP1252:') === false) {
    throw new RuntimeException('Linha CP1252 não apareceu no PDF.');
}

if (strpos($pdf, 'Linha 2 com quebra preservada.') === false) {
    throw new RuntimeException('Quebra de linha não preservada (Linha 2 ausente).');
}

echo "OK\n";
