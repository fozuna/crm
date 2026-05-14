<?php
declare(strict_types=1);

namespace App\Services;

final class LogoProcessor
{
    public function process(array $file): array
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

        $maxBytes = 5 * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            throw new \RuntimeException('Arquivo excede 5MB.');
        }

        $original = (string) $file['name'];
        $tmp = (string) $file['tmp_name'];

        $head = @file_get_contents($tmp, false, null, 0, 4096);
        $head = is_string($head) ? $head : '';

        $isSvg = str_contains(strtolower($head), '<svg') || str_ends_with(strtolower($original), '.svg');
        if ($isSvg) {
            return $this->processSvg($tmp, $original);
        }

        return $this->processRaster($tmp, $original);
    }

    private function processSvg(string $tmp, string $original): array
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

        $out = tempnam(sys_get_temp_dir(), 'traxter_logo_');
        if (!is_string($out) || $out === '') {
            throw new \RuntimeException('Falha ao preparar arquivo.');
        }
        $outSvg = $out . '.svg';
        @unlink($out);
        if (@file_put_contents($outSvg, $bytes) === false) {
            throw new \RuntimeException('Falha ao gravar SVG.');
        }

        return [
            'tmp_path' => $outSvg,
            'mime' => 'image/svg+xml',
            'ext' => 'svg',
            'original_name' => $original,
        ];
    }

    private function processRaster(string $tmp, string $original): array
    {
        if (!function_exists('getimagesize') || !function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('Servidor sem suporte a imagem (GD).');
        }

        $info = @getimagesize($tmp);
        if ($info === false || !isset($info['mime'])) {
            throw new \RuntimeException('Imagem inválida.');
        }

        $mime = (string) $info['mime'];
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
            throw new \RuntimeException('Formato inválido. Use PNG, JPG ou SVG.');
        }

        $bytes = @file_get_contents($tmp);
        if (!is_string($bytes) || $bytes === '') {
            throw new \RuntimeException('Imagem inválida.');
        }

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new \RuntimeException('Imagem inválida.');
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);
            throw new \RuntimeException('Imagem inválida.');
        }

        $dstW = 300;
        $dstH = 100;
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            imagedestroy($src);
            throw new \RuntimeException('Falha ao processar imagem.');
        }

        if ($mime === 'image/png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        } else {
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
        }

        $scale = min($dstW / $srcW, $dstH / $srcH);
        $newW = (int) floor($srcW * $scale);
        $newH = (int) floor($srcH * $scale);
        $dstX = (int) floor(($dstW - $newW) / 2);
        $dstY = (int) floor(($dstH - $newH) / 2);

        imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        imagedestroy($src);

        $out = tempnam(sys_get_temp_dir(), 'traxter_logo_');
        if (!is_string($out) || $out === '') {
            imagedestroy($dst);
            throw new \RuntimeException('Falha ao preparar arquivo.');
        }

        $outPng = $out . '.png';
        @unlink($out);
        imagesavealpha($dst, true);
        $ok = imagepng($dst, $outPng, 6);
        imagedestroy($dst);
        if (!$ok || !is_file($outPng)) {
            throw new \RuntimeException('Falha ao gravar imagem.');
        }

        return [
            'tmp_path' => $outPng,
            'mime' => 'image/png',
            'ext' => 'png',
            'original_name' => $original,
        ];
    }
}

