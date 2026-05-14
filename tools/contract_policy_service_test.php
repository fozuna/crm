<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ContractPolicyService;

final class Assert
{
    public static function ok(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public static function eq(mixed $actual, mixed $expected, string $message): void
    {
        if ($actual !== $expected) {
            throw new RuntimeException($message . ' (got ' . var_export($actual, true) . ', expected ' . var_export($expected, true) . ')');
        }
    }
}

$service = new ContractPolicyService();

$template = [
    'id' => 1,
    'name' => 'Padrao',
    'signature_mode_default' => 'digital',
    'require_signature_default' => 1,
    'auto_criteria_json' => json_encode([
        'enabled' => true,
        'min_total' => 5000,
        'required_client_ids' => [9],
        'required_service_ids' => [7],
        'service_keywords' => ['mensalidade', 'implantacao'],
    ], JSON_UNESCAPED_UNICODE),
];

$proposal = ['client_id' => 5, 'total' => 7600];
$items = [['service_id' => 2, 'description' => 'Implantacao do portal']];
$client = ['name' => 'Cliente Teste'];

$result = $service->evaluate($template, $proposal, $items, $client);
Assert::ok((bool) $result['eligible'], 'Proposta acima do valor minimo deveria exigir contrato.');
Assert::ok(in_array('valor_minimo', (array) $result['matched_by'], true), 'Criterio de valor minimo deveria aparecer.');

$proposalByClient = ['client_id' => 9, 'total' => 1000];
$resultByClient = $service->evaluate($template, $proposalByClient, [['service_id' => 1, 'description' => 'Servico avulso']], $client);
Assert::ok((bool) $resultByClient['eligible'], 'Cliente configurado explicitamente deveria exigir contrato.');
Assert::ok(in_array('cliente', (array) $resultByClient['matched_by'], true), 'Criterio de cliente deveria aparecer.');

$proposalByService = ['client_id' => 1, 'total' => 1000];
$resultByService = $service->evaluate($template, $proposalByService, [['service_id' => 7, 'description' => 'Servico avulso']], $client);
Assert::ok((bool) $resultByService['eligible'], 'Servico configurado explicitamente deveria exigir contrato.');
Assert::ok(in_array('servico', (array) $resultByService['matched_by'], true), 'Criterio de servico deveria aparecer.');

$proposalByKeyword = ['client_id' => 1, 'total' => 1000];
$resultByKeyword = $service->evaluate($template, $proposalByKeyword, [['service_id' => 2, 'description' => 'Mensalidade recorrente do sistema']], $client);
Assert::ok((bool) $resultByKeyword['eligible'], 'Palavra-chave de servico deveria exigir contrato.');
Assert::ok(in_array('palavra_chave', (array) $resultByKeyword['matched_by'], true), 'Criterio de palavra-chave deveria aparecer.');

$disabledTemplate = $template;
$disabledTemplate['auto_criteria_json'] = json_encode(['enabled' => false], JSON_UNESCAPED_UNICODE);
$disabled = $service->evaluate($disabledTemplate, $proposal, $items, $client);
Assert::ok(!(bool) $disabled['eligible'], 'Template com automacao desabilitada nao deve sugerir contrato.');

echo "OK\n";
