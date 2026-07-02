<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\DB;

final class DashboardRepository
{
    public function stats(): array
    {
        $pdo = DB::pdo();

        $approved = (int) $pdo->query("SELECT COUNT(*) FROM proposals WHERE status = 'aprovada'")->fetchColumn();
        $activeProjects = (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'ativo'")->fetchColumn();
        $receivable = 0.0;
        try {
            $hasPaidAmount = (bool) $pdo->query("SHOW COLUMNS FROM finance_installments LIKE 'paid_amount'")->fetchColumn();
            if ($hasPaidAmount) {
                $receivable = (float) $pdo->query("SELECT COALESCE(SUM(amount - paid_amount),0) FROM finance_installments WHERE status IN ('pendente','reaberto')")->fetchColumn();
            } else {
                $sql = "SELECT COALESCE(SUM(fi.amount - COALESCE(p.paid,0)),0)
                        FROM finance_installments fi
                        LEFT JOIN (
                          SELECT installment_id, SUM(amount) AS paid
                          FROM finance_payments
                          GROUP BY installment_id
                        ) p ON p.installment_id = fi.id
                        WHERE fi.status IN ('pendente','reaberto')";
                $receivable = (float) $pdo->query($sql)->fetchColumn();
            }
        } catch (\Throwable) {
            $receivable = 0.0;
        }

        return [
            'approved_proposals' => $approved,
            'active_projects' => $activeProjects,
            'receivable' => $receivable,
            'service_orders_open' => (int) $pdo->query("SELECT COUNT(*) FROM servicos_avulsos WHERE deleted_at IS NULL AND status = 'aberto'")->fetchColumn(),
            'service_orders_progress' => (int) $pdo->query("SELECT COUNT(*) FROM servicos_avulsos WHERE deleted_at IS NULL AND status = 'em_andamento'")->fetchColumn(),
            'service_orders_done' => (int) $pdo->query("SELECT COUNT(*) FROM servicos_avulsos WHERE deleted_at IS NULL AND status = 'concluido'")->fetchColumn(),
            'service_orders_billed' => (int) $pdo->query("SELECT COUNT(*) FROM servicos_avulsos WHERE deleted_at IS NULL AND status = 'faturado'")->fetchColumn(),
            'service_orders_billed_month' => (float) $pdo->query("SELECT COALESCE(SUM(final_amount), 0) FROM servicos_avulsos WHERE deleted_at IS NULL AND status = 'faturado' AND completed_at IS NOT NULL AND DATE_FORMAT(completed_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn(),
            'service_orders_avg_hours' => (float) $pdo->query("SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, opened_at, completed_at)), 0) FROM servicos_avulsos WHERE deleted_at IS NULL AND completed_at IS NOT NULL AND status IN ('concluido', 'faturado')")->fetchColumn(),
        ];
    }
}
