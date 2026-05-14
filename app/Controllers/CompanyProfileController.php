<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\BrandAssetProcessor;
use App\Services\CompanyProfileService;
use App\Services\CompanyProfileValidator;
use App\Services\LogoProcessor;

final class CompanyProfileController
{
    public function edit(Request $request): void
    {
        $profile = (new CompanyProfileService())->getCached();
        View::render('company_profile/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'profile' => $profile,
            'errors' => [],
        ]);
    }

    public function update(Request $request): void
    {
        $service = new CompanyProfileService();
        $existing = $service->get();

        $validator = new CompanyProfileValidator();
        $result = $validator->validate($request->allPost());

        if (!(bool) ($result['ok'] ?? false)) {
            View::render('company_profile/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'profile' => $request->allPost(),
                'errors' => (array) ($result['errors'] ?? []),
                'error' => 'Corrija os campos destacados.',
            ]);
            return;
        }

        $files = $_FILES;
        $needLight = $existing === null || (is_array($existing) && (string) ($existing['logo_light_path'] ?? '') === '');
        $needDark = $existing === null || (is_array($existing) && (string) ($existing['logo_dark_path'] ?? '') === '');

        $pending = [];
        $fileErrors = [];
        $processor = new LogoProcessor();

        if (isset($files['logo_light']) && is_array($files['logo_light']) && (int) ($files['logo_light']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $pending['light'] = $processor->process($files['logo_light']);
            } catch (\Throwable $e) {
                $fileErrors['logo_light'] = $e->getMessage();
            }
        }

        if (isset($files['logo_dark']) && is_array($files['logo_dark']) && (int) ($files['logo_dark']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $pending['dark'] = $processor->process($files['logo_dark']);
            } catch (\Throwable $e) {
                $fileErrors['logo_dark'] = $e->getMessage();
            }
        }

        if ($needLight && !isset($pending['light'])) {
            $fileErrors['logo_light'] = 'Logo claro é obrigatório.';
        }
        if ($needDark && !isset($pending['dark'])) {
            $fileErrors['logo_dark'] = 'Logo escuro é obrigatório.';
        }

        $assetProcessor = new BrandAssetProcessor();
        if (isset($files['favicon']) && is_array($files['favicon']) && (int) ($files['favicon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $pending['favicon'] = $assetProcessor->process($files['favicon'], 'favicon');
            } catch (\Throwable $e) {
                $fileErrors['favicon'] = $e->getMessage();
            }
        }
        if (isset($files['meta_image']) && is_array($files['meta_image']) && (int) ($files['meta_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $pending['meta_image'] = $assetProcessor->process($files['meta_image'], 'meta_image');
            } catch (\Throwable $e) {
                $fileErrors['meta_image'] = $e->getMessage();
            }
        }

        if (count($fileErrors) > 0) {
            View::render('company_profile/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'profile' => array_merge((array) $result['data'], $existing ?? []),
                'errors' => $fileErrors,
                'error' => 'Envie os logos obrigatórios (claro e escuro).',
            ]);
            foreach ($pending as $p) {
                $tmpPath = (string) ($p['tmp_path'] ?? '');
                if ($tmpPath !== '' && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
            return;
        }

        $actorId = (int) Session::get('user_id', 0);

        $saved = $service->upsert((array) $result['data'], $actorId, 'ui');

        foreach ($pending as $variant => $p) {
            if (!in_array($variant, ['light', 'dark'], true)) {
                continue;
            }
            $tmpPath = (string) ($p['tmp_path'] ?? '');
            if ($tmpPath === '' || !is_file($tmpPath)) {
                continue;
            }

            $dir = __DIR__ . '/../../storage/uploads/company_profile';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!is_dir($dir)) {
                @unlink($tmpPath);
                continue;
            }

            $ext = (string) ($p['ext'] ?? 'png');
            $final = $dir . '/logo-' . $variant . '.' . $ext;
            $oldPathKey = $variant === 'light' ? 'logo_light_path' : 'logo_dark_path';
            $oldPath = (string) ($saved[$oldPathKey] ?? '');

            if (!@rename($tmpPath, $final)) {
                @unlink($tmpPath);
                continue;
            }
            @chmod($final, 0644);

            $updated = $service->updateLogo($variant, [
                'path' => $final,
                'mime' => (string) ($p['mime'] ?? 'application/octet-stream'),
                'original_name' => (string) ($p['original_name'] ?? ''),
            ], $actorId, 'ui');

            $newPath = (string) ($updated[$oldPathKey] ?? '');
            if ($oldPath !== '' && $oldPath !== $newPath && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        foreach (['favicon', 'meta_image'] as $asset) {
            if (!isset($pending[$asset]) || !is_array($pending[$asset])) {
                continue;
            }
            $p = $pending[$asset];
            $tmpPath = (string) ($p['tmp_path'] ?? '');
            if ($tmpPath === '' || !is_file($tmpPath)) {
                continue;
            }

            $dir = __DIR__ . '/../../storage/uploads/company_profile/branding';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!is_dir($dir)) {
                @unlink($tmpPath);
                continue;
            }

            $ext = (string) ($p['ext'] ?? 'png');
            $final = $dir . '/' . $asset . '.' . $ext;
            $oldKey = $asset . '_path';
            $oldPath = (string) ($saved[$oldKey] ?? '');

            if (!@rename($tmpPath, $final)) {
                @unlink($tmpPath);
                continue;
            }
            @chmod($final, 0644);

            $updated = $service->updateAsset($asset, [
                'path' => $final,
                'mime' => (string) ($p['mime'] ?? 'application/octet-stream'),
                'original_name' => (string) ($p['original_name'] ?? ''),
            ], $actorId, 'ui');

            $newPath = (string) ($updated[$oldKey] ?? '');
            if ($oldPath !== '' && $oldPath !== $newPath && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        Response::redirect($request->basePath() . '/empresa');
    }

    public function audit(Request $request): void
    {
        $rows = (new CompanyProfileService())->listAudit(200);
        View::render('company_profile/audit', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'rows' => $rows,
        ]);
    }

    public function logo(Request $request, array $params): void
    {
        $variant = (string) ($params['variant'] ?? '');
        $variant = $variant === 'light' ? 'light' : 'dark';

        $profile = (new CompanyProfileService())->getCached();
        if ($profile === null) {
            http_response_code(404);
            return;
        }

        $pathKey = $variant === 'light' ? 'logo_light_path' : 'logo_dark_path';
        $mimeKey = $variant === 'light' ? 'logo_light_mime' : 'logo_dark_mime';

        $path = (string) ($profile[$pathKey] ?? '');
        $mime = (string) ($profile[$mimeKey] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function asset(Request $request, array $params): void
    {
        $asset = (string) ($params['asset'] ?? '');
        $asset = $asset === 'meta-image' ? 'meta_image' : 'favicon';

        $profile = (new CompanyProfileService())->getCached();
        if ($profile === null) {
            http_response_code(404);
            return;
        }

        $path = (string) ($profile[$asset . '_path'] ?? '');
        $mime = (string) ($profile[$asset . '_mime'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }
}
