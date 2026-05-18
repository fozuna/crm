<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI apenas.\n";
    exit(1);
}

$metaPath = (string) ($argv[1] ?? '');
$encPath = (string) ($argv[2] ?? '');
$outPath = (string) ($argv[3] ?? '');
$passphrase = (string) ($argv[4] ?? '');

if ($metaPath === '' || $encPath === '' || $outPath === '' || $passphrase === '') {
    fwrite(STDERR, "Uso: php tools/decrypt_backup.php <meta.json> <arquivo.enc> <saida.sql.gz> <passphrase>\n");
    exit(2);
}

$metaRaw = @file_get_contents($metaPath);
$meta = $metaRaw !== false ? json_decode((string) $metaRaw, true) : null;
if (!is_array($meta)) {
    fwrite(STDERR, "Meta inválida.\n");
    exit(3);
}

$iterations = (int) ($meta['iterations'] ?? 0);
$salt = base64_decode((string) ($meta['salt_b64'] ?? ''), true);
$iv = base64_decode((string) ($meta['iv_b64'] ?? ''), true);
$macExpected = base64_decode((string) ($meta['hmac_sha256_b64'] ?? ''), true);
if ($iterations <= 0 || !is_string($salt) || !is_string($iv) || !is_string($macExpected) || $salt === '' || $iv === '' || $macExpected === '') {
    fwrite(STDERR, "Meta incompleta.\n");
    exit(4);
}

$dk = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, 64, true);
$encKey = substr($dk, 0, 32);
$macKey = substr($dk, 32, 32);

$in = @fopen($encPath, 'rb');
if (!is_resource($in)) {
    fwrite(STDERR, "Falha ao abrir .enc.\n");
    exit(5);
}

$h = hash_init('sha256', HASH_HMAC, $macKey);
hash_update($h, $salt);
hash_update($h, $iv);
while (!feof($in)) {
    $chunk = fread($in, 1024 * 1024);
    if ($chunk === false) {
        fclose($in);
        fwrite(STDERR, "Falha ao ler .enc.\n");
        exit(6);
    }
    if ($chunk !== '') {
        hash_update($h, $chunk);
    }
}
fclose($in);

$mac = hash_final($h, true);
if (!hash_equals($macExpected, $mac)) {
    fwrite(STDERR, "HMAC inválido.\n");
    exit(7);
}

$in = @fopen($encPath, 'rb');
if (!is_resource($in)) {
    fwrite(STDERR, "Falha ao abrir .enc.\n");
    exit(8);
}
$out = @fopen($outPath, 'wb');
if (!is_resource($out)) {
    fclose($in);
    fwrite(STDERR, "Falha ao criar saída.\n");
    exit(9);
}

$blockSize = 16;
$buf = '';
$curIv = $iv;
$tail = '';

while (!feof($in)) {
    $chunk = fread($in, 1024 * 1024);
    if ($chunk === false) {
        fclose($in);
        fclose($out);
        fwrite(STDERR, "Falha ao ler .enc.\n");
        exit(10);
    }
    $buf .= $chunk;

    $fullLen = intdiv(strlen($buf), $blockSize) * $blockSize;
    if ($fullLen === 0) {
        continue;
    }
    $toDec = substr($buf, 0, $fullLen);
    $buf = substr($buf, $fullLen);

    $plain = openssl_decrypt($toDec, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $curIv);
    if ($plain === false) {
        fclose($in);
        fclose($out);
        fwrite(STDERR, "Falha ao descriptografar.\n");
        exit(11);
    }
    $curIv = substr($toDec, -$blockSize);
    $tail .= $plain;

    if (strlen($tail) > 2 * 1024 * 1024) {
        $emitLen = strlen($tail) - $blockSize;
        if ($emitLen > 0) {
            fwrite($out, substr($tail, 0, $emitLen));
            $tail = substr($tail, $emitLen);
        }
    }
}

if ($buf !== '') {
    fclose($in);
    fclose($out);
    fwrite(STDERR, "Arquivo .enc truncado.\n");
    exit(12);
}

if (strlen($tail) === 0 || (strlen($tail) % $blockSize) !== 0) {
    fclose($in);
    fclose($out);
    fwrite(STDERR, "Conteúdo inválido.\n");
    exit(13);
}

$pad = ord(substr($tail, -1));
if ($pad <= 0 || $pad > $blockSize) {
    fclose($in);
    fclose($out);
    fwrite(STDERR, "Padding inválido.\n");
    exit(14);
}

$plainFinal = substr($tail, 0, -$pad);
fwrite($out, $plainFinal);

fclose($in);
fclose($out);

echo "OK: {$outPath}\n";
exit(0);

