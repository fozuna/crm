<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ClientValidator;

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

$today = new DateTimeImmutable('2026-05-10');
$validator = new ClientValidator($today);

$base = [
    'name' => 'Cliente Exemplo',
    'email' => 'financeiro@cliente.com.br',
    'phone' => '(67) 99999-9999',
    'company' => 'Cliente Exemplo LTDA',
    'contact_person' => 'Maria Silva',
    'status' => 'ativo',
    'project_reference' => 'Projeto institucional',
];

$optional = $validator->validate($base);
Assert::ok((bool) $optional['ok'], 'Cadastro sem servicos adicionais deve ser aceito.');
Assert::eq($optional['data']['has_hosting_contract'], 0, 'Hospedagem deve ficar desmarcada por default.');
Assert::eq($optional['data']['manages_domain'], 0, 'Dominio deve ficar desmarcado por default.');

$hosting = $validator->validate($base + [
    'has_hosting_contract' => '1',
    'hosting_contract_amount' => '1.250,90',
    'hosting_due_date' => '2026-05-20',
    'hosting_renewal_days' => '45',
]);
Assert::ok((bool) $hosting['ok'], 'Hospedagem com dados validos deveria passar.');
Assert::eq($hosting['data']['hosting_contract_amount'], 1250.90, 'Valor da hospedagem deve ser convertido para decimal.');
Assert::eq($hosting['data']['hosting_due_date'], '2026-05-20', 'Vencimento da hospedagem deve ser normalizado.');
Assert::eq($hosting['data']['hosting_renewal_suggested_date'], '2026-07-04', 'Renovacao sugerida deve somar a quantidade de dias ao vencimento.');

$hostingMissing = $validator->validate($base + [
    'has_hosting_contract' => '1',
]);
Assert::ok(!(bool) $hostingMissing['ok'], 'Hospedagem marcada sem campos obrigatorios deve falhar.');
Assert::ok(isset($hostingMissing['errors']['hosting_contract_amount']), 'Deveria exigir valor da hospedagem.');
Assert::ok(isset($hostingMissing['errors']['hosting_due_date']), 'Deveria exigir vencimento da hospedagem.');

$hostingPast = $validator->validate($base + [
    'has_hosting_contract' => '1',
    'hosting_contract_amount' => '490,00',
    'hosting_due_date' => '2026-05-09',
    'hosting_renewal_days' => '30',
]);
Assert::ok(!(bool) $hostingPast['ok'], 'Hospedagem com data passada deve falhar.');
Assert::ok(isset($hostingPast['errors']['hosting_due_date']), 'Deveria acusar data passada na hospedagem.');

$hostingOverLimit = $validator->validate($base + [
    'has_hosting_contract' => '1',
    'hosting_contract_amount' => '490,00',
    'hosting_due_date' => '2026-05-20',
    'hosting_renewal_days' => '46',
]);
Assert::ok(!(bool) $hostingOverLimit['ok'], 'Prazo acima de 45 dias deve falhar.');
Assert::ok(isset($hostingOverLimit['errors']['hosting_renewal_days']), 'Deveria bloquear prazo superior a 45 dias.');

$domain = $validator->validate($base + [
    'manages_domain' => '1',
    'domain_due_date' => '2026-06-18',
    'domain_amount' => '79,90',
]);
Assert::ok((bool) $domain['ok'], 'Dominio com dados validos deveria passar.');
Assert::eq($domain['data']['domain_due_date'], '2026-06-18', 'Vencimento do dominio deve ser normalizado.');
Assert::eq($domain['data']['domain_amount'], 79.90, 'Valor do dominio deve ser convertido.');

$domainMissing = $validator->validate($base + [
    'manages_domain' => '1',
]);
Assert::ok(!(bool) $domainMissing['ok'], 'Dominio marcado sem obrigatorios deve falhar.');
Assert::ok(isset($domainMissing['errors']['domain_due_date']), 'Deveria exigir data do dominio.');
Assert::ok(isset($domainMissing['errors']['domain_amount']), 'Deveria exigir valor do dominio.');

$domainPast = $validator->validate($base + [
    'manages_domain' => '1',
    'domain_due_date' => '2026-05-01',
    'domain_amount' => '79,90',
]);
Assert::ok(!(bool) $domainPast['ok'], 'Dominio com data passada deve falhar.');
Assert::ok(isset($domainPast['errors']['domain_due_date']), 'Deveria bloquear data passada do dominio.');

echo "OK\n";
