<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

class ServiceOrderApprovalRepository
{
    public function findByServiceOrderId(int $serviceOrderId): ?array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT sa.*,
                    so.numero_os,
                    so.service_name,
                    so.status AS service_order_status,
                    so.client_id,
                    so.contact_name,
                    so.assigned_user_id,
                    so.request_description,
                    so.executed_activities,
                    so.technical_notes,
                    so.final_amount,
                    so.discount_amount,
                    so.surcharge_amount,
                    so.opened_at,
                    so.completed_at,
                    c.name AS client_name,
                    c.company AS client_company,
                    c.email AS client_email,
                    c.billing_email AS client_billing_email,
                    c.phone AS client_phone,
                    c.billing_phone AS client_billing_phone,
                    c.contact_person AS client_contact_person,
                    u.name AS assigned_user_name,
                    u.email AS assigned_user_email
             FROM servicos_avulsos_aprovacoes sa
             INNER JOIN servicos_avulsos so ON so.id = sa.servico_avulso_id
             LEFT JOIN clients c ON c.id = so.client_id
             LEFT JOIN users u ON u.id = so.assigned_user_id
             WHERE sa.servico_avulso_id = :service_order_id
             LIMIT 1'
        );
        $stmt->bindValue(':service_order_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT sa.*,
                    so.numero_os,
                    so.service_name,
                    so.status AS service_order_status,
                    so.client_id,
                    so.contact_name,
                    so.assigned_user_id,
                    so.request_description,
                    so.executed_activities,
                    so.technical_notes,
                    so.final_amount,
                    so.discount_amount,
                    so.surcharge_amount,
                    so.opened_at,
                    so.completed_at,
                    c.name AS client_name,
                    c.company AS client_company,
                    c.email AS client_email,
                    c.billing_email AS client_billing_email,
                    c.phone AS client_phone,
                    c.billing_phone AS client_billing_phone,
                    c.contact_person AS client_contact_person,
                    u.name AS assigned_user_name,
                    u.email AS assigned_user_email
             FROM servicos_avulsos_aprovacoes sa
             INNER JOIN servicos_avulsos so ON so.id = sa.servico_avulso_id
             LEFT JOIN clients c ON c.id = so.client_id
             LEFT JOIN users u ON u.id = so.assigned_user_id
             WHERE sa.public_id = :public_id
             LIMIT 1'
        );
        $stmt->bindValue(':public_id', $publicId);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function upsertGenerated(int $serviceOrderId, array $payload, ?int $actorId): array
    {
        $existing = $this->findByServiceOrderId($serviceOrderId);
        if ($existing === null) {
            $stmt = DB::pdo()->prepare(
                'INSERT INTO servicos_avulsos_aprovacoes (
                    servico_avulso_id,
                    public_id,
                    token_hash,
                    token_expires_at,
                    token_used_at,
                    token_last_access_at,
                    token_revoked_at,
                    status,
                    requester_name,
                    requester_email,
                    requester_phone,
                    justification,
                    actor_identifier,
                    actor_ip,
                    actor_user_agent,
                    actor_geo_summary,
                    actor_geo_json,
                    first_access_at,
                    decision_at,
                    proof_pdf_path,
                    proof_pdf_hash,
                    proof_pdf_generated_at,
                    email_sent_at,
                    sms_status,
                    sms_message,
                    created_by,
                    updated_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :service_order_id,
                    :public_id,
                    :token_hash,
                    :token_expires_at,
                    NULL,
                    NULL,
                    NULL,
                    :status,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    :sms_status,
                    :sms_message,
                    :created_by,
                    :updated_by,
                    NOW(),
                    NOW()
                )'
            );
            $stmt->bindValue(':service_order_id', $serviceOrderId, \PDO::PARAM_INT);
            $stmt->bindValue(':public_id', (string) $payload['public_id']);
            $stmt->bindValue(':token_hash', (string) $payload['token_hash']);
            $stmt->bindValue(':token_expires_at', (string) $payload['token_expires_at']);
            $stmt->bindValue(':status', (string) ($payload['status'] ?? 'pendente'));
            $stmt->bindValue(':sms_status', (string) ($payload['sms_status'] ?? 'indisponivel'));
            $stmt->bindValue(':sms_message', (string) ($payload['sms_message'] ?? ''), \PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':updated_by', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $stmt = DB::pdo()->prepare(
                'UPDATE servicos_avulsos_aprovacoes SET
                    public_id = :public_id,
                    token_hash = :token_hash,
                    token_expires_at = :token_expires_at,
                    token_used_at = NULL,
                    token_last_access_at = NULL,
                    token_revoked_at = NULL,
                    status = :status,
                    requester_name = NULL,
                    requester_email = NULL,
                    requester_phone = NULL,
                    justification = NULL,
                    actor_identifier = NULL,
                    actor_ip = NULL,
                    actor_user_agent = NULL,
                    actor_geo_summary = NULL,
                    actor_geo_json = NULL,
                    first_access_at = NULL,
                    decision_at = NULL,
                    proof_pdf_path = NULL,
                    proof_pdf_hash = NULL,
                    proof_pdf_generated_at = NULL,
                    email_sent_at = NULL,
                    sms_status = :sms_status,
                    sms_message = :sms_message,
                    updated_by = :updated_by,
                    updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->bindValue(':id', (int) $existing['id'], \PDO::PARAM_INT);
            $stmt->bindValue(':public_id', (string) $payload['public_id']);
            $stmt->bindValue(':token_hash', (string) $payload['token_hash']);
            $stmt->bindValue(':token_expires_at', (string) $payload['token_expires_at']);
            $stmt->bindValue(':status', (string) ($payload['status'] ?? 'pendente'));
            $stmt->bindValue(':sms_status', (string) ($payload['sms_status'] ?? 'indisponivel'));
            $stmt->bindValue(':sms_message', (string) ($payload['sms_message'] ?? ''), \PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->execute();
        }

        $saved = $this->findByServiceOrderId($serviceOrderId);
        if ($saved === null) {
            throw new \RuntimeException('Falha ao persistir aprovação da ordem de serviço.');
        }
        return $saved;
    }

    public function markAccess(int $approvalId, string $accessedAt): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE servicos_avulsos_aprovacoes SET
                token_last_access_at = :accessed_at,
                first_access_at = COALESCE(first_access_at, :accessed_at),
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $approvalId, \PDO::PARAM_INT);
        $stmt->bindValue(':accessed_at', $accessedAt);
        $stmt->execute();
    }

    public function markEmailSent(int $approvalId, string $sentAt): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE servicos_avulsos_aprovacoes SET
                email_sent_at = :sent_at,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $approvalId, \PDO::PARAM_INT);
        $stmt->bindValue(':sent_at', $sentAt);
        $stmt->execute();
    }

    public function recordDecision(int $approvalId, array $payload, ?int $actorId): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE servicos_avulsos_aprovacoes SET
                status = :status,
                requester_name = :requester_name,
                requester_email = :requester_email,
                requester_phone = :requester_phone,
                justification = :justification,
                actor_identifier = :actor_identifier,
                actor_ip = :actor_ip,
                actor_user_agent = :actor_user_agent,
                actor_geo_summary = :actor_geo_summary,
                actor_geo_json = :actor_geo_json,
                token_used_at = :token_used_at,
                decision_at = :decision_at,
                updated_by = :updated_by,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $approvalId, \PDO::PARAM_INT);
        $stmt->bindValue(':status', (string) $payload['status']);
        $this->bindNullable($stmt, ':requester_name', $payload['requester_name'] ?? null);
        $this->bindNullable($stmt, ':requester_email', $payload['requester_email'] ?? null);
        $this->bindNullable($stmt, ':requester_phone', $payload['requester_phone'] ?? null);
        $this->bindNullable($stmt, ':justification', $payload['justification'] ?? null);
        $this->bindNullable($stmt, ':actor_identifier', $payload['actor_identifier'] ?? null);
        $this->bindNullable($stmt, ':actor_ip', $payload['actor_ip'] ?? null);
        $this->bindNullable($stmt, ':actor_user_agent', $payload['actor_user_agent'] ?? null);
        $this->bindNullable($stmt, ':actor_geo_summary', $payload['actor_geo_summary'] ?? null);
        $this->bindNullable($stmt, ':actor_geo_json', $payload['actor_geo_json'] ?? null);
        $stmt->bindValue(':token_used_at', (string) $payload['token_used_at']);
        $stmt->bindValue(':decision_at', (string) $payload['decision_at']);
        $stmt->bindValue(':updated_by', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function markExpired(int $approvalId): void
    {
        $stmt = DB::pdo()->prepare(
            "UPDATE servicos_avulsos_aprovacoes
             SET status = 'expirada', updated_at = NOW()
             WHERE id = :id AND status = 'pendente'"
        );
        $stmt->bindValue(':id', $approvalId, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function attachProof(int $approvalId, string $path, string $hash, string $generatedAt, ?int $actorId): void
    {
        $stmt = DB::pdo()->prepare(
            'UPDATE servicos_avulsos_aprovacoes SET
                proof_pdf_path = :path,
                proof_pdf_hash = :hash,
                proof_pdf_generated_at = :generated_at,
                updated_by = :updated_by,
                updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->bindValue(':id', $approvalId, \PDO::PARAM_INT);
        $stmt->bindValue(':path', $path);
        $stmt->bindValue(':hash', $hash);
        $stmt->bindValue(':generated_at', $generatedAt);
        $stmt->bindValue(':updated_by', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();
    }

    private function bindNullable(\PDOStatement $stmt, string $param, mixed $value): void
    {
        $stmt->bindValue($param, $value, $value === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
    }
}
