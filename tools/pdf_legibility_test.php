<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ProposalPdfGenerator;

function assertContains(string $haystack, string $needle, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        throw new RuntimeException($message);
    }
}

function pagesCount(string $pdf): int
{
    if (preg_match('~/Type /Pages\b[\s\S]*?/Count (\d+)~', $pdf, $m) === 1) {
        return (int) $m[1];
    }
    return substr_count($pdf, '/Type /Page');
}

$baseBranding = [
    'company_name' => 'TRAXTER.',
    'logo_path' => null,
    'primary_color' => '#293241',
    'accent_color' => '#fdf2f2',
];

$proposal = [
    'id' => 7,
    'client_name' => 'Cliente de Teste',
    'created_at' => '2026-04-27 10:00:00',
    'description' => "Linha 1 com acentos: Gestão, Organização e Currículos.\nLinha 2 com texto normal.",
    'notes' => '',
    'terms' => '',
    'subtotal' => 100.00,
    'discount_percent' => 0.0,
    'discount_amount' => 0.0,
    'total' => 100.00,
    'delivery_start' => '2026-04-27',
    'delivery_end' => '2026-05-10',
    'penalty_terms' => '',
];

$items = [
    ['description' => 'Serviço com descrição curta', 'qty' => 1, 'unit_price' => 100, 'total' => 100],
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

try {
    foreach ([0.90, 1.00, 1.15] as $scale) {
        $branding = $baseBranding;
        $branding['font_scale'] = $scale;
        $pdf = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, 0);

        assertContains($pdf, 'Campo Grande, MS - 27/04/2026', 'Texto padrão de rodapé não encontrado.');

        $headingRg = '0.067 0.094 0.153 rg';
        assertContains($pdf, $headingRg, 'Cor de heading esperada não foi aplicada.');
        assertContains($pdf, '(Proposta:) Tj', 'Label "Proposta" não encontrado (ou cor não foi resetada).');

        $dividerRG = '0.420 0.447 0.502 RG';
        assertContains($pdf, $dividerRG, 'Separadores/linhas não estão usando a cor acessível esperada.');

        $count = pagesCount($pdf);
        if ($count < 1) {
            throw new RuntimeException('PDF sem páginas detectáveis.');
        }
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
