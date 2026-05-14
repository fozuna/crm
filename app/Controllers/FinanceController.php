<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\FinancePaymentRepository;
use App\Repositories\FinanceCancellationRepository;
use App\Repositories\ProjectRepository;
use App\Services\FinanceService;
use App\Services\Money;

final class FinanceController
{
    public function project(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $project = (new ProjectRepository())->find($id);
        if ($project === null) {
            http_response_code(404);
            echo 'Projeto não encontrado.';
            return;
        }
        $installments = (new FinanceInstallmentRepository())->listByProject($id);
        $requests = (new FinanceCancellationRepository())->listPendingByProject($id);
        $isAdmin = (int) Session::get('is_admin', 0) === 1;
        $today = date('Y-m-d');
        foreach ($installments as &$r) {
            $status = (string) ($r['status'] ?? '');
            $due = (string) ($r['due_date'] ?? '');
            $r['is_overdue'] = ($status !== 'pago' && $status !== 'cancelado' && $due !== '' && $due < $today);
            $r['open_amount'] = max(0, (float) ($r['amount'] ?? 0) - (float) ($r['paid_amount'] ?? 0));
        }

        $ok = trim((string) $request->input('ok', ''));
        $error = trim((string) $request->input('error', ''));
        View::render('projects/finance', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'project' => $project,
            'installments' => $installments,
            'requests' => $requests,
            'isAdmin' => $isAdmin,
            'ok' => $ok,
            'error' => $error,
        ]);
    }

    public function pay(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $installmentId = (int) ($params['installmentId'] ?? 0);
        $amount = Money::parseBRL((string) $request->input('amount', '0'));
        $method = trim((string) $request->input('method', ''));
        $reference = trim((string) $request->input('reference', ''));
        $note = trim((string) $request->input('note', ''));
        $actorId = (int) Session::get('user_id', 0);
        $error = '';
        try {
            (new FinanceService())->addPayment($installmentId, $amount, $method !== '' ? $method : null, $reference !== '' ? $reference : null, $note !== '' ? $note : null, $actorId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $to = $request->basePath() . '/projetos/' . $projectId . '/financeiro';
        if ($error !== '') {
            $to .= '?error=' . rawurlencode($error);
        } else {
            $to .= '?ok=Pagamento registrado';
        }
        Response::redirect($to);
    }

    public function cancel(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $installmentId = (int) ($params['installmentId'] ?? 0);
        $reason = trim((string) $request->input('reason', ''));
        $actorId = (int) Session::get('user_id', 0);
        $isAdmin = (int) Session::get('is_admin', 0) === 1;
        $error = '';
        try {
            (new FinanceService())->cancelInstallment($installmentId, $reason, $actorId, $isAdmin);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $to = $request->basePath() . '/projetos/' . $projectId . '/financeiro';
        if ($error !== '') {
            $to .= '?error=' . rawurlencode($error);
        } else {
            $to .= '?ok=' . rawurlencode($isAdmin ? 'Parcela cancelada' : 'Solicitação de cancelamento criada');
        }
        Response::redirect($to);
    }

    public function approveCancel(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $requestId = (int) ($params['requestId'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        $error = '';
        try {
            (new FinanceService())->approveCancellationRequest($requestId, $actorId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $to = $request->basePath() . '/projetos/' . $projectId . '/financeiro';
        if ($error !== '') {
            $to .= '?error=' . rawurlencode($error);
        } else {
            $to .= '?ok=Cancelamento aprovado';
        }
        Response::redirect($to);
    }

    public function rejectCancel(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $requestId = (int) ($params['requestId'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        $error = '';
        try {
            (new FinanceService())->rejectCancellationRequest($requestId, $actorId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $to = $request->basePath() . '/projetos/' . $projectId . '/financeiro';
        if ($error !== '') {
            $to .= '?error=' . rawurlencode($error);
        } else {
            $to .= '?ok=Cancelamento rejeitado';
        }
        Response::redirect($to);
    }


    public function reopen(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $installmentId = (int) ($params['installmentId'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        $error = '';
        try {
            (new FinanceService())->reopenInstallment($installmentId, $actorId);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $to = $request->basePath() . '/projetos/' . $projectId . '/financeiro';
        if ($error !== '') {
            $to .= '?error=' . rawurlencode($error);
        } else {
            $to .= '?ok=Parcela reaberta';
        }
        Response::redirect($to);
    }
}
