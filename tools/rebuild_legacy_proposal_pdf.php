<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI apenas.\n";
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\ProposalBrandingRepository;
use App\Services\CompanyProfileService;
use App\Services\ProposalPdfGenerator;

$sourcePath = $argv[1] ?? '';
$targetPath = $argv[2] ?? '';

if ($sourcePath === '' || $targetPath === '') {
    fwrite(STDERR, "Uso: php tools/rebuild_legacy_proposal_pdf.php <origem.pdf> <destino.pdf>\n");
    exit(2);
}

$sourcePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourcePath);
$targetPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Arquivo de origem nao encontrado.\n");
    exit(3);
}

$raw = file_get_contents($sourcePath);
if (!is_string($raw) || $raw === '') {
    fwrite(STDERR, "Falha ao ler o PDF de origem.\n");
    exit(4);
}

preg_match_all('/BT \/F\d \d+ Tf(?: [0-9.]+ Tw)? \d+ \d+ Td \((.*?)\) Tj ET/s', $raw, $matches);
$lines = [];
foreach (($matches[1] ?? []) as $match) {
    $text = (string) $match;
    $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
    if (function_exists('iconv')) {
        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if ($text === '') {
        continue;
    }
    if (preg_match('/^Página \d+ de \d+ /u', $text) === 1) {
        continue;
    }
    if ($text === '30.358.115/0001-13') {
        continue;
    }
    if (preg_match('/^Campo Grande, MS - \d{2}\/\d{2}\/\d{4}$/u', $text) === 1) {
        continue;
    }
    if (preg_match('/^"?Proposta válida por 30 dias\.?"?$/u', $text) === 1) {
        continue;
    }
    if ($text === 'Assinatura do cliente') {
        continue;
    }
    if ($text === 'TRAXTER. Automações e Sistemas') {
        continue;
    }
    $lines[] = $text;
}

$lines = array_values($lines);

$findAfter = static function (array $list, string $needle): string {
    $index = array_search($needle, $list, true);
    if ($index === false) {
        return '';
    }
    return trim((string) ($list[$index + 1] ?? ''));
};

$findSection = static function (array $list, string $start, ?string $end = null): array {
    $startIndex = array_search($start, $list, true);
    if ($startIndex === false) {
        return [];
    }
    $from = $startIndex + 1;
    $to = count($list);
    if ($end !== null) {
        $endIndex = array_search($end, $list, true);
        if ($endIndex !== false && $endIndex > $from) {
            $to = $endIndex;
        }
    }
    return array_slice($list, $from, $to - $from);
};

$proposalId = 0;
if (preg_match('/Proposta #(\d+)/', implode("\n", $lines), $m) === 1) {
    $proposalId = (int) $m[1];
}
if ($proposalId <= 0) {
    $proposalId = (int) preg_replace('/\D+/', '', $findAfter($lines, 'Proposta:'));
}

$issueDateBr = '';
foreach ($lines as $line) {
    if (preg_match('/^Data:\s*(\d{2}\/\d{2}\/\d{4})$/', $line, $m) === 1) {
        $issueDateBr = $m[1];
        break;
    }
}

$issueDateSql = null;
if ($issueDateBr !== '') {
    [$d, $m, $y] = explode('/', $issueDateBr);
    $issueDateSql = sprintf('%04d-%02d-%02d 00:00:00', (int) $y, (int) $m, (int) $d);
}

$clientName = $findAfter($lines, 'Cliente:');

$descriptionLines = $findSection($lines, 'Descrição do projeto', 'Serviços');
$servicesLines = $findSection($lines, 'Serviços', 'Resumo financeiro');
$financialLines = $findSection($lines, 'Resumo financeiro', 'Prazos de entrega');
$deliveryLines = $findSection($lines, 'Prazos de entrega', 'Termos e condições');
$termsLines = $findSection($lines, 'Termos e condições', null);

$moneyToFloat = static function (string $value): float {
    $clean = preg_replace('/[^\d,.-]/', '', $value);
    $clean = str_replace('.', '', (string) $clean);
    $clean = str_replace(',', '.', (string) $clean);
    return (float) $clean;
};

$extractMoneyLine = static function (array $list, string $prefix) use ($moneyToFloat): float {
    foreach ($list as $line) {
        if (stripos($line, $prefix) === 0) {
            return $moneyToFloat($line);
        }
    }
    return 0.0;
};

$subtotal = $extractMoneyLine($financialLines, 'Subtotal:');
$discountAmount = $extractMoneyLine($financialLines, 'Desconto');
$total = $extractMoneyLine($financialLines, 'Total:');
$discountPercent = 0.0;
foreach ($financialLines as $line) {
    if (preg_match('/Desconto \(([\d.,]+)%\)/', $line, $m) === 1) {
        $discountPercent = (float) str_replace(',', '.', $m[1]);
        break;
    }
}

$itemsDescription = [];
$qty = 1.0;
$unitPrice = $total;
$itemTotal = $total;
foreach ($servicesLines as $line) {
    if (in_array($line, ['Descrição', 'Qtd', 'Valor', 'Total'], true)) {
        continue;
    }
    if (preg_match('/^\d+(?:[.,]\d+)?$/', $line) === 1) {
        $qty = (float) str_replace(',', '.', $line);
        continue;
    }
    if (preg_match('/^R\$\s*[\d.]+,\d{2}$/u', $line) === 1) {
        if ($unitPrice === $total) {
            $unitPrice = $moneyToFloat($line);
            $itemTotal = $unitPrice;
        } else {
            $itemTotal = $moneyToFloat($line);
        }
        continue;
    }
    $itemsDescription[] = $line;
}
if ($itemTotal <= 0.0) {
    $itemTotal = $total;
}
if ($unitPrice <= 0.0) {
    $unitPrice = $qty > 0 ? ($itemTotal / $qty) : $itemTotal;
}

$paymentOptions = [];
$currentPayment = null;
foreach ($financialLines as $line) {
    if (preg_match('/^Forma de pagamento\s+\d+/u', $line) === 1) {
        if (is_array($currentPayment)) {
            $paymentOptions[] = $currentPayment;
        }
        $currentPayment = [
            'label' => '',
            'total' => $total,
            'snapshot' => [
                'schedule' => [],
                'special_terms' => '',
            ],
        ];
        continue;
    }
    if (!is_array($currentPayment)) {
        continue;
    }
    if ($currentPayment['label'] === '' && !str_starts_with($line, 'Valor:')) {
        $currentPayment['label'] = $line;
        continue;
    }
    if (str_starts_with($line, 'Valor:')) {
        $currentPayment['total'] = $moneyToFloat($line);
        continue;
    }
    if (preg_match('/^(Entrada|Parcela\s+\d+|À vista) \((\d{2}\/\d{2}\/\d{4})\): (R\$ .+)$/u', $line, $m) === 1) {
        $kindRaw = $m[1];
        $kind = str_starts_with($kindRaw, 'Entrada') ? 'entrada' : (str_starts_with($kindRaw, 'À vista') ? 'avista' : 'parcela');
        $number = $kind === 'parcela' ? (int) preg_replace('/\D+/', '', $kindRaw) : 0;
        [$d, $mo, $y] = explode('/', $m[2]);
        $currentPayment['snapshot']['schedule'][] = [
            'kind' => $kind,
            'no' => $number,
            'due_date' => sprintf('%04d-%02d-%02d', (int) $y, (int) $mo, (int) $d),
            'amount' => $moneyToFloat($m[3]),
        ];
    }
}
if (is_array($currentPayment)) {
    $paymentOptions[] = $currentPayment;
}

$deliveryStart = null;
$deliveryEnd = null;
$milestones = [];
foreach ($deliveryLines as $line) {
    if (preg_match('/^Início estimado:\s*(\d{2}\/\d{2}\/\d{4})$/u', $line, $m) === 1) {
        [$d, $mo, $y] = explode('/', $m[1]);
        $deliveryStart = sprintf('%04d-%02d-%02d', (int) $y, (int) $mo, (int) $d);
        continue;
    }
    if (preg_match('/^Término estimado:\s*(\d{2}\/\d{2}\/\d{4})$/u', $line, $m) === 1) {
        [$d, $mo, $y] = explode('/', $m[1]);
        $deliveryEnd = sprintf('%04d-%02d-%02d', (int) $y, (int) $mo, (int) $d);
        continue;
    }
    if (preg_match('/^Marco:\s*(.+)\s+\((\d{2}\/\d{2}\/\d{4})\)$/u', $line, $m) === 1) {
        [$d, $mo, $y] = explode('/', $m[2]);
        $milestones[] = [
            'title' => trim($m[1]),
            'due_date' => sprintf('%04d-%02d-%02d', (int) $y, (int) $mo, (int) $d),
            'notes' => null,
            'penalty_terms' => null,
        ];
    }
}

$branding = [
    'company_name' => 'TRAXTER CRM',
    'primary_color' => '#293241',
    'accent_color' => '#ee6c4d',
    'logo_path' => '',
    'logo_light_path' => '',
    'logo_dark_path' => '',
    'company_cnpj' => '30358115000113',
];

try {
    $brandingDb = (new ProposalBrandingRepository())->get();
    if (is_array($brandingDb)) {
        $branding = array_merge($branding, $brandingDb);
    }
    $company = (new CompanyProfileService())->getCached();
    if (is_array($company)) {
        $branding['company_cnpj'] = (string) ($company['cnpj'] ?? $branding['company_cnpj']);
        $logoPath = (string) ($branding['logo_path'] ?? '');
        if ($logoPath === '' || !is_file($logoPath)) {
            $fallbackLogo = (string) ($company['logo_light_path'] ?? '');
            if ($fallbackLogo !== '' && is_file($fallbackLogo)) {
                $branding['logo_path'] = $fallbackLogo;
            }
        }
    }
} catch (\Throwable) {
}

$proposal = [
    'id' => $proposalId > 0 ? $proposalId : 1,
    'client_name' => $clientName !== '' ? $clientName : 'Cliente não informado',
    'description' => implode("\n", $descriptionLines),
    'subtotal' => $subtotal,
    'discount_percent' => $discountPercent,
    'discount_amount' => $discountAmount,
    'total' => $total,
    'created_at' => $issueDateSql ?? date('Y-m-d 00:00:00'),
    'payment_snapshot' => '',
    'delivery_start' => $deliveryStart,
    'delivery_end' => $deliveryEnd,
    'terms' => implode("\n", $termsLines),
    'notes' => '',
    'penalty_terms' => null,
];

$items = [[
    'description' => trim(implode(' ', $itemsDescription)),
    'qty' => $qty,
    'unit_price' => $unitPrice,
    'total' => $itemTotal,
    'is_bonus' => 0,
]];

$bytes = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, 0);

$targetDir = dirname($targetPath);
if (!is_dir($targetDir)) {
    @mkdir($targetDir, 0755, true);
}
if (!is_dir($targetDir)) {
    fwrite(STDERR, "Falha ao preparar o diretório de destino.\n");
    exit(5);
}

if (@file_put_contents($targetPath, $bytes) === false) {
    fwrite(STDERR, "Falha ao gravar o PDF reconstruido.\n");
    exit(6);
}

echo json_encode([
    'source' => $sourcePath,
    'target' => $targetPath,
    'proposal_id' => $proposal['id'],
    'client_name' => $proposal['client_name'],
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
