<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\FinancialReceivablePdfGenerator;
use App\Services\FinancialReceivablePdfValidator;

// Nome deliberadamente distinto de $failures: cada arquivo requerido abaixo também
// declara sua própria $failures = 0 e, como require compartilha escopo, essa variável
// aqui seria resetada a cada require, descartando o total acumulado — usar um nome
// próprio para o acumulador deste arquivo evita a colisão.
$totalFailures = 0;

$assert = static function (bool $ok, string $message) use (&$totalFailures): void {
    if ($ok) {
        echo "OK  - {$message}\n";
        return;
    }
    $totalFailures++;
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
$assert(str_contains($pdf, 'Receb'), 'PDF contém identificador do recebível');
$assert(str_contains($pdf, 'R$ 3.500,00'), 'PDF contém valor formatado em BRL');
$assert(str_contains($pdf, '+5567993256260'), 'PDF contém contato no rodapé');
$assert(str_contains($pdf, 'comercial@traxter.com.br'), 'PDF contém e-mail no rodapé');
$assert(!str_contains($pdf, 'â€¢'), 'PDF não contém caractere corrompido no rodapé');

$long = $baseReceivable;
$long['description'] = str_repeat('Descricao muito longa para testar quebra de linha e estabilidade do layout. ', 30);
$longPdf = $gen->build($branding, $long);
$assert(str_starts_with($longPdf, '%PDF-1.4'), 'Geração com descrição longa permanece válida');
$assert(str_contains($longPdf, 'Itens'), 'PDF com descrição longa mantém seção de itens');

$databaseFailures = require __DIR__ . '/database_structure.php';
$totalFailures += (int) $databaseFailures;

$leadFailures = require __DIR__ . '/leads_module.php';
$totalFailures += (int) $leadFailures;

$serviceOrderFailures = require __DIR__ . '/service_orders_module.php';
$totalFailures += (int) $serviceOrderFailures;

$serviceOrderReportFailures = require __DIR__ . '/service_orders_report_repository.php';
$totalFailures += (int) $serviceOrderReportFailures;

$serviceOrderListingSummaryFailures = require __DIR__ . '/service_orders_listing_summary.php';
$totalFailures += (int) $serviceOrderListingSummaryFailures;

$serviceOrderBillingFailures = require __DIR__ . '/service_order_billing_module.php';
$totalFailures += (int) $serviceOrderBillingFailures;

$serviceOrderLayoutFailures = require __DIR__ . '/service_order_layout_module.php';
$totalFailures += (int) $serviceOrderLayoutFailures;

$serviceOrderReparcelFailures = require __DIR__ . '/service_order_reparcel_module.php';
$totalFailures += (int) $serviceOrderReparcelFailures;

$serviceOrderValueSourceFailures = require __DIR__ . '/service_order_billing_value_source_module.php';
$totalFailures += (int) $serviceOrderValueSourceFailures;

$financeReportFailures = require __DIR__ . '/finance_report_repository.php';
$totalFailures += (int) $financeReportFailures;

$financeReportControllerFailures = require __DIR__ . '/finance_report_controller.php';
$totalFailures += (int) $financeReportControllerFailures;

$approvalFailures = require __DIR__ . '/service_order_approval_module.php';
$totalFailures += (int) $approvalFailures;

$productionErrorFailures = require __DIR__ . '/production_error_handling.php';
$totalFailures += (int) $productionErrorFailures;

$landingLoginFailures = require __DIR__ . '/landing_login_page.php';
$totalFailures += (int) $landingLoginFailures;

$pdfsAllFailures = require __DIR__ . '/pdfs_all.php';
$totalFailures += (int) $pdfsAllFailures;

exit($totalFailures > 0 ? 1 : 0);
