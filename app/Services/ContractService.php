<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Repositories\AuditLogRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ContractNotificationRepository;
use App\Repositories\ContractRepository;
use App\Repositories\ContractTemplateRepository;
use App\Repositories\ContractVersionRepository;
use App\Repositories\ProposalRepository;

final class ContractService
{
    public function suggestionForProposal(int $proposalId): array
    {
        $context = $this->proposalContext($proposalId);
        $template = $context['template'];
        if ($template === null) {
            return [
                'available' => false,
                'eligible' => false,
                'reason' => 'Nenhum template ativo de contrato foi configurado.',
                'template' => null,
                'contract' => $this->contractRepo()->findByProposal($proposalId),
            ];
        }

        $evaluation = (new ContractPolicyService())->evaluate($template, $context['proposal'], $context['items'], $context['client']);
        $evaluation['available'] = true;
        $evaluation['template'] = $template;
        $evaluation['contract'] = $this->contractRepo()->findByProposal($proposalId);
        return $evaluation;
    }

    public function syncApprovedProposal(int $proposalId, int $actorId, bool $requiresContract, ?string $signatureMode = null): ?int
    {
        $context = $this->proposalContext($proposalId);
        $proposal = $context['proposal'];
        $template = $context['template'];

        if ($template === null) {
            $this->proposalRepo()->setContractDecision($proposalId, false, null, 'Sem template ativo de contrato.');
            return null;
        }

        $evaluation = (new ContractPolicyService())->evaluate($template, $proposal, $context['items'], $context['client']);
        $reason = $requiresContract
            ? ($evaluation['reason'] ?? 'Contrato solicitado manualmente na aprovacao.')
            : 'Contrato dispensado no fluxo de aprovacao.';
        $this->proposalRepo()->setContractDecision($proposalId, $requiresContract, (int) $template['id'], $reason);

        if (!$requiresContract) {
            return null;
        }

        return $this->generateFromProposal($proposalId, $actorId, [
            'signature_mode' => $signatureMode ?: (string) ($template['signature_mode_default'] ?? 'print'),
            'policy_reason' => $reason,
        ]);
    }

    public function generateFromProposal(int $proposalId, int $actorId, array $options = []): int
    {
        $context = $this->proposalContext($proposalId);
        $proposal = $context['proposal'];
        $template = $context['template'];
        if ($template === null) {
            throw new \RuntimeException('Nenhum template ativo de contrato foi encontrado.');
        }

        $signatureMode = in_array((string) ($options['signature_mode'] ?? ''), ['digital', 'print'], true)
            ? (string) $options['signature_mode']
            : (string) ($template['signature_mode_default'] ?? 'print');
        $needsSignature = (int) ($template['require_signature_default'] ?? 1) === 1 ? 1 : 0;
        $existing = $this->contractRepo()->findByProposal($proposalId);
        $contractId = is_array($existing) ? (int) $existing['id'] : 0;
        $contractNumber = $contractId > 0 ? (string) $existing['contract_number'] : '';

        if ($contractNumber === '') {
            $contractNumber = 'CTR-PROP-' . $proposalId;
        }

        $engine = new ContractTemplateEngine();
        $rendered = $engine->render($template, [
            'proposal' => $proposal,
            'client' => $context['client'],
            'company' => $context['company'],
            'items' => $context['items'],
            'milestones' => $context['milestones'],
            'payment_schedule' => $context['payment_schedule'],
            'contract_number' => $contractNumber,
            'signature_mode_label' => $signatureMode === 'digital' ? 'Assinatura digital' : 'Formalizacao por impressao',
        ]);

        $summaryJson = json_encode([
            'proposal_total' => (float) ($proposal['total'] ?? 0),
            'delivery_start' => (string) ($proposal['delivery_start'] ?? ''),
            'delivery_end' => (string) ($proposal['delivery_end'] ?? ''),
            'signature_mode' => $signatureMode,
        ], JSON_UNESCAPED_UNICODE);

        if ($contractId === 0) {
            $contractId = $this->contractRepo()->create([
                'proposal_id' => (int) $proposal['id'],
                'client_id' => (int) $proposal['client_id'],
                'template_id' => (int) $template['id'],
                'status' => 'rascunho',
                'signature_mode' => $signatureMode,
                'needs_signature' => $needsSignature,
                'contract_number' => '',
                'title' => (string) $rendered['title'],
                'current_version' => 1,
                'current_file_path' => null,
                'rendered_body' => (string) $rendered['body'],
                'rendered_summary' => $summaryJson,
                'source_proposal_snapshot' => json_encode($this->snapshot($context), JSON_UNESCAPED_UNICODE),
                'policy_reason' => (string) ($options['policy_reason'] ?? ''),
                'signature_provider' => null,
                'signature_reference' => null,
                'signature_url' => null,
                'sent_for_signature_at' => null,
                'signed_at' => null,
                'effective_date' => (string) ($proposal['delivery_start'] ?? '') ?: null,
                'expires_at' => (string) ($proposal['delivery_end'] ?? '') ?: null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $contractNumber = 'CTR-' . str_pad((string) $contractId, 6, '0', STR_PAD_LEFT);
        }

        $rendered = $engine->render($template, [
            'proposal' => $proposal,
            'client' => $context['client'],
            'company' => $context['company'],
            'items' => $context['items'],
            'milestones' => $context['milestones'],
            'payment_schedule' => $context['payment_schedule'],
            'contract_number' => $contractNumber,
            'signature_mode_label' => $signatureMode === 'digital' ? 'Assinatura digital' : 'Formalizacao por impressao',
        ]);

        $version = $this->versionRepo()->nextVersion($contractId);
        $filePath = $this->generatePdfFile($contractId, $version, $context['branding'], [
            'title' => (string) $rendered['title'],
            'contract_number' => $contractNumber,
            'current_version' => $version,
        ], (string) $rendered['body'], (string) $rendered['footer']);

        $this->contractRepo()->update($contractId, [
            'template_id' => (int) $template['id'],
            'status' => is_array($existing) ? (string) ($existing['status'] ?? 'rascunho') : 'rascunho',
            'signature_mode' => $signatureMode,
            'needs_signature' => $needsSignature,
            'contract_number' => $contractNumber,
            'title' => (string) $rendered['title'],
            'current_version' => $version,
            'current_file_path' => $filePath,
            'rendered_body' => (string) $rendered['body'],
            'rendered_summary' => $summaryJson,
            'source_proposal_snapshot' => json_encode($this->snapshot($context), JSON_UNESCAPED_UNICODE),
            'policy_reason' => (string) ($options['policy_reason'] ?? ''),
            'signature_provider' => is_array($existing) ? ($existing['signature_provider'] ?? null) : null,
            'signature_reference' => is_array($existing) ? ($existing['signature_reference'] ?? null) : null,
            'signature_url' => is_array($existing) ? ($existing['signature_url'] ?? null) : null,
            'sent_for_signature_at' => is_array($existing) ? ($existing['sent_for_signature_at'] ?? null) : null,
            'signed_at' => is_array($existing) ? ($existing['signed_at'] ?? null) : null,
            'effective_date' => (string) ($proposal['delivery_start'] ?? '') ?: null,
            'expires_at' => (string) ($proposal['delivery_end'] ?? '') ?: null,
            'updated_by' => $actorId,
        ]);

        $this->versionRepo()->create($contractId, $version, [
            'template_snapshot' => json_encode($template, JSON_UNESCAPED_UNICODE),
            'proposal_snapshot' => json_encode($this->snapshot($context), JSON_UNESCAPED_UNICODE),
            'rendered_body' => (string) $rendered['body'],
            'file_path' => $filePath,
            'created_by' => $actorId,
        ]);

        $this->auditRepo()->create('contract', $contractId, is_array($existing) ? 'regenerate' : 'generate', $actorId, [
            'proposal_id' => $proposalId,
            'version' => $version,
            'signature_mode' => $signatureMode,
        ]);

        return $contractId;
    }

    public function sendForSignature(int $contractId, int $actorId, string $basePath): void
    {
        $contract = $this->requireContract($contractId);
        $signatureMode = (string) ($contract['signature_mode'] ?? 'print');
        $providerUrl = trim((string) Config::get('CONTRACT_SIGNATURE_PROVIDER_URL', ''));
        $signatureUrl = $signatureMode === 'digital' && $providerUrl !== ''
            ? rtrim($providerUrl, '/') . '/contracts/' . $contractId
            : rtrim($basePath, '/') . '/contratos/' . $contractId . '/imprimir';

        $payload = [
            'signature_provider' => $signatureMode === 'digital' && $providerUrl !== '' ? 'external' : 'manual',
            'signature_reference' => 'SIG-' . str_pad((string) $contractId, 6, '0', STR_PAD_LEFT),
            'signature_url' => $signatureUrl,
            'sent_for_signature_at' => date('Y-m-d H:i:s'),
        ];
        $this->contractRepo()->updateStatus($contractId, 'pendente_assinatura', $actorId, $payload);

        $this->notificationRepo()->create($contractId, [
            'type' => 'signature_pending',
            'recipient_name' => $contract['client_name'] ?? null,
            'recipient_email' => $this->extractClientEmail($contract),
            'channel' => $signatureMode === 'digital' ? 'email' : 'manual',
            'status' => $signatureMode === 'digital' ? 'pending' : 'skipped',
            'message' => 'Contrato ' . (string) ($contract['contract_number'] ?? '') . ' aguardando formalizacao.',
            'metadata' => json_encode(['signature_url' => $signatureUrl], JSON_UNESCAPED_UNICODE),
            'sent_at' => $signatureMode === 'digital' ? null : date('Y-m-d H:i:s'),
        ]);

        $this->auditRepo()->create('contract', $contractId, 'send_for_signature', $actorId, ['signature_url' => $signatureUrl]);
    }

    public function markSigned(int $contractId, int $actorId): void
    {
        $this->contractRepo()->updateStatus($contractId, 'assinado', $actorId, [
            'signed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->notificationRepo()->create($contractId, [
            'type' => 'status_changed',
            'recipient_name' => null,
            'recipient_email' => null,
            'channel' => 'system',
            'status' => 'sent',
            'message' => 'Contrato marcado como assinado.',
            'metadata' => json_encode(['status' => 'assinado'], JSON_UNESCAPED_UNICODE),
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        $this->auditRepo()->create('contract', $contractId, 'mark_signed', $actorId, []);
    }

    public function markEffective(int $contractId, int $actorId): void
    {
        $this->contractRepo()->updateStatus($contractId, 'vigente', $actorId, [
            'effective_date' => date('Y-m-d'),
        ]);
        $this->auditRepo()->create('contract', $contractId, 'mark_effective', $actorId, []);
    }

    public function normalizeTemplateInput(array $in): array
    {
        $criteria = [
            'enabled' => isset($in['criteria_enabled']),
            'min_total' => Money::parseBRL((string) ($in['criteria_min_total'] ?? '')),
            'required_client_ids' => $this->csvInts((string) ($in['criteria_required_client_ids'] ?? '')),
            'required_service_ids' => $this->csvInts((string) ($in['criteria_required_service_ids'] ?? '')),
            'service_keywords' => $this->csvStrings((string) ($in['criteria_service_keywords'] ?? '')),
        ];

        return [
            'name' => trim((string) ($in['name'] ?? 'Template de Contrato')),
            'description' => ($desc = trim((string) ($in['description'] ?? ''))) !== '' ? $desc : null,
            'is_active' => isset($in['is_active']) ? 1 : 0,
            'auto_criteria_json' => json_encode($criteria, JSON_UNESCAPED_UNICODE),
            'signature_mode_default' => in_array((string) ($in['signature_mode_default'] ?? 'print'), ['digital', 'print'], true) ? (string) $in['signature_mode_default'] : 'print',
            'require_signature_default' => isset($in['require_signature_default']) ? 1 : 0,
            'header_title' => trim((string) ($in['header_title'] ?? 'Contrato de Prestacao de Servicos')),
            'body_template' => trim((string) ($in['body_template'] ?? '')),
            'footer_notes' => ($footer = trim((string) ($in['footer_notes'] ?? ''))) !== '' ? $footer : null,
        ];
    }

    private function proposalContext(int $proposalId): array
    {
        $proposal = $this->proposalRepo()->find($proposalId);
        if (!is_array($proposal)) {
            throw new \RuntimeException('Proposta nao encontrada.');
        }

        $items = $this->proposalRepo()->items($proposalId);
        $milestones = $this->proposalRepo()->milestones($proposalId);
        $client = (new ClientRepository())->find((int) ($proposal['client_id'] ?? 0)) ?? [];
        $company = (new CompanyProfileService())->getCached() ?? [];
        $template = $this->templateRepo()->active();
        $paymentSchedule = [];
        $snap = json_decode((string) ($proposal['payment_snapshot'] ?? ''), true);
        if (is_array($snap)) {
            $paymentSchedule = is_array($snap['schedule'] ?? null) ? $snap['schedule'] : [];
        }

        $branding = (new CompanyProfileService())->branding();

        return [
            'proposal' => $proposal,
            'items' => $items,
            'milestones' => $milestones,
            'client' => $client,
            'company' => $company,
            'template' => $template,
            'payment_schedule' => $paymentSchedule,
            'branding' => $branding,
        ];
    }

    private function snapshot(array $context): array
    {
        return [
            'proposal' => $context['proposal'],
            'client' => $context['client'],
            'company' => $context['company'],
            'items' => $context['items'],
            'milestones' => $context['milestones'],
            'payment_schedule' => $context['payment_schedule'],
        ];
    }

    private function generatePdfFile(int $contractId, int $version, array $branding, array $contractMeta, string $body, string $footer): string
    {
        $bytes = (new ContractPdfGenerator())->build($branding, $contractMeta, $body, $footer);
        $dir = __DIR__ . '/../../storage/pdfs/contracts/' . $contractId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            throw new \RuntimeException('Falha ao preparar diretorio do contrato.');
        }

        $path = $dir . '/contrato-v' . $version . '.pdf';
        if (@file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException('Falha ao salvar PDF do contrato.');
        }
        return $path;
    }

    private function csvInts(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $int = (int) $part;
            if ($int > 0) {
                $out[] = $int;
            }
        }
        return array_values(array_unique($out));
    }

    private function csvStrings(string $raw): array
    {
        $parts = preg_split('/[\n,;]+/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part !== '') {
                $out[] = $part;
            }
        }
        return array_values(array_unique($out));
    }

    private function extractClientEmail(array $contract): ?string
    {
        $snapshot = json_decode((string) ($contract['source_proposal_snapshot'] ?? ''), true);
        $client = is_array($snapshot['client'] ?? null) ? $snapshot['client'] : [];
        $email = trim((string) ($client['email'] ?? ''));
        return $email !== '' ? $email : null;
    }

    private function requireContract(int $id): array
    {
        $row = $this->contractRepo()->find($id);
        if (!is_array($row)) {
            throw new \RuntimeException('Contrato nao encontrado.');
        }
        return $row;
    }

    private function proposalRepo(): ProposalRepository
    {
        return new ProposalRepository();
    }

    private function templateRepo(): ContractTemplateRepository
    {
        return new ContractTemplateRepository();
    }

    private function contractRepo(): ContractRepository
    {
        return new ContractRepository();
    }

    private function versionRepo(): ContractVersionRepository
    {
        return new ContractVersionRepository();
    }

    private function notificationRepo(): ContractNotificationRepository
    {
        return new ContractNotificationRepository();
    }

    private function auditRepo(): AuditLogRepository
    {
        return new AuditLogRepository();
    }
}
