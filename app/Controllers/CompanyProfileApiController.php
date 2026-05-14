<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CompanyProfileService;
use App\Services\CompanyProfileValidator;
use App\Services\LogoProcessor;

final class CompanyProfileApiController
{
    public function show(Request $request): void
    {
        $this->assertAdmin();
        $profile = (new CompanyProfileService())->getCached();
        Response::json(['ok' => true, 'data' => $profile]);
    }

    public function upsert(Request $request): void
    {
        $this->assertAdmin();
        $payload = $request->jsonBody();
        $validator = new CompanyProfileValidator();
        $result = $validator->validate($payload);
        if (!(bool) ($result['ok'] ?? false)) {
            Response::json(['ok' => false, 'errors' => (array) ($result['errors'] ?? [])], 422);
        }

        $actorId = (int) Session::get('user_id', 0);
        $data = (new CompanyProfileService())->upsert((array) $result['data'], $actorId, 'api');
        Response::json(['ok' => true, 'data' => $data]);
    }

    public function destroy(Request $request): void
    {
        $this->assertAdmin();
        $actorId = (int) Session::get('user_id', 0);
        (new CompanyProfileService())->delete($actorId, 'api');
        Response::json(['ok' => true]);
    }

    public function audit(Request $request): void
    {
        $this->assertAdmin();
        $limit = (int) ($request->input('limit', 200));
        $rows = (new CompanyProfileService())->listAudit($limit);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function uploadLogo(Request $request): void
    {
        $this->assertAdmin();
        $variant = (string) ($request->input('variant', ''));
        $variant = $variant === 'light' ? 'light' : 'dark';

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::json(['ok' => false, 'error' => 'Arquivo ausente.'], 400);
        }

        $processor = new LogoProcessor();
        $processed = $processor->process($_FILES['file']);

        $tmpPath = (string) ($processed['tmp_path'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            Response::json(['ok' => false, 'error' => 'Falha ao processar logo.'], 400);
        }

        $dir = __DIR__ . '/../../storage/uploads/company_profile';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            @unlink($tmpPath);
            Response::json(['ok' => false, 'error' => 'Falha ao preparar diretório.'], 500);
        }

        $ext = (string) ($processed['ext'] ?? 'png');
        $final = $dir . '/logo-' . $variant . '.' . $ext;
        if (!@rename($tmpPath, $final)) {
            @unlink($tmpPath);
            Response::json(['ok' => false, 'error' => 'Falha ao salvar logo.'], 500);
        }
        @chmod($final, 0644);

        $actorId = (int) Session::get('user_id', 0);
        $service = new CompanyProfileService();
        $before = $service->get();
        $updated = $service->updateLogo($variant, [
            'path' => $final,
            'mime' => (string) ($processed['mime'] ?? 'application/octet-stream'),
            'original_name' => (string) ($processed['original_name'] ?? ''),
        ], $actorId, 'api');

        if (is_array($before)) {
            $oldKey = $variant === 'light' ? 'logo_light_path' : 'logo_dark_path';
            $old = (string) ($before[$oldKey] ?? '');
            $new = (string) ($updated[$oldKey] ?? '');
            if ($old !== '' && $old !== $new && is_file($old)) {
                @unlink($old);
            }
        }

        Response::json(['ok' => true, 'data' => $updated]);
    }

    private function assertAdmin(): void
    {
        $userId = Session::get('user_id');
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            Response::json(['ok' => false, 'error' => 'Não autenticado.'], 401);
        }
        $isAdmin = (int) Session::get('is_admin', 0);
        if ($isAdmin !== 1) {
            Response::json(['ok' => false, 'error' => 'Sem permissão.'], 403);
        }
    }
}
