<?php
declare(strict_types=1);

namespace App\Services;

final class BrandAssetProcessor
{
    public function process(array $file, string $kind): array
    {
        if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
            throw new \RuntimeException('Arquivo inválido.');
        }
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Nenhum arquivo enviado.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload.');
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new \RuntimeException('Upload inválido.');
        }

        $tmp = (string) $file['tmp_name'];
        $original = (string) $file['name'];
        $maxBytes = $kind === 'favicon' ? (1024 * 1024) : (5 * 1024 * 1024);
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException($kind === 'favicon' ? 'Favicon excede 1MB.' : 'Arquivo excede 5MB.');
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($tmp);
        }
        $head = @file_get_contents($tmp, false, null, 0, 4096);
        $head = is_string($head) ? $head : '';
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        $isSvg = str_contains(strtolower($head), '<svg') || $ext === 'svg';

        if ($isSvg) {
            if ($kind === 'favicon') {
                return $this->storeSvg($tmp, $original, 'favicon.svg');
            }
            return $this->storeSvg($tmp, $original, 'meta-image.svg');
        }

        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ];
        if ($mime === '' && $ext === 'ico') {
            $mime = 'image/x-icon';
        }
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException($kind === 'favicon'
                ? 'Formato inválido. Use PNG, JPG, WEBP, SVG ou ICO.'
                : 'Formato inválido. Use PNG, JPG, WEBP ou SVG.');
        }
        if ($kind !== 'favicon' && $allowed[$mime] === 'ico') {
            throw new \RuntimeException('Imagem social não pode usar ICO.');
        }

        $out = tempnam(sys_get_temp_dir(), 'traxter_brand_');
        if (!is_string($out) || $out === '') {
            throw new \RuntimeException('Falha ao preparar arquivo.');
        }
        $finalTmp = $out . '.' . $allowed[$mime];
        @unlink($out);
        $bytes = @file_get_contents($tmp);
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Arquivo inválido.');
        }
        if (@file_put_contents($finalTmp, $bytes) === false) {
            throw new \RuntimeException('Falha ao gravar arquivo.');
        }

        return [
            'tmp_path' => $finalTmp,
            'mime' => $mime,
            'ext' => $allowed[$mime],
            'original_name' => $original,
        ];
    }

    private function storeSvg(string $tmp, string $original, string $fileName): array
    {
        $bytes = @file_get_contents($tmp);
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('SVG inválido.');
        }

        $lower = strtolower($bytes);
        if (!str_contains($lower, '<svg')) {
            throw new \RuntimeException('SVG inválido.');
        }
        if (str_contains($lower, '<script') || str_contains($lower, 'onload=') || str_contains($lower, 'onerror=')) {
            throw new \RuntimeException('SVG contém conteúdo não permitido.');
        }

        $out = tempnam(sys_get_temp_dir(), 'traxter_brand_');
        if (!is_string($out) || $out === '') {
            throw new \RuntimeException('Falha ao preparar arquivo.');
        }
        @unlink($out);
        $finalTmp = dirname($out) . DIRECTORY_SEPARATOR . $fileName;
        if (@file_put_contents($finalTmp, $bytes) === false) {
            throw new \RuntimeException('Falha ao gravar SVG.');
        }

        return [
            'tmp_path' => $finalTmp,
            'mime' => 'image/svg+xml',
            'ext' => 'svg',
            'original_name' => $original,
        ];
    }
}
