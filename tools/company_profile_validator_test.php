<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\CompanyProfileValidator;

final class Assert
{
    public static function ok(bool $cond, string $msg): void
    {
        if (!$cond) {
            throw new RuntimeException($msg);
        }
    }

    public static function eq(mixed $a, mixed $b, string $msg): void
    {
        if ($a !== $b) {
            throw new RuntimeException($msg . ' (got ' . var_export($a, true) . ', expected ' . var_export($b, true) . ')');
        }
    }
}

$v = new CompanyProfileValidator();

$valid = [
    'legal_name' => 'Empresa Exemplo LTDA',
    'trade_name' => 'Empresa Exemplo',
    'brand_name' => 'Marca Exemplo',
    'brand_tagline' => 'Tecnologia para RH',
    'cnpj' => '04.252.011/0001-10',
    'domain' => 'exemplo.com.br',
    'email' => 'contato@exemplo.com.br',
    'website' => 'exemplo.com.br',
    'primary_color' => '#112233',
    'accent_color' => '#AABBCC',
    'font_name' => 'Inter',
    'meta_title' => 'Marca Exemplo CRM',
    'meta_description' => 'Descrição institucional consolidada.',
    'meta_keywords' => 'crm, rh, saas',
    'whatsapp' => '(67) 99999-9999',
    'phones' => "(67) 3333-3333\n(67) 98888-8888",
    'zip' => '79000-000',
    'street' => 'Rua A',
    'number' => '123',
    'complement' => 'Sala 2',
    'neighborhood' => 'Centro',
    'city' => 'Campo Grande',
    'state' => 'MS',
];

$r = $v->validate($valid);
Assert::ok((bool) $r['ok'], 'Payload válido deveria passar.');
Assert::eq($r['data']['cnpj'], '04252011000110', 'CNPJ deve ser normalizado em dígitos.');
Assert::eq($r['data']['domain'], 'exemplo.com.br', 'Domínio deve normalizar.');
Assert::eq($r['data']['email'], 'contato@exemplo.com.br', 'E-mail deve ser normalizado.');
Assert::eq($r['data']['whatsapp'], '+5567999999999', 'WhatsApp deve normalizar para E.164 BR.');
Assert::eq($r['data']['primary_color'], '#112233', 'Cor primária deve ser preservada em hexadecimal.');
Assert::eq($r['data']['accent_color'], '#AABBCC', 'Cor de destaque deve ser preservada em hexadecimal.');
Assert::eq($r['data']['font_name'], 'Inter', 'Tipografia deve ser preservada.');
Assert::ok(is_array($r['data']['phones']) && count($r['data']['phones']) === 2, 'Telefones devem virar array.');

$invalid = $valid;
$invalid['cnpj'] = '11.111.111/1111-11';
$invalid['email'] = 'teste@gmail.com';
$invalid['domain'] = 'minhaempresa.com';
$ri = $v->validate($invalid);
Assert::ok(!(bool) $ri['ok'], 'Payload inválido deveria falhar.');
Assert::ok(isset($ri['errors']['cnpj']), 'Deveria acusar CNPJ inválido.');
Assert::ok(isset($ri['errors']['email']), 'Deveria bloquear domínio de e-mail gratuito.');

$mismatch = $valid;
$mismatch['domain'] = 'minhaempresa.com';
$mismatch['email'] = 'contato@exemplo.com.br';
$rm = $v->validate($mismatch);
Assert::ok(!(bool) $rm['ok'], 'Domínio e e-mail divergentes deveria falhar.');
Assert::ok(isset($rm['errors']['email']), 'Deveria acusar mismatch de domínio do e-mail.');

$invalidBrand = $valid;
$invalidBrand['primary_color'] = 'azul';
$invalidBrand['meta_description'] = str_repeat('x', 321);
$rb = $v->validate($invalidBrand);
Assert::ok(!(bool) $rb['ok'], 'Branding inválido deveria falhar.');
Assert::ok(isset($rb['errors']['primary_color']), 'Deveria validar cor primária.');
Assert::ok(isset($rb['errors']['meta_description']), 'Deveria validar tamanho da meta description.');

echo "OK\n";
