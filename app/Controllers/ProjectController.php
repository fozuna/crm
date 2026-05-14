<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\ProjectEventRepository;
use App\Repositories\ProjectMilestoneRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectStatusHistoryRepository;
use App\Repositories\ProjectTaskRepository;
use App\Repositories\UserListRepository;
use App\Services\ProjectAutomationService;

final class ProjectController
{
    public function index(Request $request): void
    {
        $filters = [
            'status' => (string) $request->input('status', ''),
            'workflow_phase' => (string) $request->input('workflow_phase', ''),
            'client_id' => (int) $request->input('client_id', 0),
            'owner_user_id' => (int) $request->input('owner_user_id', 0),
        ];
        $projects = (new ProjectRepository())->list($filters);
        $users = (new UserListRepository())->all();
        View::render('projects/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'projects' => $projects,
            'filters' => $filters,
            'users' => $users,
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $project = (new ProjectRepository())->find($id);
        if ($project === null) {
            http_response_code(404);
            echo 'Projeto não encontrado.';
            return;
        }

        $tasks = (new ProjectTaskRepository())->listByProject($id);
        $milestones = (new ProjectMilestoneRepository())->listByProject($id);
        $events = (new ProjectEventRepository())->listByProject($id);
        $history = (new ProjectStatusHistoryRepository())->listByProject($id);
        $installments = (new FinanceInstallmentRepository())->listByProject($id);

        $actorIds = [];
        foreach ($events as $e) {
            $aid = (int) ($e['created_by'] ?? 0);
            if ($aid > 0) {
                $actorIds[] = $aid;
            }
        }
        foreach ($history as $h) {
            $aid = (int) ($h['actor_id'] ?? 0);
            if ($aid > 0) {
                $actorIds[] = $aid;
            }
        }
        $names = (new UserListRepository())->namesByIds($actorIds);

        View::render('projects/show', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'project' => $project,
            'tasks' => $tasks,
            'milestones' => $milestones,
            'events' => $events,
            'history' => $history,
            'installments' => $installments,
            'actorNames' => $names,
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $project = (new ProjectRepository())->find($id);
        if ($project === null) {
            http_response_code(404);
            echo 'Projeto não encontrado.';
            return;
        }

        $users = (new UserListRepository())->all();
        View::render('projects/edit', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'project' => $project,
            'users' => $users,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $project = (new ProjectRepository())->find($id);
        if ($project === null) {
            http_response_code(404);
            return;
        }

        $title = trim((string) $request->input('title', ''));
        $desc = trim((string) $request->input('description', ''));
        $owner = (int) $request->input('owner_user_id', 0);
        $status = trim((string) $request->input('status', 'ativo'));
        $start = trim((string) $request->input('start_date', ''));
        $end = trim((string) $request->input('end_date', ''));

        if ($title === '' || !in_array($status, ['ativo', 'pausado', 'finalizado', 'cancelado'], true)) {
            Response::redirect($request->basePath() . '/projetos/' . $id . '/editar');
        }

        $pdo = \App\Core\DB::pdo();
        $stmt = $pdo->prepare('UPDATE projects SET title = :t, description = :d, owner_user_id = :o, status = :s, start_date = :sd, end_date = :ed, updated_at = NOW() WHERE id = :id');
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':t', $title);
        $stmt->bindValue(':d', $desc === '' ? null : $desc);
        $stmt->bindValue(':o', $owner > 0 ? $owner : null, $owner > 0 ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $stmt->bindValue(':s', $status);
        $stmt->bindValue(':sd', preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1 ? $start : null);
        $stmt->bindValue(':ed', preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) === 1 ? $end : null);
        $stmt->execute();

        Response::redirect($request->basePath() . '/projetos/' . $id);
    }

    public function updateTask(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $taskId = (int) ($params['taskId'] ?? 0);
        $status = trim((string) $request->input('status', ''));
        if (!in_array($status, ['pendente', 'em_andamento', 'concluida', 'cancelada'], true)) {
            Response::redirect($request->basePath() . '/projetos/' . $projectId);
        }
        (new ProjectTaskRepository())->updateStatus($taskId, $status);
        (new ProjectAutomationService())->recalcProgress($projectId);
        Response::redirect($request->basePath() . '/projetos/' . $projectId);
    }

    public function addTask(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $phase = trim((string) $request->input('phase', ''));
        $title = trim((string) $request->input('title', ''));
        if (!in_array($phase, ['planejamento', 'execucao', 'acompanhamento', 'entrega', 'pos_venda'], true) || $title === '') {
            Response::redirect($request->basePath() . '/projetos/' . $projectId);
        }
        $actorId = (int) Session::get('user_id', 0);
        (new ProjectTaskRepository())->create($projectId, $phase, $title, null, $actorId > 0 ? $actorId : null, null, 999);
        (new ProjectAutomationService())->recalcProgress($projectId);
        Response::redirect($request->basePath() . '/projetos/' . $projectId);
    }

    public function deleteTask(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $taskId = (int) ($params['taskId'] ?? 0);
        (new ProjectTaskRepository())->delete($taskId);
        (new ProjectAutomationService())->recalcProgress($projectId);
        Response::redirect($request->basePath() . '/projetos/' . $projectId);
    }

    public function addMilestone(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $title = trim((string) $request->input('title', ''));
        $due = trim((string) $request->input('due_date', ''));
        if ($title === '') {
            Response::redirect($request->basePath() . '/projetos/' . $projectId);
        }
        $due = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) === 1 ? $due : null;
        (new ProjectMilestoneRepository())->create($projectId, $title, $due, null);
        Response::redirect($request->basePath() . '/projetos/' . $projectId);
    }

    public function deleteMilestone(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $mid = (int) ($params['milestoneId'] ?? 0);
        (new ProjectMilestoneRepository())->delete($mid);
        Response::redirect($request->basePath() . '/projetos/' . $projectId);
    }
}
