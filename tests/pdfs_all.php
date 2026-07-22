<?php
declare(strict_types=1);

if (!class_exists(\App\Core\Config::class, false)) {
    require __DIR__ . '/../app/bootstrap.php';
}

use App\Services\ContractPdfGenerator;
use App\Services\FinancialReceiptPdfGenerator;
use App\Services\FinancialReceivablePdfGenerator;
use App\Services\PdfStandardTheme;
use App\Services\ProposalPdfGenerator;
use App\Services\ServiceOrderPdfGenerator;

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
    'logo_light_path' => '',
    'logo_dark_path' => '',
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
$assert(str_contains($pdfContract, 'Contrato'), 'Contrato: contém título principal');

$serviceOrder = [
    'numero_os' => 'OS-000001',
    'service_name' => 'Ajuste de processo',
    'client_name' => 'Cliente Teste LTDA',
    'client_company' => 'Cliente Teste LTDA',
    'contact_name' => 'Fabio',
    'assigned_user_name' => 'Equipe Técnica',
    'type' => 'suporte',
    'status' => 'aberto',
    'opened_at' => '2026-05-01 08:00:00',
    'due_at' => '2026-05-03 18:00:00',
    'completed_at' => '',
    'estimated_hours' => 2.5,
    'executed_hours' => 1.75,
    'request_description' => '<p>Solicitação do cliente.</p>',
    'executed_activities' => '<p>Atividade executada.</p>',
    'technical_notes' => '<p>Observação técnica.</p>',
    'billable' => 1,
    'base_service_name' => 'Consultoria',
    'base_amount' => 500.00,
    'discount_amount' => 0.00,
    'surcharge_amount' => 50.00,
    'final_amount' => 550.00,
    'financial_receivable_id' => 15,
];
$attachments = [
    ['original_name' => 'anexo.pdf', 'file_extension' => 'pdf', 'file_size' => 10240, 'uploaded_by_name' => 'Equipe Técnica'],
];
$history = [
    ['created_at' => '2026-05-01 08:00:00', 'message' => 'OS criada.', 'actor_name' => 'Equipe Técnica'],
];
$pdfOs = (new ServiceOrderPdfGenerator())->build($branding, $serviceOrder, $attachments, $history);
$assert(str_contains($pdfOs, $contactPhone), 'OS: rodapé contém telefone');
$assert(str_contains($pdfOs, $contactEmail), 'OS: rodapé contém e-mail');
$assert(str_contains($pdfOs, 'OS-000001'), 'OS: contém número da ordem');
$assert(str_contains($pdfOs, 'Hist'), 'OS: contém seção de histórico');

$createPixel = static function (string $path): void {
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO0pP1QAAAAASUVORK5CYII=');
    file_put_contents($path, $bytes);
};
$lightLogo = tempnam(sys_get_temp_dir(), 'pdf_light_') . '.png';
$darkLogo = tempnam(sys_get_temp_dir(), 'pdf_dark_') . '.png';
@unlink(substr($lightLogo, 0, -4));
@unlink(substr($darkLogo, 0, -4));
$createPixel($lightLogo);
$createPixel($darkLogo);

$themeReflection = new ReflectionClass(PdfStandardTheme::class);
$resolveLogo = $themeReflection->getMethod('resolveHeaderLogoPath');
$resolveLogo->setAccessible(true);
$selectedLogo = $resolveLogo->invoke(null, [
    'logo_path' => '',
    'logo_light_path' => $lightLogo,
    'logo_dark_path' => $darkLogo,
]);
$assert($selectedLogo === $darkLogo, 'Tema PDF: cabeçalho (sempre fundo claro) usa o logo escuro automaticamente');

$fallbackLogo = $resolveLogo->invoke(null, [
    'logo_path' => '',
    'logo_light_path' => $lightLogo,
    'logo_dark_path' => '',
]);
$assert($fallbackLogo === $lightLogo, 'Tema PDF: sem logo escuro cadastrado, cai para o logo claro disponível');

@unlink($lightLogo);
@unlink($darkLogo);

return $failures;
