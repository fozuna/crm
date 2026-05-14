<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;
use App\Services\CompanyProfileService;

final class ProposalBrandingRepository
{
    public function get(): array
    {
        $legacy = $this->legacyRow();
        $company = null;
        try {
            $company = (new CompanyProfileService())->branding();
        } catch (\Throwable $e) {
            $company = null;
        }

        return [
            'id' => 1,
            'company_name' => (string) ($company['company_name'] ?? $legacy['company_name'] ?? 'TRAXTER'),
            'logo_path' => ($company['logo_path'] ?? null) ?: null,
            'primary_color' => (string) ($company['primary_color'] ?? $legacy['primary_color'] ?? '#293241'),
            'accent_color' => (string) ($company['accent_color'] ?? $legacy['accent_color'] ?? '#ee6c4d'),
            'font_name' => (string) ($company['font_name'] ?? $legacy['font_name'] ?? 'Helvetica'),
            'meta_title' => (string) ($company['meta_title'] ?? ''),
            'meta_description' => (string) ($company['meta_description'] ?? ''),
            'meta_keywords' => (string) ($company['meta_keywords'] ?? ''),
            'favicon_path' => (string) ($company['favicon_path'] ?? ''),
            'favicon_mime' => (string) ($company['favicon_mime'] ?? ''),
            'meta_image_path' => (string) ($company['meta_image_path'] ?? ''),
            'meta_image_mime' => (string) ($company['meta_image_mime'] ?? ''),
            'company_cnpj' => (string) ($company['company_cnpj'] ?? ''),
            'company_email' => (string) ($company['company_email'] ?? ''),
            'company_whatsapp' => (string) ($company['company_whatsapp'] ?? ''),
            'company_website' => (string) ($company['company_website'] ?? ''),
            'updated_at' => (string) ($legacy['updated_at'] ?? ''),
        ];
    }

    public function upsert(array $data): void
    {
        $pdo = DB::pdo();
        $existing = $pdo->query('SELECT COUNT(*) FROM proposal_branding WHERE id = 1')->fetchColumn();
        $logo = array_key_exists('logo_path', $data) ? $data['logo_path'] : null;

        if ((int) $existing === 0) {
            $stmt = $pdo->prepare('INSERT INTO proposal_branding (id, company_name, logo_path, primary_color, accent_color, font_name, updated_at) VALUES (1, :company, :logo, :primary, :accent, :font, NOW())');
        } else {
            $stmt = $pdo->prepare('UPDATE proposal_branding SET company_name = :company, logo_path = COALESCE(:logo, logo_path), primary_color = :primary, accent_color = :accent, font_name = :font, updated_at = NOW() WHERE id = 1');
        }
        $stmt->bindValue(':company', (string) $data['company_name']);
        $stmt->bindValue(':logo', $logo);
        $stmt->bindValue(':primary', (string) $data['primary_color']);
        $stmt->bindValue(':accent', (string) $data['accent_color']);
        $stmt->bindValue(':font', (string) $data['font_name']);
        $stmt->execute();
    }

    private function legacyRow(): array
    {
        try {
            $pdo = DB::pdo();
            $stmt = $pdo->prepare('SELECT id, company_name, primary_color, accent_color, font_name, updated_at FROM proposal_branding WHERE id = 1');
            $stmt->execute();
            $row = $stmt->fetch();
            if (is_array($row)) {
                return $row;
            }
        } catch (\Throwable $e) {
        }

        return [
            'id' => 1,
            'company_name' => 'TRAXTER',
            'primary_color' => '#293241',
            'accent_color' => '#ee6c4d',
            'font_name' => 'Helvetica',
            'updated_at' => '',
        ];
    }
}
