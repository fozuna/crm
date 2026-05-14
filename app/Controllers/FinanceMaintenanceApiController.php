<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\FinanceInstallmentRepository;
use App\Services\FinanceService;
use App\Services\InstallmentCharges;
use App\Services\Money;

final class FinanceMaintenanceApiController
{
    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $inst = (new FinanceInstallmentRepository())->find($id);
        if ($inst === null) {
            Response::json(['ok' => false, 'error' => 'Parcela não encontrada.'], 404);
            return;
        }

        $open = max(0.0, (float) ($inst['amount'] ?? 0) - (float) ($inst['paid_amount'] ?? 0));
        $charges = InstallmentCharges::compute($open, (string) ($inst['due_date'] ?? ''));
        $today = date('Y-m-d');
        $st = (string) ($inst['status'] ?? '');
        $due = (string) ($inst['due_date'] ?? '');
        $paidAt = (string) ($inst['paid_at'] ?? '');
        $effective = $st;
        if (($st === 'pendente' || $st === 'reaberto') && $due !== '' && $due < $today) {
            $effective = 'vencida';
        }
        if ($st === 'atrasado') {
            $effective = 'vencida';
        }
        if ($st === 'pago' && $paidAt !== '' && $due !== '' && substr($paidAt, 0, 10) < $due) {
            $effective = 'adiantada';
        }

        Response::json([
            'ok' => true,
            'data' => [
                'installment' => $inst,
                'open_amount' => $open,
                'status_effective' => $effective,
                'charges' => $charges,
            ],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $patch = [];
        $due = trim((string) $request->input('due_date', ''));
        if ($due !== '') {
            $patch['due_date'] = $due;
        }
        if ($request->input('amount', null) !== null) {
            $patch['amount'] = Money::parseBRL((string) $request->input('amount', '0'));
        }
        $st = trim((string) $request->input('status', ''));
        if ($st !== '') {
            $patch['status'] = $st;
        }

        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinanceService())->updateInstallment($id, $patch, $actorId);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function delete(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinanceService())->deleteInstallment($id, $actorId);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function pay(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $amount = Money::parseBRL((string) $request->input('amount', '0'));
        $method = trim((string) $request->input('method', ''));
        $reference = trim((string) $request->input('reference', ''));
        $note = trim((string) $request->input('note', ''));
        $actorId = (int) Session::get('user_id', 0);
        try {
            (new FinanceService())->addPayment($id, $amount, $method !== '' ? $method : null, $reference !== '' ? $reference : null, $note !== '' ? $note : null, $actorId);
            Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function advance(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $amount = Money::parseBRL((string) $request->input('amount', '0'));
        $method = trim((string) $request->input('method', ''));
        $reference = trim((string) $request->input('reference', ''));
        $note = trim((string) $request->input('note', ''));
        $actorId = (int) Session::get('user_id', 0);

        try {
            $res = (new FinanceService())->advanceAmount($id, $amount, $method !== '' ? $method : null, $reference !== '' ? $reference : null, $note !== '' ? $note : null, $actorId);
            Response::json(['ok' => true, 'data' => $res]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
