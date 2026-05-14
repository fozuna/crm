<?php
declare(strict_types=1);

namespace App\Services;

final class CompanyProfileValidator
{
    public function validate(array $in): array
    {
        $errors = [];

        $legalName = trim((string) ($in['legal_name'] ?? ''));
        $tradeName = trim((string) ($in['trade_name'] ?? ''));
        $brandName = trim((string) ($in['brand_name'] ?? ''));
        $brandTagline = trim((string) ($in['brand_tagline'] ?? ''));
        $cnpjRaw = (string) ($in['cnpj'] ?? '');
        $domainRaw = trim((string) ($in['domain'] ?? ''));
        $emailRaw = trim((string) ($in['email'] ?? ''));
        $websiteRaw = trim((string) ($in['website'] ?? ''));
        $primaryColor = strtoupper(trim((string) ($in['primary_color'] ?? '#293241')));
        $accentColor = strtoupper(trim((string) ($in['accent_color'] ?? '#ee6c4d')));
        $fontName = trim((string) ($in['font_name'] ?? 'Helvetica'));
        $metaTitle = trim((string) ($in['meta_title'] ?? ''));
        $metaDescription = trim((string) ($in['meta_description'] ?? ''));
        $metaKeywords = trim((string) ($in['meta_keywords'] ?? ''));
        $phonesRaw = $in['phones'] ?? null;
        $whatsappRaw = trim((string) ($in['whatsapp'] ?? ''));

        if ($legalName === '') {
            $errors['legal_name'] = 'Razão Social é obrigatória.';
        }
        if ($brandName !== '' && mb_strlen($brandName) > 190) {
            $errors['brand_name'] = 'Nome da marca deve ter no máximo 190 caracteres.';
        }
        if ($brandTagline !== '' && mb_strlen($brandTagline) > 255) {
            $errors['brand_tagline'] = 'Tagline deve ter no máximo 255 caracteres.';
        }
        if (!$this->isHexColor($primaryColor)) {
            $errors['primary_color'] = 'Cor primária inválida.';
        }
        if (!$this->isHexColor($accentColor)) {
            $errors['accent_color'] = 'Cor de destaque inválida.';
        }
        if ($fontName === '') {
            $fontName = 'Helvetica';
        }
        if (mb_strlen($fontName) > 80) {
            $errors['font_name'] = 'Tipografia deve ter no máximo 80 caracteres.';
        }
        if ($metaTitle !== '' && mb_strlen($metaTitle) > 190) {
            $errors['meta_title'] = 'Meta title deve ter no máximo 190 caracteres.';
        }
        if ($metaDescription !== '' && mb_strlen($metaDescription) > 320) {
            $errors['meta_description'] = 'Meta description deve ter no máximo 320 caracteres.';
        }
        if ($metaKeywords !== '' && mb_strlen($metaKeywords) > 500) {
            $errors['meta_keywords'] = 'Meta keywords deve ter no máximo 500 caracteres.';
        }

        $cnpjDigits = preg_replace('/\D+/', '', $cnpjRaw);
        $cnpjDigits = is_string($cnpjDigits) ? $cnpjDigits : '';
        if ($cnpjDigits === '' || !$this->isValidCnpj($cnpjDigits)) {
            $errors['cnpj'] = 'CNPJ inválido.';
        }

        $domain = '';
        if ($domainRaw === '') {
            $errors['domain'] = 'Domínio é obrigatório.';
        } else {
            $domain = $this->normalizeDomain($domainRaw);
            if ($domain === '') {
                $errors['domain'] = 'Domínio inválido.';
            }
        }

        $email = '';
        if ($emailRaw === '' || !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail corporativo inválido.';
        } else {
            $email = strtolower($emailRaw);
            $emailDomain = substr(strrchr($email, '@') ?: '', 1);
            if ($emailDomain === '' || $this->isFreeEmailDomain($emailDomain)) {
                $errors['email'] = 'Use um e-mail corporativo (domínio próprio).';
            } elseif ($domain !== '' && !$this->emailMatchesDomain($emailDomain, $domain)) {
                $errors['email'] = 'O domínio do e-mail deve corresponder ao domínio informado.';
            }
        }

        $website = '';
        if ($websiteRaw !== '') {
            $website = $this->normalizeWebsite($websiteRaw);
            if ($website === '') {
                $errors['website'] = 'Website inválido.';
            }
        }

        $phones = $this->normalizePhones($phonesRaw);
        if (count($phones) > 10) {
            $errors['phones'] = 'Limite de 10 telefones.';
        }

        $whatsapp = $this->normalizeWhatsapp($whatsappRaw);
        if ($whatsapp === '') {
            $errors['whatsapp'] = 'WhatsApp inválido.';
        }

        $address = [
            'zip' => $this->onlyDigits((string) ($in['zip'] ?? '')),
            'street' => trim((string) ($in['street'] ?? '')),
            'number' => trim((string) ($in['number'] ?? '')),
            'complement' => trim((string) ($in['complement'] ?? '')),
            'neighborhood' => trim((string) ($in['neighborhood'] ?? '')),
            'city' => trim((string) ($in['city'] ?? '')),
            'state' => strtoupper(trim((string) ($in['state'] ?? ''))),
        ];

        if ($address['zip'] !== '' && strlen($address['zip']) !== 8) {
            $errors['zip'] = 'CEP inválido.';
        }
        if ($address['state'] !== '' && preg_match('/^[A-Z]{2}$/', $address['state']) !== 1) {
            $errors['state'] = 'UF inválida.';
        }

        $data = [
            'legal_name' => $legalName,
            'trade_name' => $tradeName,
            'brand_name' => $brandName,
            'brand_tagline' => $brandTagline,
            'cnpj' => $cnpjDigits,
            'domain' => $domain,
            'website' => $website,
            'primary_color' => $this->normalizeHexColor($primaryColor),
            'accent_color' => $this->normalizeHexColor($accentColor),
            'font_name' => $fontName,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'email' => $email,
            'phones' => $phones,
            'whatsapp' => $whatsapp,
            'address' => $address,
        ];

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'data' => $data,
        ];
    }

    private function normalizePhones(mixed $value): array
    {
        if (is_array($value)) {
            $in = $value;
        } else {
            $raw = trim((string) $value);
            $in = $raw === '' ? [] : preg_split('/[\n,;]+/', $raw);
        }

        $out = [];
        foreach ($in as $p) {
            $p = trim((string) $p);
            if ($p === '') {
                continue;
            }
            $digits = $this->onlyDigits($p);
            if (strlen($digits) < 10 || strlen($digits) > 13) {
                continue;
            }
            $out[] = $digits;
        }
        return array_values(array_unique($out));
    }

    private function normalizeWhatsapp(string $raw): string
    {
        $digits = $this->onlyDigits($raw);
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '55')) {
            if (strlen($digits) < 12 || strlen($digits) > 13) {
                return '';
            }
            return '+' . $digits;
        }

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '+55' . $digits;
        }

        return '';
    }

    private function normalizeDomain(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $raw = preg_replace('#^https?://#', '', $raw);
        $raw = preg_replace('#/.*$#', '', (string) $raw);
        $raw = trim((string) $raw);
        if ($raw === '' || strlen($raw) > 190) {
            return '';
        }
        if (preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $raw) !== 1) {
            return '';
        }
        return $raw;
    }

    private function normalizeWebsite(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        $parts = parse_url($raw);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }
        return $raw;
    }

    private function onlyDigits(string $s): string
    {
        $d = preg_replace('/\D+/', '', $s);
        return is_string($d) ? $d : '';
    }

    private function isHexColor(string $color): bool
    {
        return preg_match('/^#?[0-9A-Fa-f]{6}$/', $color) === 1;
    }

    private function normalizeHexColor(string $color): string
    {
        $color = strtoupper(trim($color));
        if ($color === '') {
            return '';
        }
        return str_starts_with($color, '#') ? $color : ('#' . $color);
    }

    private function isFreeEmailDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        $blocked = [
            'gmail.com',
            'hotmail.com',
            'outlook.com',
            'live.com',
            'yahoo.com',
            'icloud.com',
            'bol.com.br',
            'uol.com.br',
            'terra.com.br',
        ];
        return in_array($domain, $blocked, true);
    }

    private function emailMatchesDomain(string $emailDomain, string $domain): bool
    {
        $emailDomain = strtolower($emailDomain);
        $domain = strtolower($domain);
        return $emailDomain === $domain || str_ends_with($emailDomain, '.' . $domain);
    }

    private function isValidCnpj(string $cnpjDigits): bool
    {
        if (strlen($cnpjDigits) !== 14) {
            return false;
        }
        if (preg_match('/^(\d)\1{13}$/', $cnpjDigits) === 1) {
            return false;
        }

        $nums = array_map('intval', str_split($cnpjDigits));
        $w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $nums[$i] * $w1[$i];
        }
        $r = $sum % 11;
        $d1 = $r < 2 ? 0 : 11 - $r;
        if ($nums[12] !== $d1) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $nums[$i] * $w2[$i];
        }
        $r = $sum % 11;
        $d2 = $r < 2 ? 0 : 11 - $r;
        return $nums[13] === $d2;
    }
}
