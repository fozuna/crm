<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $key = self::keyBytes();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if (!is_string($cipher) || $cipher === '' || !is_string($tag) || strlen($tag) !== 16) {
            throw new \RuntimeException('Falha ao criptografar dados.');
        }

        $blob = $iv . $tag . $cipher;
        return 'v1.' . rtrim(strtr(base64_encode($blob), '+/', '-_'), '=');
    }

    public static function decrypt(?string $cipherText): ?string
    {
        if ($cipherText === null || $cipherText === '') {
            return null;
        }
        if (!str_starts_with($cipherText, 'v1.')) {
            return null;
        }

        $b64 = substr($cipherText, 3);
        $b64 = strtr($b64, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $blob = base64_decode($b64, true);
        if (!is_string($blob) || strlen($blob) < (12 + 16 + 1)) {
            return null;
        }

        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $cipher = substr($blob, 28);

        $key = self::keyBytes();
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? $plain : null;
    }

    public static function encryptJson(array $data): ?string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || $json === '' || $json === 'null') {
            return null;
        }
        return self::encrypt($json);
    }

    public static function decryptJson(?string $cipherText): array
    {
        $plain = self::decrypt($cipherText);
        if ($plain === null || $plain === '') {
            return [];
        }
        $decoded = json_decode($plain, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function keyBytes(): string
    {
        $raw = (string) Config::get('APP_KEY', '');
        if ($raw !== '') {
            $decoded = self::base64UrlDecode($raw);
            if (is_string($decoded) && strlen($decoded) >= 32) {
                return substr($decoded, 0, 32);
            }
            return substr(hash('sha256', $raw, true), 0, 32);
        }

        $fallback = (string) Config::get('APP_URL', '') . '|' . (string) Config::get('DB_NAME', '') . '|' . (string) Config::get('DB_USER', '');
        return substr(hash('sha256', $fallback, true), 0, 32);
    }

    private static function base64UrlDecode(string $b64url): ?string
    {
        $b64 = strtr($b64url, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $out = base64_decode($b64, true);
        return is_string($out) ? $out : null;
    }
}

