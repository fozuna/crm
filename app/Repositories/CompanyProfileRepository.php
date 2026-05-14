<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class CompanyProfileRepository
{
    public function get(): ?array
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT id, legal_name, trade_name, brand_name, brand_tagline, cnpj, domain, website, primary_color, accent_color, font_name, meta_title, meta_description, meta_keywords, email_cipher, phones_cipher, whatsapp_cipher, address_cipher, favicon_path, favicon_mime, favicon_original_name, meta_image_path, meta_image_mime, meta_image_original_name, logo_light_path, logo_light_mime, logo_light_original_name, logo_dark_path, logo_dark_mime, logo_dark_original_name, updated_by, created_at, updated_at FROM company_profile WHERE id = 1');
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function upsert(array $row): void
    {
        $pdo = DB::pdo();

        $existing = $this->exists();
        if ($existing) {
            $stmt = $pdo->prepare('UPDATE company_profile SET legal_name = :legal_name, trade_name = :trade_name, brand_name = :brand_name, brand_tagline = :brand_tagline, cnpj = :cnpj, domain = :domain, website = :website, primary_color = :primary_color, accent_color = :accent_color, font_name = :font_name, meta_title = :meta_title, meta_description = :meta_description, meta_keywords = :meta_keywords, email_cipher = :email_cipher, phones_cipher = :phones_cipher, whatsapp_cipher = :whatsapp_cipher, address_cipher = :address_cipher, updated_by = :updated_by, updated_at = NOW() WHERE id = 1');
        } else {
            $stmt = $pdo->prepare('INSERT INTO company_profile (id, legal_name, trade_name, brand_name, brand_tagline, cnpj, domain, website, primary_color, accent_color, font_name, meta_title, meta_description, meta_keywords, email_cipher, phones_cipher, whatsapp_cipher, address_cipher, updated_by, created_at, updated_at) VALUES (1, :legal_name, :trade_name, :brand_name, :brand_tagline, :cnpj, :domain, :website, :primary_color, :accent_color, :font_name, :meta_title, :meta_description, :meta_keywords, :email_cipher, :phones_cipher, :whatsapp_cipher, :address_cipher, :updated_by, NOW(), NOW())');
        }

        $stmt->bindValue(':legal_name', (string) ($row['legal_name'] ?? ''));
        $stmt->bindValue(':trade_name', $row['trade_name'] !== null ? (string) $row['trade_name'] : null);
        $stmt->bindValue(':brand_name', $row['brand_name'] !== null ? (string) $row['brand_name'] : null);
        $stmt->bindValue(':brand_tagline', $row['brand_tagline'] !== null ? (string) $row['brand_tagline'] : null);
        $stmt->bindValue(':cnpj', (string) ($row['cnpj'] ?? ''));
        $stmt->bindValue(':domain', $row['domain'] !== null ? (string) $row['domain'] : null);
        $stmt->bindValue(':website', $row['website'] !== null ? (string) $row['website'] : null);
        $stmt->bindValue(':primary_color', (string) ($row['primary_color'] ?? '#293241'));
        $stmt->bindValue(':accent_color', (string) ($row['accent_color'] ?? '#ee6c4d'));
        $stmt->bindValue(':font_name', (string) ($row['font_name'] ?? 'Helvetica'));
        $stmt->bindValue(':meta_title', $row['meta_title'] !== null ? (string) $row['meta_title'] : null);
        $stmt->bindValue(':meta_description', $row['meta_description'] !== null ? (string) $row['meta_description'] : null);
        $stmt->bindValue(':meta_keywords', $row['meta_keywords'] !== null ? (string) $row['meta_keywords'] : null);
        $stmt->bindValue(':email_cipher', $row['email_cipher'] !== null ? (string) $row['email_cipher'] : null);
        $stmt->bindValue(':phones_cipher', $row['phones_cipher'] !== null ? (string) $row['phones_cipher'] : null);
        $stmt->bindValue(':whatsapp_cipher', $row['whatsapp_cipher'] !== null ? (string) $row['whatsapp_cipher'] : null);
        $stmt->bindValue(':address_cipher', $row['address_cipher'] !== null ? (string) $row['address_cipher'] : null);
        $updatedBy = isset($row['updated_by']) ? (int) $row['updated_by'] : null;
        $stmt->bindValue(':updated_by', $updatedBy, $updatedBy === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateLogo(string $variant, ?string $path, ?string $mime, ?string $originalName): void
    {
        $variant = $variant === 'light' ? 'light' : 'dark';

        $pdo = DB::pdo();
        $colPath = $variant === 'light' ? 'logo_light_path' : 'logo_dark_path';
        $colMime = $variant === 'light' ? 'logo_light_mime' : 'logo_dark_mime';
        $colOrig = $variant === 'light' ? 'logo_light_original_name' : 'logo_dark_original_name';

        $sql = 'UPDATE company_profile SET ' . $colPath . ' = :path, ' . $colMime . ' = :mime, ' . $colOrig . ' = :orig, updated_at = NOW() WHERE id = 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':path', $path);
        $stmt->bindValue(':mime', $mime);
        $stmt->bindValue(':orig', $originalName);
        $stmt->execute();
    }

    public function updateAsset(string $asset, ?string $path, ?string $mime, ?string $originalName): void
    {
        $asset = $asset === 'meta_image' ? 'meta_image' : 'favicon';

        $pdo = DB::pdo();
        $colPath = $asset . '_path';
        $colMime = $asset . '_mime';
        $colOrig = $asset . '_original_name';

        $sql = 'UPDATE company_profile SET ' . $colPath . ' = :path, ' . $colMime . ' = :mime, ' . $colOrig . ' = :orig, updated_at = NOW() WHERE id = 1';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':path', $path);
        $stmt->bindValue(':mime', $mime);
        $stmt->bindValue(':orig', $originalName);
        $stmt->execute();
    }

    public function delete(): void
    {
        $pdo = DB::pdo();
        $pdo->exec('DELETE FROM company_profile WHERE id = 1');
    }

    private function exists(): bool
    {
        $pdo = DB::pdo();
        $stmt = $pdo->query('SELECT 1 FROM company_profile WHERE id = 1');
        return $stmt->fetchColumn() !== false;
    }
}
