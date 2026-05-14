<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\CompanyProfileAuditRepository;
use App\Repositories\CompanyProfileRepository;

final class CompanyProfileService
{
    private CompanyProfileRepository $repo;
    private CompanyProfileAuditRepository $auditRepo;

    public function __construct()
    {
        $this->repo = new CompanyProfileRepository();
        $this->auditRepo = new CompanyProfileAuditRepository();
    }

    public function getCached(): ?array
    {
        $cached = $this->readCache();
        if (is_array($cached)) {
            return $cached;
        }

        $profile = $this->get();
        if ($profile !== null) {
            $this->writeCache($profile);
        }
        return $profile;
    }

    public function get(): ?array
    {
        $row = $this->repo->get();
        if ($row === null) {
            return null;
        }

        $email = Crypto::decrypt($row['email_cipher'] ?? null);
        $phones = Crypto::decryptJson($row['phones_cipher'] ?? null);
        $whatsapp = Crypto::decrypt($row['whatsapp_cipher'] ?? null);
        $address = Crypto::decryptJson($row['address_cipher'] ?? null);

        return [
            'id' => (int) ($row['id'] ?? 1),
            'legal_name' => (string) ($row['legal_name'] ?? ''),
            'trade_name' => (string) ($row['trade_name'] ?? ''),
            'brand_name' => (string) ($row['brand_name'] ?? ''),
            'brand_tagline' => (string) ($row['brand_tagline'] ?? ''),
            'cnpj' => (string) ($row['cnpj'] ?? ''),
            'domain' => (string) ($row['domain'] ?? ''),
            'website' => (string) ($row['website'] ?? ''),
            'primary_color' => (string) ($row['primary_color'] ?? '#293241'),
            'accent_color' => (string) ($row['accent_color'] ?? '#ee6c4d'),
            'font_name' => (string) ($row['font_name'] ?? 'Helvetica'),
            'meta_title' => (string) ($row['meta_title'] ?? ''),
            'meta_description' => (string) ($row['meta_description'] ?? ''),
            'meta_keywords' => (string) ($row['meta_keywords'] ?? ''),
            'email' => $email ?? '',
            'phones' => is_array($phones) ? $phones : [],
            'whatsapp' => $whatsapp ?? '',
            'address' => is_array($address) ? $address : [],
            'favicon_path' => (string) ($row['favicon_path'] ?? ''),
            'favicon_mime' => (string) ($row['favicon_mime'] ?? ''),
            'favicon_original_name' => (string) ($row['favicon_original_name'] ?? ''),
            'meta_image_path' => (string) ($row['meta_image_path'] ?? ''),
            'meta_image_mime' => (string) ($row['meta_image_mime'] ?? ''),
            'meta_image_original_name' => (string) ($row['meta_image_original_name'] ?? ''),
            'logo_light_path' => (string) ($row['logo_light_path'] ?? ''),
            'logo_light_mime' => (string) ($row['logo_light_mime'] ?? ''),
            'logo_dark_path' => (string) ($row['logo_dark_path'] ?? ''),
            'logo_dark_mime' => (string) ($row['logo_dark_mime'] ?? ''),
            'updated_by' => isset($row['updated_by']) ? (int) $row['updated_by'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    public function upsert(array $payload, int $actorId, string $source): array
    {
        $before = $this->get();

        $row = [
            'legal_name' => (string) ($payload['legal_name'] ?? ''),
            'trade_name' => ($payload['trade_name'] ?? '') !== '' ? (string) $payload['trade_name'] : null,
            'brand_name' => ($payload['brand_name'] ?? '') !== '' ? (string) $payload['brand_name'] : null,
            'brand_tagline' => ($payload['brand_tagline'] ?? '') !== '' ? (string) $payload['brand_tagline'] : null,
            'cnpj' => (string) ($payload['cnpj'] ?? ''),
            'domain' => ($payload['domain'] ?? '') !== '' ? (string) $payload['domain'] : null,
            'website' => ($payload['website'] ?? '') !== '' ? (string) $payload['website'] : null,
            'primary_color' => (string) ($payload['primary_color'] ?? '#293241'),
            'accent_color' => (string) ($payload['accent_color'] ?? '#ee6c4d'),
            'font_name' => (string) ($payload['font_name'] ?? 'Helvetica'),
            'meta_title' => ($payload['meta_title'] ?? '') !== '' ? (string) $payload['meta_title'] : null,
            'meta_description' => ($payload['meta_description'] ?? '') !== '' ? (string) $payload['meta_description'] : null,
            'meta_keywords' => ($payload['meta_keywords'] ?? '') !== '' ? (string) $payload['meta_keywords'] : null,
            'email_cipher' => Crypto::encrypt(($payload['email'] ?? '') !== '' ? (string) $payload['email'] : null),
            'phones_cipher' => Crypto::encryptJson(is_array($payload['phones'] ?? null) ? (array) $payload['phones'] : []),
            'whatsapp_cipher' => Crypto::encrypt(($payload['whatsapp'] ?? '') !== '' ? (string) $payload['whatsapp'] : null),
            'address_cipher' => Crypto::encryptJson(is_array($payload['address'] ?? null) ? (array) $payload['address'] : []),
            'updated_by' => $actorId,
        ];

        $this->repo->upsert($row);

        $after = $this->get();
        if ($after === null) {
            throw new \RuntimeException('Falha ao salvar perfil empresarial.');
        }

        $action = $before === null ? 'create' : 'update';
        $diff = $this->diff($before, $after);
        $this->auditRepo->create($actorId, $action, $source, $diff);

        $this->writeCache($after);
        return $after;
    }

    public function updateLogo(string $variant, array $logo, int $actorId, string $source): array
    {
        $before = $this->get();
        if ($before === null) {
            throw new \RuntimeException('Crie o perfil empresarial antes de enviar logos.');
        }

        $variant = $variant === 'light' ? 'light' : 'dark';
        $pathKey = $variant === 'light' ? 'logo_light_path' : 'logo_dark_path';
        $mimeKey = $variant === 'light' ? 'logo_light_mime' : 'logo_dark_mime';

        $this->repo->updateLogo($variant, (string) ($logo['path'] ?? ''), (string) ($logo['mime'] ?? ''), (string) ($logo['original_name'] ?? ''));

        $after = $this->get();
        if ($after === null) {
            throw new \RuntimeException('Falha ao atualizar logo.');
        }

        $diff = [
            $pathKey => ['before' => (string) ($before[$pathKey] ?? ''), 'after' => (string) ($after[$pathKey] ?? '')],
            $mimeKey => ['before' => (string) ($before[$mimeKey] ?? ''), 'after' => (string) ($after[$mimeKey] ?? '')],
        ];
        $this->auditRepo->create($actorId, 'logo_update', $source, $diff);
        $this->writeCache($after);
        return $after;
    }

    public function updateAsset(string $asset, array $file, int $actorId, string $source): array
    {
        $before = $this->get();
        if ($before === null) {
            throw new \RuntimeException('Crie o perfil empresarial antes de enviar ativos.');
        }

        $asset = $asset === 'meta_image' ? 'meta_image' : 'favicon';
        $pathKey = $asset . '_path';
        $mimeKey = $asset . '_mime';
        $origKey = $asset . '_original_name';

        $this->repo->updateAsset($asset, (string) ($file['path'] ?? ''), (string) ($file['mime'] ?? ''), (string) ($file['original_name'] ?? ''));

        $after = $this->get();
        if ($after === null) {
            throw new \RuntimeException('Falha ao atualizar ativo de branding.');
        }

        $diff = [
            $pathKey => ['before' => (string) ($before[$pathKey] ?? ''), 'after' => (string) ($after[$pathKey] ?? '')],
            $mimeKey => ['before' => (string) ($before[$mimeKey] ?? ''), 'after' => (string) ($after[$mimeKey] ?? '')],
            $origKey => ['before' => (string) ($before[$origKey] ?? ''), 'after' => (string) ($after[$origKey] ?? '')],
        ];
        $this->auditRepo->create($actorId, 'update', $source, $diff);
        $this->writeCache($after);
        return $after;
    }

    public function branding(): array
    {
        $profile = $this->getCached() ?? [];
        $companyName = trim((string) ($profile['brand_name'] ?? ''));
        if ($companyName === '') {
            $companyName = trim((string) ($profile['trade_name'] ?? ''));
        }
        if ($companyName === '') {
            $companyName = trim((string) ($profile['legal_name'] ?? ''));
        }

        $metaTitle = trim((string) ($profile['meta_title'] ?? ''));
        if ($metaTitle === '') {
            $metaTitle = $companyName !== '' ? $companyName . ' CRM' : 'TRAXTER CRM';
        }

        return [
            'company_name' => $companyName !== '' ? $companyName : 'TRAXTER',
            'brand_tagline' => trim((string) ($profile['brand_tagline'] ?? '')),
            'primary_color' => (string) ($profile['primary_color'] ?? '#293241'),
            'accent_color' => (string) ($profile['accent_color'] ?? '#ee6c4d'),
            'font_name' => (string) ($profile['font_name'] ?? 'Helvetica'),
            'meta_title' => $metaTitle,
            'meta_description' => (string) ($profile['meta_description'] ?? ''),
            'meta_keywords' => (string) ($profile['meta_keywords'] ?? ''),
            'favicon_path' => (string) ($profile['favicon_path'] ?? ''),
            'favicon_mime' => (string) ($profile['favicon_mime'] ?? ''),
            'favicon_original_name' => (string) ($profile['favicon_original_name'] ?? ''),
            'meta_image_path' => (string) ($profile['meta_image_path'] ?? ''),
            'meta_image_mime' => (string) ($profile['meta_image_mime'] ?? ''),
            'meta_image_original_name' => (string) ($profile['meta_image_original_name'] ?? ''),
            'logo_path' => (string) (($profile['logo_dark_path'] ?? '') !== '' ? $profile['logo_dark_path'] : ($profile['logo_light_path'] ?? '')),
            'logo_light_path' => (string) ($profile['logo_light_path'] ?? ''),
            'logo_light_mime' => (string) ($profile['logo_light_mime'] ?? ''),
            'logo_dark_path' => (string) ($profile['logo_dark_path'] ?? ''),
            'logo_dark_mime' => (string) ($profile['logo_dark_mime'] ?? ''),
            'company_cnpj' => (string) ($profile['cnpj'] ?? ''),
            'company_email' => (string) ($profile['email'] ?? ''),
            'company_whatsapp' => (string) ($profile['whatsapp'] ?? ''),
            'company_website' => (string) ($profile['website'] ?? ''),
        ];
    }

    public function delete(int $actorId, string $source): void
    {
        $before = $this->get();
        if ($before === null) {
            return;
        }

        $this->repo->delete();
        $this->auditRepo->create($actorId, 'delete', $source, ['deleted' => true]);
        $this->invalidateCache();

        foreach (['logo_light_path', 'logo_dark_path', 'favicon_path', 'meta_image_path'] as $k) {
            $p = (string) ($before[$k] ?? '');
            if ($p !== '' && is_file($p)) {
                @unlink($p);
            }
        }
    }

    public function listAudit(int $limit = 100): array
    {
        $rows = $this->auditRepo->list($limit);
        foreach ($rows as &$r) {
            $decoded = json_decode((string) ($r['diff'] ?? ''), true);
            $r['diff'] = is_array($decoded) ? $decoded : [];
        }
        return $rows;
    }

    public function invalidateCache(): void
    {
        $path = $this->cachePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function diff(?array $before, array $after): array
    {
        $before = is_array($before) ? $before : [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
        $out = [];
        foreach ($keys as $k) {
            if (in_array($k, ['logo_light_path', 'logo_dark_path', 'logo_light_mime', 'logo_dark_mime'], true)) {
                continue;
            }
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;
            if ($b !== $a) {
                $out[$k] = ['before' => $b, 'after' => $a];
            }
        }
        return $out;
    }

    private function cachePath(): string
    {
        return __DIR__ . '/../../storage/cache/company_profile.json';
    }

    private function readCache(): ?array
    {
        $path = $this->cachePath();
        if (!is_file($path)) {
            return null;
        }

        $ttl = 300;
        $age = time() - (int) @filemtime($path);
        if ($age > $ttl) {
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function writeCache(array $profile): void
    {
        $dir = dirname($this->cachePath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            return;
        }

        $json = json_encode($profile, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return;
        }
        @file_put_contents($this->cachePath(), $json);
    }
}
