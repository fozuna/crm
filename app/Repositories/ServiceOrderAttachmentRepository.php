<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ServiceOrderAttachmentRepositoryContract;
use App\Core\DB;

final class ServiceOrderAttachmentRepository implements ServiceOrderAttachmentRepositoryContract
{
    public function create(int $serviceOrderId, array $data, ?int $actorId): int
    {
        $stmt = DB::pdo()->prepare(
            'INSERT INTO servicos_avulsos_anexos (
                servico_avulso_id, original_name, stored_name, file_path, file_extension,
                file_size, mime_type, uploaded_by, created_at
            ) VALUES (
                :servico_avulso_id, :original_name, :stored_name, :file_path, :file_extension,
                :file_size, :mime_type, :uploaded_by, NOW()
            )'
        );
        $stmt->bindValue(':servico_avulso_id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->bindValue(':original_name', (string) ($data['original_name'] ?? ''));
        $stmt->bindValue(':stored_name', (string) ($data['stored_name'] ?? ''));
        $stmt->bindValue(':file_path', (string) ($data['file_path'] ?? ''));
        $stmt->bindValue(':file_extension', (string) ($data['file_extension'] ?? ''));
        $stmt->bindValue(':file_size', (int) ($data['file_size'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':mime_type', (string) ($data['mime_type'] ?? 'application/octet-stream'));
        $stmt->bindValue(':uploaded_by', $actorId, $actorId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->execute();

        return (int) DB::pdo()->lastInsertId();
    }

    public function listByServiceOrder(int $serviceOrderId): array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT a.*, u.name AS uploaded_by_name
             FROM servicos_avulsos_anexos a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.servico_avulso_id = :id
             ORDER BY a.id DESC'
        );
        $stmt->bindValue(':id', $serviceOrderId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = DB::pdo()->prepare(
            'SELECT a.*, u.name AS uploaded_by_name
             FROM servicos_avulsos_anexos a
             LEFT JOIN users u ON u.id = a.uploaded_by
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function delete(int $id): void
    {
        $stmt = DB::pdo()->prepare('DELETE FROM servicos_avulsos_anexos WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
    }
}
