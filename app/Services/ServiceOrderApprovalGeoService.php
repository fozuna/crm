<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderApprovalGeoService
{
    public function resolve(array $server): array
    {
        $ip = $this->detectIp($server);
        $country = trim((string) ($server['HTTP_CF_IPCOUNTRY'] ?? $server['HTTP_X_COUNTRY_CODE'] ?? ''));
        $region = trim((string) ($server['HTTP_X_REGION_CODE'] ?? $server['HTTP_X_APPENGINE_REGION'] ?? ''));
        $city = trim((string) ($server['HTTP_X_CITY'] ?? $server['HTTP_X_APPENGINE_CITY'] ?? ''));

        if ($this->isPrivateIp($ip)) {
            $summary = 'Rede local / ambiente interno';
        } else {
            $parts = array_values(array_filter([$city, $region, $country], static fn(string $value): bool => $value !== ''));
            $summary = count($parts) > 0 ? implode(' / ', $parts) : 'Geolocalização aproximada indisponível';
        }

        return [
            'ip' => $ip,
            'summary' => $summary,
            'json' => [
                'ip' => $ip,
                'country' => $country,
                'region' => $region,
                'city' => $city,
                'source' => 'request_headers',
            ],
        ];
    }

    public function actorIdentifier(array $approval, array $input, array $context): string
    {
        $seed = implode('|', [
            (string) ($approval['public_id'] ?? ''),
            strtolower(trim((string) ($input['requester_email'] ?? $approval['requester_email'] ?? $approval['client_billing_email'] ?? $approval['client_email'] ?? ''))),
            preg_replace('/\D+/', '', (string) ($input['requester_phone'] ?? $approval['requester_phone'] ?? $approval['client_billing_phone'] ?? $approval['client_phone'] ?? '')) ?? '',
            strtolower(trim((string) ($input['requester_name'] ?? $approval['requester_name'] ?? $approval['client_contact_person'] ?? $approval['client_name'] ?? ''))),
            (string) ($context['ip'] ?? ''),
        ]);
        return hash('sha256', $seed);
    }

    private function detectIp(array $server): string
    {
        $candidates = [
            $server['HTTP_CF_CONNECTING_IP'] ?? null,
            $server['HTTP_X_FORWARDED_FOR'] ?? null,
            $server['HTTP_X_REAL_IP'] ?? null,
            $server['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            $parts = array_map('trim', explode(',', $candidate));
            foreach ($parts as $part) {
                if (filter_var($part, FILTER_VALIDATE_IP)) {
                    return $part;
                }
            }
        }

        return '';
    }

    private function isPrivateIp(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
