<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class ServiceOrderApprovalTokenService
{
    public function generatePublicId(): string
    {
        return strtolower(bin2hex(random_bytes(10)));
    }

    public function issue(array $claims, ?\DateTimeImmutable $expiresAt = null): string
    {
        $now = time();
        $ttlHours = max(1, (int) Config::get('SERVICE_ORDER_APPROVAL_TTL_HOURS', 72));
        $expiresAt = $expiresAt ?? (new \DateTimeImmutable())->modify('+' . $ttlHours . ' hours');

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
            'enc' => 'AES-256-GCM',
        ];

        $payload = array_merge($claims, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt->getTimestamp(),
        ]);

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->signatureKey(), true);
        $segments[] = $this->base64UrlEncode($signature);
        $jwt = implode('.', $segments);

        $encrypted = Crypto::encrypt($jwt);
        if (!is_string($encrypted) || $encrypted === '') {
            throw new \RuntimeException('Falha ao emitir token de aprovação.');
        }
        return $encrypted;
    }

    public function validate(string $token): array
    {
        $jwt = Crypto::decrypt($token);
        if (!is_string($jwt) || $jwt === '') {
            return [];
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return [];
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $signature = $this->base64UrlDecode($signatureB64);
        if ($signature === null) {
            return [];
        }

        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $this->signatureKey(), true);
        if (!hash_equals($expected, $signature)) {
            return [];
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            return [];
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return [];
        }

        $now = time();
        $nbf = (int) ($payload['nbf'] ?? 0);
        $exp = (int) ($payload['exp'] ?? 0);
        if ($nbf > 0 && $now < $nbf) {
            return [];
        }
        if ($exp > 0 && $now >= $exp) {
            return [];
        }

        return $payload;
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function signatureKey(): string
    {
        $appKey = (string) Config::get('APP_KEY', '');
        if ($appKey === '' || $appKey === 'defina-uma-chave-unica-aqui') {
            throw new \RuntimeException('APP_KEY inválida para emissão do token de aprovação.');
        }
        return hash('sha256', 'service-order-approval|' . $appKey, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = strtr($value, '-_', '+/');
        $pad = strlen($decoded) % 4;
        if ($pad !== 0) {
            $decoded .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($decoded, true);
        return is_string($raw) ? $raw : null;
    }
}
