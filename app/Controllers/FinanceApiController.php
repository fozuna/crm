<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\FinancePaymentRepository;
use App\Services\FinanceService;
use App\Services\Money;

final class FinanceApiController
{
    public function listProjectInstallments(Request $request, array $params): void
    {
        $projectId = (int) ($params['id'] ?? 0);
        $rows = (new FinanceInstallmentRepository())->listByProject($projectId);
        $today = date('Y-m-d');
        foreach ($rows as &$r) {
            $status = (string) ($r['status'] ?? '');
            $due = (string) ($r['due_date'] ?? '');
            $r['is_overdue'] = ($status !== 'pago' && $status !== 'cancelado' && $due !== '' && $due < $today);
            $r['open_amount'] = max(0, (float) ($r['amount'] ?? 0) - (float) ($r['paid_amount'] ?? 0));
        }
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function listPayments(Request $request, array $params): void
    {
        $installmentId = (int) ($params['id'] ?? 0);
        $rows = (new FinancePaymentRepository())->listByInstallment($installmentId);
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public function addPayment(Request $request, array $params): void
    {
        $installmentId = (int) ($params['id'] ?? 0);
        $amount = Money::parseBRL((string) $request->input('amount', '0'));
        $method = trim((string) $request->input('method', ''));
        $reference = trim((string) $request->input('reference', ''));
        $note = trim((string) $request->input('note', ''));

        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinanceService())->addPayment(
                $installmentId,
                $amount,
                $method !== '' ? $method : null,
                $reference !== '' ? $reference : null,
                $note !== '' ? $note : null,
                $actorId
            );
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, array $params): void
    {
        $installmentId = (int) ($params['id'] ?? 0);
        $reason = trim((string) $request->input('reason', ''));
        $actorId = (int) Session::get('user_id', 0);
        $isAdmin = (int) Session::get('is_admin', 0) === 1;
        try {
            (new FinanceService())->cancelInstallment($installmentId, $reason, $actorId, $isAdmin);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function reopen(Request $request, array $params): void
    {
        $installmentId = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinanceService())->reopenInstallment($installmentId, $actorId);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
