<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\FinancialReceivablePdfGenerator;
use App\Services\FinancialReceivablePdfValidator;

$failures = 0;

$assert = static function (bool $ok, string $message) use (&$failures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL- {$message}\n";
};

$branding = [
    'company_name' => 'TRAXTER',
    'primary_color' => '#293241',
    'accent_color' => '#ee6c4d',
    'logo_path' => '',
    'company_cnpj' => '30358115000113',
    'company_email' => 'financeiro@traxter.com.br',
    'company_whatsapp' => '(65) 99999-9999',
    'company_website' => 'https://traxter.com.br',
];

$baseReceivable = [
    'id' => 1,
    'title' => 'Mensalidade de suporte',
    'description' => 'Plano mensal de suporte e manutencao.',
    'client_name' => 'Cliente Teste',
    'client_company' => 'Cliente Teste LTDA',
    'project_title' => 'Projeto X',
    'status' => 'pending',
    'issue_date' => '2026-05-01',
    'due_date' => '2026-05-20',
    'installment_number' => 1,
    'total_installments' => 1,
    'original_amount' => 3500.00,
    'discount_amount' => 0.00,
    'interest_amount' => 0.00,
    'fine_amount' => 0.00,
    'received_amount' => 0.00,
    'remaining_amount' => 3500.00,
    'payment_method' => 'PIX',
    'payment_channel' => 'Banco',
    'bank_name' => 'Banco Teste',
    'account_name' => 'Conta Principal',
    'branch_number' => '0001',
    'account_number' => '12345-6',
    'pix_key' => 'financeiro@traxter.com.br',
    'invoice_number' => 'NF-001',
    'external_reference' => 'REF-ABC',
    'notes' => 'Pagamento via PIX ate a data de vencimento.',
];

$validator = new FinancialReceivablePdfValidator();
$assert($validator->validate($baseReceivable) === [], 'Validação aceita recebível completo');
$assert($validator->validate(array_merge($baseReceivable, ['due_date' => ''])) !== [], 'Validação bloqueia vencimento ausente');

$gen = new FinancialReceivablePdfGenerator();
$pdf = $gen->build($branding, $baseReceivable);
$assert(str_starts_with($pdf, '%PDF-1.4'), 'Geração retorna bytes PDF');
$assert(str_contains($pdf, 'Conta a Receber'), 'PDF contém título principal');
$assert(str_contains($pdf, 'Recebivel:'), 'PDF contém identificador do recebível');
$assert(str_contains($pdf, 'R$ 3.500,00'), 'PDF contém valor formatado em BRL');
$assert(str_contains($pdf, 'Pagina 1 de'), 'PDF contém paginação');

$long = $baseReceivable;
$long['description'] = str_repeat('Descricao muito longa para testar quebra de linha e estabilidade do layout. ', 30);
$longPdf = $gen->build($branding, $long);
$assert(str_starts_with($longPdf, '%PDF-1.4'), 'Geração com descrição longa permanece válida');
$assert(str_contains($longPdf, 'Servicos / Itens'), 'PDF com descrição longa mantém seção de itens');

exit($failures > 0 ? 1 : 0);

