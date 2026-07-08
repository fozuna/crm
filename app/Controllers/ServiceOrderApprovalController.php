<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ServiceOrderApprovalService;

final class ServiceOrderApprovalController
{
    public function generate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);

        try {
            $result = (new ServiceOrderApprovalService())->generateForServiceOrder($id, $actorId);
            $emailStatus = (string) (($result['email']['status'] ?? ''));
            $message = $emailStatus === 'enviado'
                ? 'Link de aprovação gerado e enviado por e-mail ao cliente.'
                : 'Link de aprovação gerado, mas o envio por e-mail precisa de verificação.';

            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=success&msg=' . rawurlencode($message));
        } catch (\Throwable $e) {
            Response::redirect($request->basePath() . '/ordens-servico/' . $id . '/editar?toast=error&msg=' . rawurlencode($e->getMessage()));
        }
    }

    public function proof(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $approval = (new ServiceOrderApprovalService())->approvalSummaryForOrder($id);
        if ($approval === null) {
            http_response_code(404);
            echo 'Aprovação não encontrada.';
            return;
        }

        $path = (string) ($approval['proof_pdf_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            echo 'Comprovante PDF ainda não foi gerado.';
            return;
        }

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="comprovante-aprovacao-os-' . rawurlencode((string) ($approval['numero_os'] ?? $id)) . '.pdf"');
        readfile($path);
        exit;
    }

    public function showPublic(Request $request, array $params): void
    {
        $publicId = trim((string) ($params['publicId'] ?? ''));
        $token = trim((string) $request->input('token', ''));

        $result = $publicId !== '' && $token !== ''
            ? (new ServiceOrderApprovalService())->resolvePublicAccess($publicId, $token, $_SERVER)
            : ['ok' => false, 'message' => 'Link de aprovação incompleto ou inválido.', 'status_code' => 400];

        http_response_code((int) ($result['status_code'] ?? 200));
        View::render('service_orders/public_approval', [
            'base' => $request->basePath(),
            'approval' => is_array($result['approval'] ?? null) ? $result['approval'] : null,
            'token' => $token,
            'error' => ($result['ok'] ?? false) ? '' : (string) ($result['message'] ?? 'Link inválido.'),
            'success' => '',
            'submitted' => false,
        ], null);
    }

    public function submitPublic(Request $request, array $params): void
    {
        $publicId = trim((string) ($params['publicId'] ?? ''));
        $token = trim((string) $request->input('token', ''));

        try {
            $result = (new ServiceOrderApprovalService())->decide($publicId, $token, $request->allPost(), $_SERVER);
            View::render('service_orders/public_approval', [
                'base' => $request->basePath(),
                'approval' => is_array($result['approval'] ?? null) ? $result['approval'] : null,
                'token' => '',
                'error' => '',
                'success' => 'Manifestação registrada com sucesso. O comprovante digital foi armazenado na ordem de serviço.',
                'submitted' => true,
            ], null);
        } catch (\Throwable $e) {
            $reloaded = $publicId !== '' && $token !== ''
                ? (new ServiceOrderApprovalService())->resolvePublicAccess($publicId, $token, $_SERVER)
                : ['approval' => null];

            View::render('service_orders/public_approval', [
                'base' => $request->basePath(),
                'approval' => is_array($reloaded['approval'] ?? null) ? $reloaded['approval'] : null,
                'token' => $token,
                'error' => $e->getMessage(),
                'success' => '',
                'submitted' => false,
            ], null);
        }
    }
}
