<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ContractPdfGenerator;
use App\Services\FinancialReceiptPdfGenerator;
use App\Services\FinancialReceivablePdfGenerator;
use App\Services\ProposalPdfGenerator;

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
];

$contactPhone = '+5567993256260';
$contactEmail = 'comercial@traxter.com.br';

$receivable = [
    'id' => 10,
    'title' => 'Mensalidade',
    'description' => 'Plano mensal.',
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
    'pix_key' => 'chave-pix',
    'invoice_number' => 'NF-001',
    'external_reference' => 'REF-ABC',
    'notes' => '',
    'category_name' => 'Mensalidades',
    'cost_center_name' => 'Operacional',
    'contract_id' => 0,
    'competence_date' => '2026-05-01',
];

$pdfReceivable = (new FinancialReceivablePdfGenerator())->build($branding, $receivable);
$assert(str_contains($pdfReceivable, $contactPhone), 'Recebível: rodapé contém telefone');
$assert(str_contains($pdfReceivable, $contactEmail), 'Recebível: rodapé contém e-mail');

$receipt = [
    'id' => 3,
    'payment_date' => '2026-05-10 10:00:00',
    'amount_received' => 100.00,
    'interest_amount' => 0.00,
    'fine_amount' => 0.00,
    'discount_amount' => 0.00,
    'payment_method' => 'PIX',
    'transaction_reference' => 'TRX',
    'observation' => '',
];

$brandingReceipt = $branding + [
    'logo_mime' => '',
];

$pdfReceipt = (new FinancialReceiptPdfGenerator())->build($brandingReceipt, $receivable, $receipt);
$assert(str_contains($pdfReceipt, $contactPhone), 'Recibo: rodapé contém telefone');
$assert(str_contains($pdfReceipt, $contactEmail), 'Recibo: rodapé contém e-mail');

$proposal = [
    'id' => 7,
    'client_name' => 'Cliente Teste LTDA',
    'description' => 'Descricao do projeto',
    'subtotal' => 100.00,
    'discount_percent' => 0.0,
    'discount_amount' => 0.0,
    'total' => 100.00,
    'created_at' => '2026-05-01 10:00:00',
    'payment_snapshot' => '',
    'delivery_start' => '2026-05-01',
    'delivery_end' => '2026-05-20',
    'terms' => 'Termos',
    'notes' => '',
];

$items = [
    ['description' => 'Servico 1', 'qty' => 1, 'unit_price' => 100.0, 'total' => 100.0, 'is_bonus' => 0],
];
$milestones = [];
$paymentOptions = [];

$pdfProposal = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, 0);
$assert(str_contains($pdfProposal, $contactPhone), 'Proposta: rodapé contém telefone');
$assert(str_contains($pdfProposal, $contactEmail), 'Proposta: rodapé contém e-mail');

$contract = [
    'title' => 'Contrato de Prestacao de Servicos',
    'contract_number' => 'CT-001',
    'current_version' => 1,
];
$body = str_repeat('Texto do contrato para testar paginação e consistência de layout. ', 120);
$footer = '';
$pdfContract = (new ContractPdfGenerator())->build($branding, $contract, $body, $footer);
$assert(str_contains($pdfContract, $contactPhone), 'Contrato: rodapé contém telefone');
$assert(str_contains($pdfContract, $contactEmail), 'Contrato: rodapé contém e-mail');

exit($failures > 0 ? 1 : 0);

