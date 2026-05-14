<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Crypto;

final class Assert
{
    public static function eq(mixed $a, mixed $b, string $msg): void
    {
        if ($a !== $b) {
            throw new RuntimeException($msg . ' (got ' . var_export($a, true) . ', expected ' . var_export($b, true) . ')');
        }
    }

    public static function ok(bool $cond, string $msg): void
    {
        if (!$cond) {
            throw new RuntimeException($msg);
        }
    }
}

$plain = 'Segredo 123 — acentos';
$enc = Crypto::encrypt($plain);
Assert::ok(is_string($enc) && str_starts_with($enc, 'v1.'), 'Criptografia deve retornar v1.*');
$dec = Crypto::decrypt($enc);
Assert::eq($dec, $plain, 'Decrypt deve retornar o original');

$arr = ['a' => 1, 'b' => 'x', 'c' => ['y' => true]];
$encJ = Crypto::encryptJson($arr);
Assert::ok(is_string($encJ) && str_starts_with($encJ, 'v1.'), 'Criptografia JSON deve retornar v1.*');
$decJ = Crypto::decryptJson($encJ);
Assert::eq($decJ, $arr, 'DecryptJson deve retornar o array original');

Assert::eq(Crypto::decrypt(null), null, 'Decrypt null deve ser null');
Assert::eq(Crypto::decrypt(''), null, 'Decrypt vazio deve ser null');
Assert::eq(Crypto::decrypt('v0.abc'), null, 'Decrypt versão desconhecida deve ser null');

echo "OK\n";

