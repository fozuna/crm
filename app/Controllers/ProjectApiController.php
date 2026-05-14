<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\ProjectMilestoneRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectStatusHistoryRepository;
use App\Repositories\ProjectTaskRepository;
use App\Services\ProjectAutomationService;

final class ProjectApiController
{
    public function index(Request $request): void
    {
        $filters = [
            'status' => (string) $request->input('status', ''),
            'workflow_phase' => (string) $request->input('workflow_phase', ''),
            'client_id' => (int) $request->input('client_id', 0),
            'owner_user_id' => (int) $request->input('owner_user_id', 0),
        ];
        $rows = (new ProjectRepository())->list($filters);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $project = (new ProjectRepository())->find($id);
        if ($project === null) {
            Response::json(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
        }

        $tasks = (new ProjectTaskRepository())->listByProject($id);
        $milestones = (new ProjectMilestoneRepository())->listByProject($id);
        $history = (new ProjectStatusHistoryRepository())->listByProject($id);

        Response::json([
            'ok' => true,
            'data' => [
                'project' => $project,
                'tasks' => $tasks,
                'milestones' => $milestones,
                'history' => $history,
            ],
        ]);
    }

    public function convertFromProposal(Request $request, array $params): void
    {
        $proposalId = (int) ($params['proposalId'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            $projectId = (new ProjectAutomationService())->createFromApprovedProposal($proposalId, $actorId);
            Response::json(['ok' => true, 'data' => ['project_id' => $projectId]]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function recalcProgress(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        try {
            $pct = (new ProjectAutomationService())->recalcProgress($id);
            Response::json(['ok' => true, 'data' => ['progress_percent' => $pct]]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function updateTaskStatus(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $taskId = (int) ($params['taskId'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        if (!in_array($status, ['pendente', 'em_andamento', 'concluida', 'cancelada'], true)) {
            Response::json(['ok' => false, 'error' => 'Status inválido.'], 422);
        }

        $repo = new ProjectRepository();
        $project = $repo->find($projectId);
        if ($project === null) {
            Response::json(['ok' => false, 'error' => 'Projeto não encontrado.'], 404);
        }

        (new ProjectTaskRepository())->updateStatus($taskId, $status);
        $pct = (new ProjectAutomationService())->recalcProgress($projectId);
        Response::json(['ok' => true, 'data' => ['progress_percent' => $pct]]);
    }
}

