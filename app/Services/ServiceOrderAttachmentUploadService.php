<?php
declare(strict_types=1);

namespace App\Services;

final class ServiceOrderAttachmentUploadService
{
    private int $maxBytes = 10_485_760;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function uploadMany(int $serviceOrderId, array $files): array
    {
        $normalized = $this->normalizeFiles($files);
        $stored = [];

        foreach ($normalized as $file) {
            $stored[] = $this->storeOne($serviceOrderId, $file);
        }

        return $stored;
    }

    public function storeOne(int $serviceOrderId, array $file): array
    {
        if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
            throw new \RuntimeException('Arquivo inválido.');
        }
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            throw new \RuntimeException('Nenhum arquivo enviado.');
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Falha no upload do arquivo.');
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw new \RuntimeException('Upload inválido.');
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $this->maxBytes) {
            throw new \RuntimeException('Arquivo excede o limite de 10MB.');
        }

        $originalName = $this->sanitizeOriginalName((string) $file['name']);
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = $this->allowedExtensions();
        if (!isset($allowed[$extension])) {
            throw new \RuntimeException('Tipo de arquivo não permitido.');
        }

        $mime = $this->detectMime((string) $file['tmp_name']);
        if (!$this->isMimeAllowed($extension, $mime)) {
            throw new \RuntimeException('Conteúdo do arquivo incompatível com o tipo enviado.');
        }

        $dir = __DIR__ . '/../../storage/uploads/service-orders/' . $serviceOrderId;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível preparar a pasta de anexos.');
        }

        $storedName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $path = $dir . DIRECTORY_SEPARATOR . $storedName;
        if (!@move_uploaded_file((string) $file['tmp_name'], $path)) {
            throw new \RuntimeException('Não foi possível salvar o anexo.');
        }
        @chmod($path, 0644);

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_path' => $path,
            'file_extension' => $extension,
            'file_size' => (int) $file['size'],
            'mime_type' => $mime,
        ];
    }

    public function isImage(array $attachment): bool
    {
        $mime = strtolower((string) ($attachment['mime_type'] ?? ''));
        return str_starts_with($mime, 'image/');
    }

    public function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return array_values(array_filter($normalized, static fn(array $item): bool => (int) ($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
    }

    private function allowedExtensions(): array
    {
        return [
            'pdf' => true,
            'doc' => true,
            'docx' => true,
            'xls' => true,
            'xlsx' => true,
            'png' => true,
            'jpg' => true,
            'jpeg' => true,
            'webp' => true,
            'zip' => true,
        ];
    }

    private function sanitizeOriginalName(string $name): string
    {
        $base = trim($name);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base);
        $base = trim((string) $base, '-.');
        if ($base === '') {
            $base = 'arquivo';
        }
        return mb_substr($base, 0, 180);
    }

    private function detectMime(string $tmpPath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return strtolower($mime);
                }
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($tmpPath);
            if (is_string($mime) && $mime !== '') {
                return strtolower($mime);
            }
        }
        return 'application/octet-stream';
    }

    private function isMimeAllowed(string $extension, string $mime): bool
    {
        $allowed = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'webp' => ['image/webp'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        ];

        return in_array($mime, $allowed[$extension] ?? [], true);
    }
}
