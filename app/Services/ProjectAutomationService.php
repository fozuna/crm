<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\ProjectEventRepository;
use App\Repositories\ProjectMilestoneRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectStatusHistoryRepository;
use App\Repositories\ProjectTaskRepository;
use App\Repositories\ProposalRepository;
use App\Services\FinanceTrace;

final class ProjectAutomationService
{
    private ProposalRepository $proposals;
    private ProjectRepository $projects;
    private ProjectTaskRepository $tasks;
    private ProjectMilestoneRepository $milestones;
    private FinanceInstallmentRepository $installments;
    private ProjectStatusHistoryRepository $history;
    private ProjectEventRepository $events;
    private AuditLogRepository $audit;

    public function __construct()
    {
        $this->proposals = new ProposalRepository();
        $this->projects = new ProjectRepository();
        $this->tasks = new ProjectTaskRepository();
        $this->milestones = new ProjectMilestoneRepository();
        $this->installments = new FinanceInstallmentRepository();
        $this->history = new ProjectStatusHistoryRepository();
        $this->events = new ProjectEventRepository();
        $this->audit = new AuditLogRepository();
    }

    public function createFromApprovedProposal(int $proposalId, int $actorId): int
    {
        $proposal = $this->proposals->find($proposalId);
        if ($proposal === null) {
            throw new \RuntimeException('Proposta não encontrada.');
        }
        if ((string) ($proposal['status'] ?? '') !== 'aprovada') {
            throw new \RuntimeException('Apenas propostas aprovadas podem virar projeto.');
        }
        if ((int) ($proposal['converted_project'] ?? 0) === 1) {
            $existing = $this->projects->findByProposal($proposalId);
            if (is_array($existing)) {
                return (int) $existing['id'];
            }
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $projectId = $this->projects->createFromProposal($proposal, $actorId);

            $this->history->create($projectId, null, 'planejamento', 'Criação automática a partir da proposta.', $actorId);
            $this->events->create($projectId, 'created', 'Projeto criado a partir da proposta #' . $proposalId, ['proposal_id' => $proposalId], $actorId);

            $items = $this->proposals->items($proposalId);
            $pMilestones = $this->proposals->milestones($proposalId);

            foreach ($pMilestones as $m) {
                $this->milestones->create(
                    $projectId,
                    (string) ($m['title'] ?? ''),
                    ($m['due_date'] ?? null) !== null ? (string) $m['due_date'] : null,
                    ($m['notes'] ?? null) !== null ? (string) $m['notes'] : null
                );
            }

            $order = 10;
            foreach (['Kickoff e alinhamento', 'Plano e cronograma', 'Definição de escopo e requisitos'] as $t) {
                $this->tasks->create($projectId, 'planejamento', $t, null, $actorId, null, $order);
                $order += 10;
            }

            $order = 10;
            foreach ($items as $it) {
                $desc = trim((string) ($it['description'] ?? ''));
                if ($desc === '') {
                    continue;
                }
                $this->tasks->create($projectId, 'execucao', 'Execução: ' . $desc, null, $actorId, null, $order);
                $order += 10;
            }

            $order = 10;
            foreach ($pMilestones as $m) {
                $title = trim((string) ($m['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $due = ($m['due_date'] ?? null) !== null ? (string) $m['due_date'] : null;
                $this->tasks->create($projectId, 'acompanhamento', 'Acompanhamento: ' . $title, null, $actorId, $due, $order);
                $order += 10;
            }
            if (count($pMilestones) === 0) {
                $this->tasks->create($projectId, 'acompanhamento', 'Reuniões de acompanhamento', null, $actorId, null, 10);
            }

            $this->tasks->create($projectId, 'entrega', 'Entrega e homologação', null, $actorId, null, 10);
            $this->tasks->create($projectId, 'entrega', 'Documentação e treinamento', null, $actorId, null, 20);
            $this->tasks->create($projectId, 'pos_venda', 'Pós-venda e suporte inicial', null, $actorId, null, 10);

            $this->generateInstallments($proposal, $projectId);
            (new FinancialReceivableService())->generateFromProject($projectId, $actorId);
            $this->proposals->markConverted($proposalId);

            $this->audit->create('proposal', $proposalId, 'convert_to_project', $actorId, ['project_id' => $projectId]);
            $this->audit->create('project', $projectId, 'created_from_proposal', $actorId, ['proposal_id' => $proposalId]);

            $this->recalcProgress($projectId);

            $pdo->commit();
            return $projectId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function recalcProgress(int $projectId): float
    {
        $stats = $this->tasks->statsByProject($projectId);
        $total = (int) ($stats['total'] ?? 0);
        $done = (int) ($stats['done'] ?? 0);
        $pct = $total <= 0 ? 0.0 : round(($done / $total) * 100, 2);
        $this->projects->updateProgress($projectId, $pct);
        return $pct;
    }

    private function generateInstallments(array $proposal, int $projectId): void
    {
        $proposalId = (int) ($proposal['id'] ?? 0);
        $total = (float) ($proposal['total'] ?? 0);

        FinanceTrace::log('project.generate_installments.start', ['proposal_id' => $proposalId, 'project_id' => $projectId, 'proposal_total' => $total]);

        $schedule = null;
        $optJson = (string) ($proposal['payment_options'] ?? '');
        $idx = (int) ($proposal['payment_selected_index'] ?? 0);
        $decoded = $optJson !== '' ? json_decode($optJson, true) : null;
        if (is_array($decoded) && isset($decoded[$idx]) && is_array($decoded[$idx])) {
            $snap = $decoded[$idx]['snapshot'] ?? null;
            if (is_array($snap) && is_array($snap['schedule'] ?? null)) {
                $schedule = $snap['schedule'];
            }
        }

        if (!is_array($schedule)) {
            $snapJson = (string) ($proposal['payment_snapshot'] ?? '');
            $snapDecoded = $snapJson !== '' ? json_decode($snapJson, true) : null;
            if (is_array($snapDecoded) && is_array($snapDecoded['schedule'] ?? null)) {
                $schedule = $snapDecoded['schedule'];
            }
        }

        if (is_array($schedule) && count($schedule) > 0) {
            $no = 1;
            foreach ($schedule as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $amount = (float) ($row['amount'] ?? 0);
                $due = (string) ($row['due_date'] ?? '');
                if ($amount <= 0) {
                    continue;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) !== 1) {
                    $due = date('Y-m-d', strtotime('+7 days'));
                }
                $this->installments->create($proposalId, $projectId, $no, $amount, $due);
                $no++;
            }
            FinanceTrace::log('project.generate_installments.done', ['proposal_id' => $proposalId, 'project_id' => $projectId, 'count' => $no - 1]);
            return;
        }

        $this->installments->create($proposalId, $projectId, 1, $total, date('Y-m-d', strtotime('+7 days')));
        FinanceTrace::log('project.generate_installments.fallback', ['proposal_id' => $proposalId, 'project_id' => $projectId, 'count' => 1]);
    }
}
