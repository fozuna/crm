<?php
declare(strict_types=1);

namespace App\Services;

use App\Contracts\AuditLogRepositoryContract;
use App\Contracts\ServiceOrderHistoryRepositoryContract;
use App\Contracts\ServiceOrderRepositoryContract;
use App\Core\Config;
use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Repositories\ProposalBrandingRepository;
use App\Repositories\ServiceOrderApprovalEventRepository;
use App\Repositories\ServiceOrderApprovalNotificationRepository;
use App\Repositories\ServiceOrderApprovalRepository;
use App\Repositories\ServiceOrderHistoryRepository;
use App\Repositories\ServiceOrderRepository;

final class ServiceOrderApprovalService
{
    public function __construct(
        private readonly ?ServiceOrderApprovalRepository $approvals = null,
        private readonly ?ServiceOrderApprovalEventRepository $events = null,
        private readonly ?ServiceOrderApprovalNotificationRepository $notifications = null,
        private readonly ?AuditLogRepositoryContract $audit = null,
        private readonly ?ServiceOrderHistoryRepositoryContract $history = null,
        private readonly ?ServiceOrderRepositoryContract $orders = null,
        private readonly ?ServiceOrderApprovalTokenService $tokens = null,
        private readonly ?ServiceOrderApprovalGeoService $geo = null,
        private readonly ?SystemMailer $mailer = null,
        private readonly mixed $transaction = null,
    ) {
    }

    public function generateForServiceOrder(int $serviceOrderId, ?int $actorId): array
    {
        $order = $this->orderRepo()->find($serviceOrderId);
        if ($order === null) {
            throw new \RuntimeException('Ordem de serviço não encontrada.');
        }
        if (!in_array((string) ($order['status'] ?? ''), [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true)) {
            throw new \RuntimeException('O link de aprovação só pode ser gerado para OS concluída ou faturada.');
        }

        $publicId = $this->tokenService()->generatePublicId();
        $expiresAt = (new \DateTimeImmutable())->modify('+' . max(1, (int) Config::get('SERVICE_ORDER_APPROVAL_TTL_HOURS', 72)) . ' hours');
        $token = $this->tokenService()->issue([
            'sub' => 'service-order-approval',
            'public_id' => $publicId,
            'service_order_id' => $serviceOrderId,
            'client_id' => (int) ($order['client_id'] ?? 0),
            'nonce' => bin2hex(random_bytes(8)),
        ], $expiresAt);

        $saved = $this->approvalRepo()->upsertGenerated($serviceOrderId, [
            'public_id' => $publicId,
            'token_hash' => $this->tokenService()->hashToken($token),
            'token_expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'status' => 'pendente',
            'sms_status' => 'enfileirado',
            'sms_message' => 'SMS não configurado no ambiente atual; registro mantido em outbox para ativação posterior.',
        ], $actorId);

        $url = $this->buildApprovalUrl($publicId, $token);
        $this->eventRepo()->create($serviceOrderId, (int) $saved['id'], 'link_gerado', [
            'metadata' => [
                'public_id' => $publicId,
                'expires_at' => $expiresAt->format(DATE_ATOM),
                'generated_by' => $actorId,
            ],
        ]);

        $this->notificationRepo()->create($serviceOrderId, (int) $saved['id'], [
            'channel' => 'sms',
            'notification_type' => 'solicitacao_aprovacao',
            'recipient_name' => (string) ($order['contact_name'] ?? $order['client_contact_person'] ?? $order['client_name'] ?? ''),
            'recipient_target' => (string) ($order['client_billing_phone'] ?? $order['client_phone'] ?? ''),
            'status' => 'enfileirado',
            'subject' => null,
            'message' => 'SMS pendente de integração. Link planejado: ' . $url,
            'metadata' => ['mode' => 'outbox_only'],
            'sent_at' => null,
        ]);
        $this->eventRepo()->create($serviceOrderId, (int) $saved['id'], 'sms_enfileirado', [
            'metadata' => ['reason' => 'canal_sms_nao_configurado'],
        ]);

        $email = $this->sendApprovalRequestEmail($saved, $token, $url);

        $saved = $this->approvalRepo()->findByServiceOrderId($serviceOrderId) ?? $saved;
        $this->historyRepo()->create(
            $serviceOrderId,
            'approval_link_generated',
            $actorId,
            null,
            null,
            $publicId,
            'Link externo de aprovação gerado para o cliente.',
            ['expires_at' => $expiresAt->format(DATE_ATOM)]
        );
        $this->auditRepo()->create('service_order', $serviceOrderId, 'approval_link_generated', $actorId, [
            'approval_id' => $saved['id'] ?? null,
            'public_id' => $publicId,
            'email_status' => $email['status'],
        ]);

        return [
            'approval' => $saved,
            'url' => $url,
            'token' => $token,
            'email' => $email,
        ];
    }

    public function resolvePublicAccess(string $publicId, string $token, array $server): array
    {
        $httpsError = $this->assertHttps($server);
        if ($httpsError !== null) {
            return ['ok' => false, 'message' => $httpsError, 'status_code' => 400];
        }

        $approval = $this->approvalRepo()->findByPublicId($publicId);
        if ($approval === null) {
            return ['ok' => false, 'message' => 'Link de aprovação não encontrado.', 'status_code' => 404];
        }

        $validation = $this->validateAccess($approval, $token, $server);
        if (($validation['ok'] ?? false) !== true) {
            $validation['approval'] = $approval;
            return $validation;
        }

        $accessedAt = date('Y-m-d H:i:s');
        $context = $this->geoService()->resolve($server);
        $this->approvalRepo()->markAccess((int) $approval['id'], $accessedAt);
        $this->eventRepo()->create((int) $approval['servico_avulso_id'], (int) $approval['id'], 'link_acessado', [
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => (string) ($server['HTTP_USER_AGENT'] ?? ''),
            'geo_summary' => $context['summary'] ?? null,
            'metadata' => ['at' => $accessedAt],
        ]);

        $fresh = $this->approvalRepo()->findByPublicId($publicId) ?? $approval;
        return [
            'ok' => true,
            'approval' => $fresh,
            'status_code' => 200,
        ];
    }

    public function decide(string $publicId, string $token, array $input, array $server): array
    {
        $approval = $this->approvalRepo()->findByPublicId($publicId);
        if ($approval === null) {
            throw new \RuntimeException('Link de aprovação não encontrado.');
        }

        $validation = $this->validateAccess($approval, $token, $server);
        if (($validation['ok'] ?? false) !== true) {
            throw new \RuntimeException((string) ($validation['message'] ?? 'Link inválido.'));
        }

        $decision = trim((string) ($input['decision'] ?? ''));
        if (!in_array($decision, ['approve', 'request_adjustments'], true)) {
            throw new \RuntimeException('Ação de aprovação inválida.');
        }

        $requesterName = trim((string) ($input['requester_name'] ?? ''));
        if ($requesterName === '') {
            throw new \RuntimeException('Informe seu nome para registrar a manifestação.');
        }

        $requesterEmail = trim((string) ($input['requester_email'] ?? ''));
        if ($requesterEmail !== '' && !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Informe um e-mail válido.');
        }

        $requesterPhone = trim((string) ($input['requester_phone'] ?? ''));
        $justification = trim((string) ($input['justification'] ?? ''));
        if ($decision === 'request_adjustments' && $justification === '') {
            throw new \RuntimeException('A justificativa é obrigatória ao solicitar ajustes.');
        }

        $context = $this->geoService()->resolve($server);
        $actorIdentifier = $this->geoService()->actorIdentifier($approval, [
            'requester_name' => $requesterName,
            'requester_email' => $requesterEmail,
            'requester_phone' => $requesterPhone,
        ], ['ip' => $context['ip'] ?? '']);

        $status = $decision === 'approve' ? 'aprovada' : 'ajustes_solicitados';
        $actionLabel = $decision === 'approve' ? 'approved' : 'adjustments_requested';
        $serviceOrderId = (int) $approval['servico_avulso_id'];
        $approvalId = (int) $approval['id'];
        $now = date('Y-m-d H:i:s');

        $pdo = is_object($this->transaction) ? $this->transaction : DB::pdo();
        $pdo->beginTransaction();
        try {
            $this->approvalRepo()->recordDecision($approvalId, [
                'status' => $status,
                'requester_name' => $requesterName,
                'requester_email' => $requesterEmail !== '' ? $requesterEmail : $this->clientEmail($approval),
                'requester_phone' => $requesterPhone !== '' ? $requesterPhone : $this->clientPhone($approval),
                'justification' => $justification !== '' ? $justification : null,
                'actor_identifier' => $actorIdentifier,
                'actor_ip' => $context['ip'] ?? null,
                'actor_user_agent' => (string) ($server['HTTP_USER_AGENT'] ?? ''),
                'actor_geo_summary' => $context['summary'] ?? null,
                'actor_geo_json' => json_encode($context['json'] ?? [], JSON_UNESCAPED_UNICODE),
                'token_used_at' => $now,
                'decision_at' => $now,
            ], null);

            $message = $decision === 'approve'
                ? 'Cliente aprovou a ordem de serviço por link externo.'
                : 'Cliente solicitou ajustes na ordem de serviço por link externo.';

            $this->historyRepo()->create(
                $serviceOrderId,
                $actionLabel,
                null,
                'approval_status',
                'pendente',
                $status,
                $message,
                [
                    'actor_identifier' => $actorIdentifier,
                    'requester_name' => $requesterName,
                    'requester_email' => $requesterEmail,
                    'requester_phone' => $requesterPhone,
                    'justification' => $justification,
                    'ip' => $context['ip'] ?? null,
                    'geo_summary' => $context['summary'] ?? null,
                ]
            );

            $this->auditRepo()->create('service_order', $serviceOrderId, $actionLabel, null, [
                'approval_id' => $approvalId,
                'status' => $status,
                'actor_identifier' => $actorIdentifier,
                'ip' => $context['ip'] ?? null,
                'geo' => $context['json'] ?? [],
            ]);

            $this->eventRepo()->create($serviceOrderId, $approvalId, $status === 'aprovada' ? 'aprovada' : 'ajustes_solicitados', [
                'actor_identifier' => $actorIdentifier,
                'ip_address' => $context['ip'] ?? null,
                'user_agent' => (string) ($server['HTTP_USER_AGENT'] ?? ''),
                'geo_summary' => $context['summary'] ?? null,
                'metadata' => [
                    'requester_name' => $requesterName,
                    'requester_email' => $requesterEmail,
                    'requester_phone' => $requesterPhone,
                    'justification' => $justification,
                ],
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $fresh = $this->approvalRepo()->findByServiceOrderId($serviceOrderId);
        if ($fresh === null) {
            throw new \RuntimeException('Falha ao recuperar a aprovação registrada.');
        }

        $proof = null;
        try {
            $proof = $this->generateProofPdf($fresh);
        } catch (\Throwable $e) {
            $this->registerPostDecisionFailure($serviceOrderId, $approvalId, 'approval_proof_error', 'Falha ao gerar o comprovante PDF da aprovação.', $e);
        }

        $notifications = [];
        try {
            $notifications = $this->notifyInternalTeam($fresh, $decision, $justification);
        } catch (\Throwable $e) {
            $this->registerPostDecisionFailure($serviceOrderId, $approvalId, 'approval_internal_notification_error', 'Falha ao notificar a equipe interna sobre a manifestação do cliente.', $e);
        }

        return [
            'approval' => $this->approvalRepo()->findByServiceOrderId($serviceOrderId) ?? $fresh,
            'proof' => $proof,
            'notifications' => $notifications,
        ];
    }

    public function approvalSummaryForOrder(int $serviceOrderId): ?array
    {
        return $this->approvalRepo()->findByServiceOrderId($serviceOrderId);
    }

    public function shouldAutoGenerate(array $before, array $after): bool
    {
        $beforeStatus = (string) ($before['status'] ?? '');
        $afterStatus = (string) ($after['status'] ?? '');
        return !in_array($beforeStatus, [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true)
            && in_array($afterStatus, [ServiceOrderStatus::CONCLUIDO, ServiceOrderStatus::FATURADO], true);
    }

    private function validateAccess(array $approval, string $token, array $server): array
    {
        $payload = $this->tokenService()->validate($token);
        if ($payload === []) {
            $this->blockedEvent($approval, $server, 'token_invalido');
            return ['ok' => false, 'message' => 'Token de aprovação inválido.', 'status_code' => 403];
        }

        if ((string) ($payload['sub'] ?? '') !== 'service-order-approval') {
            $this->blockedEvent($approval, $server, 'token_sub_invalido');
            return ['ok' => false, 'message' => 'Token de aprovação inválido.', 'status_code' => 403];
        }

        if ((string) ($payload['public_id'] ?? '') !== (string) ($approval['public_id'] ?? '')) {
            $this->blockedEvent($approval, $server, 'public_id_divergente');
            return ['ok' => false, 'message' => 'Token de aprovação divergente.', 'status_code' => 403];
        }

        if ((int) ($payload['service_order_id'] ?? 0) !== (int) ($approval['servico_avulso_id'] ?? 0)) {
            $this->blockedEvent($approval, $server, 'service_order_divergente');
            return ['ok' => false, 'message' => 'Token não pertence a esta ordem de serviço.', 'status_code' => 403];
        }

        if (!hash_equals((string) ($approval['token_hash'] ?? ''), $this->tokenService()->hashToken($token))) {
            $this->blockedEvent($approval, $server, 'hash_divergente');
            return ['ok' => false, 'message' => 'Token não autorizado.', 'status_code' => 403];
        }

        $expiresAt = strtotime((string) ($approval['token_expires_at'] ?? ''));
        if ($expiresAt !== false && $expiresAt <= time()) {
            $this->approvalRepo()->markExpired((int) $approval['id']);
            $this->blockedEvent($approval, $server, 'link_expirado');
            return ['ok' => false, 'message' => 'Este link de aprovação expirou.', 'status_code' => 410];
        }

        if ((string) ($approval['status'] ?? '') !== 'pendente' || (string) ($approval['token_used_at'] ?? '') !== '') {
            $this->blockedEvent($approval, $server, 'link_ja_utilizado');
            return ['ok' => false, 'message' => 'Este link de aprovação já foi utilizado.', 'status_code' => 410];
        }

        if ((string) ($approval['token_revoked_at'] ?? '') !== '') {
            $this->blockedEvent($approval, $server, 'link_revogado');
            return ['ok' => false, 'message' => 'Este link de aprovação foi revogado.', 'status_code' => 410];
        }

        return ['ok' => true];
    }

    private function blockedEvent(array $approval, array $server, string $reason): void
    {
        $context = $this->geoService()->resolve($server);
        $this->eventRepo()->create((int) $approval['servico_avulso_id'], (int) $approval['id'], 'acesso_bloqueado', [
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => (string) ($server['HTTP_USER_AGENT'] ?? ''),
            'geo_summary' => $context['summary'] ?? null,
            'metadata' => ['reason' => $reason],
        ]);
    }

    private function sendApprovalRequestEmail(array $approval, string $token, string $url): array
    {
        $serviceOrderId = (int) ($approval['servico_avulso_id'] ?? 0);
        $approvalId = (int) ($approval['id'] ?? 0);
        $recipient = $this->clientEmail($approval);
        $recipientName = trim((string) ($approval['contact_name'] ?? $approval['client_contact_person'] ?? $approval['client_name'] ?? 'Cliente'));
        $subject = 'Aprovação da ordem de serviço ' . (string) ($approval['numero_os'] ?? '');

        if ($recipient === '') {
            $this->notificationRepo()->create($serviceOrderId, $approvalId, [
                'channel' => 'email',
                'notification_type' => 'solicitacao_aprovacao',
                'recipient_name' => $recipientName,
                'recipient_target' => '',
                'status' => 'falhou',
                'subject' => $subject,
                'message' => 'Cliente sem e-mail cadastrado para envio do link de aprovação.',
                'metadata' => ['reason' => 'cliente_sem_email'],
                'sent_at' => null,
            ]);
            $this->eventRepo()->create($serviceOrderId, $approvalId, 'email_falhou', [
                'metadata' => ['reason' => 'cliente_sem_email'],
            ]);
            return ['status' => 'falhou', 'message' => 'Cliente sem e-mail cadastrado.'];
        }

        $body = $this->buildApprovalRequestEmailBody($approval, $url);
        $mail = $this->mailer()->send($recipient, $subject, $body);
        $status = ($mail['ok'] ?? false) ? 'enviado' : 'falhou';
        $sentAt = ($mail['ok'] ?? false) ? date('Y-m-d H:i:s') : null;

        $this->notificationRepo()->create($serviceOrderId, $approvalId, [
            'channel' => 'email',
            'notification_type' => 'solicitacao_aprovacao',
            'recipient_name' => $recipientName,
            'recipient_target' => $recipient,
            'status' => $status,
            'subject' => $subject,
            'message' => $body,
            'metadata' => [
                'public_id' => $approval['public_id'] ?? null,
                'token_hash' => $this->tokenService()->hashToken($token),
                'approval_url' => $url,
                'transport_error' => $mail['error'] ?? null,
            ],
            'sent_at' => $sentAt,
        ]);

        $this->eventRepo()->create($serviceOrderId, $approvalId, ($mail['ok'] ?? false) ? 'email_enviado' : 'email_falhou', [
            'metadata' => ['recipient' => $recipient, 'error' => $mail['error'] ?? null],
        ]);

        if ($mail['ok'] ?? false) {
            $this->approvalRepo()->markEmailSent($approvalId, (string) $sentAt);
        }

        return [
            'status' => $status,
            'message' => $mail['error'] ?? 'E-mail enviado.',
        ];
    }

    private function notifyInternalTeam(array $approval, string $decision, string $justification): array
    {
        $serviceOrderId = (int) $approval['servico_avulso_id'];
        $approvalId = (int) $approval['id'];
        $recipients = [];

        $assignedEmail = trim((string) ($approval['assigned_user_email'] ?? ''));
        if ($assignedEmail !== '' && filter_var($assignedEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[$assignedEmail] = (string) ($approval['assigned_user_name'] ?? 'Responsável interno');
        }

        try {
            $company = (new CompanyProfileService())->branding();
            $companyEmail = trim((string) ($company['company_email'] ?? ''));
            if ($companyEmail !== '' && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                $recipients[$companyEmail] = (string) ($company['company_name'] ?? 'Equipe interna');
            }
        } catch (\Throwable) {
        }

        $subject = 'Cliente respondeu à OS ' . (string) ($approval['numero_os'] ?? '');
        $body = $this->buildInternalNotificationBody($approval, $decision, $justification);

        $results = [];
        if ($recipients === []) {
            $this->notificationRepo()->create($serviceOrderId, $approvalId, [
                'channel' => 'email',
                'notification_type' => 'alerta_interno',
                'recipient_name' => 'Equipe interna',
                'recipient_target' => '',
                'status' => 'ignorado',
                'subject' => $subject,
                'message' => 'Nenhum destinatário interno configurado para alerta de aprovação.',
                'metadata' => ['reason' => 'sem_destinatarios'],
                'sent_at' => null,
            ]);
            $this->eventRepo()->create($serviceOrderId, $approvalId, 'notificacao_interna_falhou', [
                'metadata' => ['reason' => 'sem_destinatarios'],
            ]);
            return [['status' => 'ignorado', 'recipient' => '']];
        }

        foreach ($recipients as $email => $name) {
            $mail = $this->mailer()->send($email, $subject, $body);
            $status = ($mail['ok'] ?? false) ? 'enviado' : 'falhou';
            $sentAt = ($mail['ok'] ?? false) ? date('Y-m-d H:i:s') : null;
            $this->notificationRepo()->create($serviceOrderId, $approvalId, [
                'channel' => 'email',
                'notification_type' => 'alerta_interno',
                'recipient_name' => $name,
                'recipient_target' => $email,
                'status' => $status,
                'subject' => $subject,
                'message' => $body,
                'metadata' => ['transport_error' => $mail['error'] ?? null],
                'sent_at' => $sentAt,
            ]);
            $this->eventRepo()->create($serviceOrderId, $approvalId, ($mail['ok'] ?? false) ? 'notificacao_interna_enviada' : 'notificacao_interna_falhou', [
                'metadata' => ['recipient' => $email, 'error' => $mail['error'] ?? null],
            ]);
            $results[] = ['status' => $status, 'recipient' => $email];
        }

        return $results;
    }

    private function generateProofPdf(array $approval): array
    {
        $branding = [];
        try {
            $branding = (new ProposalBrandingRepository())->get();
        } catch (\Throwable) {
            $branding = [];
        }

        $bytes = (new ServiceOrderApprovalPdfGenerator())->build($branding, $approval, $approval);
        $dir = __DIR__ . '/../../storage/pdfs/service_orders/approvals';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            throw new \RuntimeException('Não foi possível preparar o diretório do comprovante da aprovação.');
        }

        $fileName = 'os-aprovacao-' . (string) ($approval['numero_os'] ?? $approval['servico_avulso_id']) . '-' . date('YmdHis') . '.pdf';
        $filePath = $dir . '/' . preg_replace('/[^a-zA-Z0-9\-_\.]+/', '-', $fileName);
        if (@file_put_contents($filePath, $bytes) === false) {
            throw new \RuntimeException('Falha ao gravar o comprovante PDF da aprovação.');
        }

        $hash = hash_file('sha256', $filePath);
        $generatedAt = date('Y-m-d H:i:s');
        $this->approvalRepo()->attachProof((int) $approval['id'], $filePath, $hash, $generatedAt, null);
        $this->eventRepo()->create((int) $approval['servico_avulso_id'], (int) $approval['id'], 'comprovante_gerado', [
            'metadata' => ['file_path' => $filePath, 'sha256' => $hash],
        ]);

        return ['path' => $filePath, 'sha256' => $hash];
    }

    private function buildApprovalRequestEmailBody(array $approval, string $url): string
    {
        $clientName = trim((string) ($approval['contact_name'] ?? $approval['client_contact_person'] ?? $approval['client_name'] ?? 'Cliente'));
        $company = trim((string) ($approval['client_company'] ?? $approval['client_name'] ?? ''));
        $expiresAt = strtotime((string) ($approval['token_expires_at'] ?? ''));

        return "Olá, {$clientName}.\n\n"
            . "A ordem de serviço " . (string) ($approval['numero_os'] ?? '') . " está disponível para sua manifestação digital.\n\n"
            . "Empresa: {$company}\n"
            . "Serviço: " . (string) ($approval['service_name'] ?? '') . "\n"
            . "Valor final: " . $this->formatMoney((float) ($approval['final_amount'] ?? 0)) . "\n"
            . "Validade do link: " . ($expiresAt !== false ? date('d/m/Y H:i', $expiresAt) : 'não informada') . "\n\n"
            . "Acesse o link seguro abaixo para aprovar a OS ou solicitar ajustes:\n{$url}\n\n"
            . "Este link é exclusivo, possui prazo de validade e só pode ser utilizado uma única vez.\n";
    }

    private function buildInternalNotificationBody(array $approval, string $decision, string $justification): string
    {
        $status = $decision === 'approve' ? 'APROVOU a OS' : 'SOLICITOU AJUSTES na OS';
        $lines = [
            'O cliente ' . (string) ($approval['requester_name'] ?? $approval['client_contact_person'] ?? $approval['client_name'] ?? 'Cliente') . ' ' . $status . '.',
            '',
            'Número da OS: ' . (string) ($approval['numero_os'] ?? ''),
            'Serviço: ' . (string) ($approval['service_name'] ?? ''),
            'Empresa/cliente: ' . (string) (($approval['client_company'] ?? '') !== '' ? $approval['client_company'] : ($approval['client_name'] ?? '')),
            'Data da decisão: ' . (string) ($approval['decision_at'] ?? ''),
            'IP: ' . (string) ($approval['actor_ip'] ?? 'Não informado'),
            'Geolocalização aproximada: ' . (string) ($approval['actor_geo_summary'] ?? 'Não informada'),
            'Identificador do ator: ' . (string) ($approval['actor_identifier'] ?? ''),
        ];
        if ($justification !== '') {
            $lines[] = 'Justificativa: ' . $justification;
        }
        return implode("\n", $lines) . "\n";
    }

    private function buildApprovalUrl(string $publicId, string $token): string
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        if ($base === '') {
            throw new \RuntimeException('APP_URL não configurada para geração do link de aprovação.');
        }
        return $base . '/os/aprovacao/' . rawurlencode($publicId) . '?token=' . rawurlencode($token);
    }

    private function clientEmail(array $approval): string
    {
        $candidates = [
            (string) ($approval['client_billing_email'] ?? ''),
            (string) ($approval['client_email'] ?? ''),
            (string) ($approval['requester_email'] ?? ''),
        ];
        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }
        return '';
    }

    private function clientPhone(array $approval): string
    {
        foreach ([
            (string) ($approval['client_billing_phone'] ?? ''),
            (string) ($approval['client_phone'] ?? ''),
        ] as $candidate) {
            if (trim($candidate) !== '') {
                return trim($candidate);
            }
        }
        return '';
    }

    private function assertHttps(array $server): ?string
    {
        $requireHttps = (bool) Config::get('APPROVAL_REQUIRE_HTTPS', true);
        if (!$requireHttps) {
            return null;
        }

        $host = strtolower(trim((string) ($server['HTTP_HOST'] ?? '')));
        $localHosts = ['localhost', '127.0.0.1'];
        if (in_array($host, $localHosts, true) || str_ends_with($host, '.test')) {
            return null;
        }

        $https = strtolower((string) ($server['HTTPS'] ?? ''));
        $forwarded = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($https === 'on' || $https === '1' || $forwarded === 'https') {
            return null;
        }

        return 'Este link exige HTTPS para exibir os dados da aprovação.';
    }

    private function formatMoney(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    private function registerPostDecisionFailure(int $serviceOrderId, int $approvalId, string $action, string $message, \Throwable $e): void
    {
        try {
            $this->historyRepo()->create(
                $serviceOrderId,
                $action,
                null,
                null,
                null,
                null,
                $message . ' ' . $e->getMessage()
            );
        } catch (\Throwable) {
        }

        try {
            $this->auditRepo()->create('service_order', $serviceOrderId, $action, null, [
                'approval_id' => $approvalId,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable) {
        }
    }

    private function approvalRepo(): ServiceOrderApprovalRepository
    {
        return $this->approvals ?? new ServiceOrderApprovalRepository();
    }

    private function eventRepo(): ServiceOrderApprovalEventRepository
    {
        return $this->events ?? new ServiceOrderApprovalEventRepository();
    }

    private function notificationRepo(): ServiceOrderApprovalNotificationRepository
    {
        return $this->notifications ?? new ServiceOrderApprovalNotificationRepository();
    }

    private function auditRepo(): AuditLogRepositoryContract
    {
        return $this->audit ?? new AuditLogRepository();
    }

    private function historyRepo(): ServiceOrderHistoryRepositoryContract
    {
        return $this->history ?? new ServiceOrderHistoryRepository();
    }

    private function orderRepo(): ServiceOrderRepositoryContract
    {
        return $this->orders ?? new ServiceOrderRepository();
    }

    private function tokenService(): ServiceOrderApprovalTokenService
    {
        return $this->tokens ?? new ServiceOrderApprovalTokenService();
    }

    private function geoService(): ServiceOrderApprovalGeoService
    {
        return $this->geo ?? new ServiceOrderApprovalGeoService();
    }

    private function mailer(): SystemMailer
    {
        return $this->mailer ?? new SystemMailer();
    }
}
