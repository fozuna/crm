<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ContractTemplateEngine;

final class Assert
{
    public static function ok(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

$engine = new ContractTemplateEngine();
$template = [
    'header_title' => 'Contrato {{contract_number}}',
    'body_template' => "Cliente: {{client_name}}\nEmpresa: {{client_company}}\nValor: {{proposal_total}}\nServicos:\n{{services_summary}}\nPagamento:\n{{payment_schedule}}",
    'footer_notes' => 'Emitido em {{current_date}}',
];

$rendered = $engine->render($template, [
    'proposal' => [
        'id' => 99,
        'title' => 'Portal Corporativo',
        'total' => 12500.50,
        'terms' => 'Pagamento conforme cronograma.',
        'notes' => '',
        'delivery_start' => '2026-05-15',
        'delivery_end' => '2026-07-30',
    ],
    'client' => [
        'name' => 'Cliente Exemplo',
        'company' => 'Cliente Exemplo LTDA',
        'email' => 'financeiro@cliente.com.br',
        'phone' => '(67) 99999-9999',
    ],
    'company' => [
        'legal_name' => 'Traxter Tecnologia LTDA',
        'trade_name' => 'TRAXTER',
        'cnpj' => '04252011000110',
        'email' => 'contato@traxter.com.br',
        'website' => 'https://traxter.com.br',
    ],
    'items' => [
        ['description' => 'Desenvolvimento completo', 'qty' => 1, 'total' => 12000],
        ['description' => 'Suporte inicial', 'qty' => 1, 'total' => 500.50],
    ],
    'milestones' => [],
    'payment_schedule' => [
        ['kind' => 'entrada', 'no' => 1, 'due_date' => '2026-05-20', 'amount' => 3000],
        ['kind' => 'parcela', 'no' => 2, 'due_date' => '2026-06-20', 'amount' => 9500.50],
    ],
    'contract_number' => 'CTR-000099',
    'signature_mode_label' => 'Assinatura digital',
]);

Assert::ok(str_contains((string) $rendered['title'], 'CTR-000099'), 'Titulo deve interpolar o numero do contrato.');
Assert::ok(str_contains((string) $rendered['body'], 'Cliente Exemplo'), 'Corpo deve interpolar cliente.');
Assert::ok(str_contains((string) $rendered['body'], 'R$ 12.500,50'), 'Corpo deve interpolar valor formatado.');
Assert::ok(str_contains((string) $rendered['body'], 'Desenvolvimento completo'), 'Corpo deve listar servicos.');
Assert::ok(str_contains((string) $rendered['body'], 'Entrada'), 'Corpo deve listar cronograma de pagamento.');
Assert::ok(str_contains((string) $rendered['footer'], date('d/m/Y')), 'Rodape deve interpolar data atual.');

echo "OK\n";
