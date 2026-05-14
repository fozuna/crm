<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Repositories\FinanceCancellationRepository;
use App\Repositories\FinanceInstallmentRepository;
use App\Repositories\FinancePaymentRepository;
use App\Repositories\ProjectEventRepository;

final class FinanceService
{
    private FinanceInstallmentRepository $installments;
    private FinancePaymentRepository $payments;
    private AuditLogRepository $audit;
    private ProjectEventRepository $events;
    private FinanceCancellationRepository $cancellations;

    public function __construct()
    {
        $this->installments = new FinanceInstallmentRepository();
        $this->payments = new FinancePaymentRepository();
        $this->audit = new AuditLogRepository();
        $this->events = new ProjectEventRepository();
        $this->cancellations = new FinanceCancellationRepository();
    }

    public function addPayment(int $installmentId, float $amount, ?string $method, ?string $reference, ?string $note, int $actorId): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Valor inválido.');
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $inst = $this->installments->find($installmentId);
            if ($inst === null) {
                throw new \RuntimeException('Parcela não encontrada.');
            }
            $status = (string) ($inst['status'] ?? '');
            if ($status === 'cancelado') {
                throw new \RuntimeException('Parcela cancelada não pode receber pagamento.');
            }

            $paidAt = date('Y-m-d H:i:s');
            $this->payments->create($installmentId, $amount, $paidAt, $method, $reference, $note, $actorId);

            $newPaid = (float) ($inst['paid_amount'] ?? 0) + $amount;
            $due = (float) ($inst['amount'] ?? 0);
            $fullyPaid = $newPaid + 0.0001 >= $due;

            $currentStatus = (string) ($inst['status'] ?? 'pendente');
            $dueDate = (string) ($inst['due_date'] ?? '');
            $today = date('Y-m-d');
            $paidDate = substr($paidAt, 0, 10);

            if ($fullyPaid) {
                $nextStatus = 'pago';
            } else {
                if (($currentStatus === 'pendente' || $currentStatus === 'reaberto') && $dueDate !== '' && $dueDate < $today) {
                    $nextStatus = 'atrasado';
                } elseif ($currentStatus === 'reaberto') {
                    $nextStatus = 'reaberto';
                } else {
                    $nextStatus = 'pendente';
                }
            }

            $this->installments->markPaidWithStatus($installmentId, min($newPaid, $due), $paidAt, $nextStatus);

            $this->audit->create('installment', $installmentId, 'payment', $actorId, [
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'fully_paid' => $fullyPaid,
            ]);
            $this->events->create((int) $inst['project_id'], 'installment_paid', 'Pagamento registrado na parcela #' . (int) $inst['installment_no'], [
                'installment_id' => $installmentId,
                'amount' => $amount,
            ], $actorId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function updateInstallment(int $installmentId, array $patch, int $actorId): void
    {
        $inst = $this->installments->find($installmentId);
        if ($inst === null) {
            throw new \RuntimeException('Parcela não encontrada.');
        }
        if ((string) ($inst['status'] ?? '') === 'cancelado') {
            throw new \RuntimeException('Parcela cancelada não pode ser editada.');
        }

        $fields = [];
        if (isset($patch['due_date'])) {
            $due = trim((string) $patch['due_date']);
            if ($due === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) !== 1) {
                throw new \RuntimeException('Vencimento inválido.');
            }
            $fields['due_date'] = $due;
        }
        if (array_key_exists('amount', $patch)) {
            $amount = (float) $patch['amount'];
            if (!is_finite($amount) || $amount <= 0) {
                throw new \RuntimeException('Valor inválido.');
            }
            $fields['amount'] = round($amount, 2);
        }
        if (isset($patch['status'])) {
            $st = trim((string) $patch['status']);
            $allowed = ['pendente', 'reaberto', 'atrasado', 'pago', 'adiantado'];
            if (!in_array($st, $allowed, true)) {
                throw new \RuntimeException('Status inválido.');
            }
            $fields['status'] = $st === 'adiantado' ? 'pago' : $st;
        }

        if (count($fields) === 0) {
            return;
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $this->installments->updateFields($installmentId, $fields);
            $this->audit->create('installment', $installmentId, 'update', $actorId, ['before' => $inst, 'after' => $fields]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function deleteInstallment(int $installmentId, int $actorId): void
    {
        $inst = $this->installments->find($installmentId);
        if ($inst === null) {
            throw new \RuntimeException('Parcela não encontrada.');
        }
        if ((string) ($inst['status'] ?? '') === 'pago') {
            throw new \RuntimeException('Parcela paga não pode ser excluída.');
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $ok = $this->installments->deleteIfNoPayments($installmentId);
            if (!$ok) {
                throw new \RuntimeException('Não é possível excluir: existem pagamentos associados.');
            }
            $this->audit->create('installment', $installmentId, 'delete', $actorId, ['before' => $inst]);
            $this->events->create((int) $inst['project_id'], 'installment_deleted', 'Parcela excluída #' . (int) $inst['installment_no'], ['installment_id' => $installmentId], $actorId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function advanceAmount(int $installmentId, float $amount, ?string $method, ?string $reference, ?string $note, int $actorId): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Valor inválido.');
        }

        $first = $this->installments->find($installmentId);
        if ($first === null) {
            throw new \RuntimeException('Parcela não encontrada.');
        }
        $projectId = (int) ($first['project_id'] ?? 0);
        if ($projectId <= 0) {
            throw new \RuntimeException('Projeto inválido.');
        }
        if ((string) ($first['status'] ?? '') === 'cancelado') {
            throw new \RuntimeException('Parcela cancelada não pode ser adiantada.');
        }

        $rows = $this->installments->listByProject($projectId);
        $queue = [];
        $startFound = false;
        $today = date('Y-m-d');
        foreach ($rows as $r) {
            $id = (int) ($r['id'] ?? 0);
            if ($id === $installmentId) {
                $startFound = true;
            }
            if (!$startFound) {
                continue;
            }
            $st = (string) ($r['status'] ?? '');
            if ($st === 'cancelado') {
                continue;
            }
            $open = max(0.0, (float) ($r['amount'] ?? 0) - (float) ($r['paid_amount'] ?? 0));
            if ($open <= 0) {
                continue;
            }
            $queue[] = ['id' => $id, 'open' => $open, 'due_date' => (string) ($r['due_date'] ?? ''), 'status' => $st, 'today' => $today];
        }

        if (count($queue) === 0) {
            throw new \RuntimeException('Não há parcelas em aberto para adiantamento.');
        }

        $paidAt = date('Y-m-d H:i:s');
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        $affected = [];
        try {
            $remaining = $amount;
            foreach ($queue as $q) {
                if ($remaining <= 0) {
                    break;
                }
                $pay = min($remaining, (float) $q['open']);
                $this->payments->create((int) $q['id'], $pay, $paidAt, $method, $reference, $note, $actorId);

                $inst = $this->installments->find((int) $q['id']);
                if ($inst === null) {
                    continue;
                }
                $newPaid = (float) ($inst['paid_amount'] ?? 0) + $pay;
                $due = (float) ($inst['amount'] ?? 0);
                $fullyPaid = $newPaid + 0.0001 >= $due;
                $dueDate = (string) ($inst['due_date'] ?? '');
                $paidDate = substr($paidAt, 0, 10);
                $currentStatus = (string) ($inst['status'] ?? 'pendente');

                $nextStatus = $currentStatus;
                if ($fullyPaid) {
                    $nextStatus = 'pago';
                } else {
                    if (($currentStatus === 'pendente' || $currentStatus === 'reaberto') && $dueDate !== '' && $dueDate < $today) {
                        $nextStatus = 'atrasado';
                    } elseif ($currentStatus === 'reaberto') {
                        $nextStatus = 'reaberto';
                    } else {
                        $nextStatus = 'pendente';
                    }
                }

                $this->installments->markPaidWithStatus((int) $q['id'], min($newPaid, $due), $paidAt, $nextStatus);
                $affected[] = ['installment_id' => (int) $q['id'], 'amount' => $pay, 'status' => $nextStatus];
                $remaining = round($remaining - $pay, 2);
            }

            $this->audit->create('installment', $installmentId, 'advance', $actorId, ['amount' => $amount, 'affected' => $affected]);
            $this->events->create($projectId, 'installment_advance', 'Adiantamento registrado a partir da parcela #' . (int) ($first['installment_no'] ?? 0), ['amount' => $amount], $actorId);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['requested' => $amount, 'affected' => $affected];
    }

    public function cancelInstallment(int $installmentId, string $reason, int $actorId, bool $isAdmin): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Motivo obrigatório.');
        }
        $inst = $this->installments->find($installmentId);
        if ($inst === null) {
            throw new \RuntimeException('Parcela não encontrada.');
        }
        if ((string) ($inst['status'] ?? '') === 'cancelado') {
            return;
        }

        if (!$isAdmin) {
            $open = max(0, (float) ($inst['amount'] ?? 0) - (float) ($inst['paid_amount'] ?? 0));
            $penalty = round($open * 0.02, 2);
            $interest = 0.0;
            $due = (string) ($inst['due_date'] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) === 1) {
                $days = (int) floor((time() - strtotime($due . ' 00:00:00')) / 86400);
                if ($days > 0) {
                    $interest = round($open * 0.00033 * $days, 2);
                }
            }
            $total = round($open + $penalty + $interest, 2);
            $reqId = $this->cancellations->create($installmentId, $actorId, $reason, $penalty, $interest, $total);
            $this->audit->create('installment', $installmentId, 'cancel_request', $actorId, [
                'request_id' => $reqId,
                'penalty_amount' => $penalty,
                'interest_amount' => $interest,
                'total_amount' => $total,
            ]);
            $this->events->create((int) $inst['project_id'], 'installment_cancel_requested', 'Solicitação de cancelamento da parcela #' . (int) $inst['installment_no'], [
                'installment_id' => $installmentId,
                'request_id' => $reqId,
            ], $actorId);
            return;
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $this->installments->cancel($installmentId, $actorId, $reason);
            $this->audit->create('installment', $installmentId, 'cancel', $actorId, ['reason' => $reason]);
            $this->events->create((int) $inst['project_id'], 'installment_canceled', 'Parcela cancelada #' . (int) $inst['installment_no'], ['installment_id' => $installmentId], $actorId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function reopenInstallment(int $installmentId, int $actorId): void
    {
        $inst = $this->installments->find($installmentId);
        if ($inst === null) {
            throw new \RuntimeException('Parcela não encontrada.');
        }
        if ((string) ($inst['status'] ?? '') !== 'cancelado') {
            throw new \RuntimeException('Apenas parcelas canceladas podem ser reabertas.');
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $this->installments->reopen($installmentId, $actorId);
            $this->audit->create('installment', $installmentId, 'reopen', $actorId, null);
            $this->events->create((int) $inst['project_id'], 'installment_reopened', 'Parcela reaberta #' . (int) $inst['installment_no'], ['installment_id' => $installmentId], $actorId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function approveCancellationRequest(int $requestId, int $actorId): void
    {
        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $req = $this->cancellations->find($requestId);
            if ($req === null) {
                throw new \RuntimeException('Solicitação não encontrada.');
            }
            if ((string) ($req['status'] ?? '') !== 'pendente') {
                return;
            }

            $installmentId = (int) ($req['installment_id'] ?? 0);
            $inst = $this->installments->find($installmentId);
            if ($inst === null) {
                throw new \RuntimeException('Parcela não encontrada.');
            }

            $this->cancellations->approve($requestId, $actorId);
            $this->installments->cancel($installmentId, $actorId, (string) ($req['reason'] ?? ''));
            $this->audit->create('installment', $installmentId, 'cancel_approved', $actorId, ['request_id' => $requestId]);
            $this->events->create((int) $inst['project_id'], 'installment_canceled', 'Cancelamento aprovado da parcela #' . (int) $inst['installment_no'], [
                'installment_id' => $installmentId,
                'request_id' => $requestId,
            ], $actorId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function rejectCancellationRequest(int $requestId, int $actorId): void
    {
        $req = $this->cancellations->find($requestId);
        if ($req === null) {
            throw new \RuntimeException('Solicitação não encontrada.');
        }
        if ((string) ($req['status'] ?? '') !== 'pendente') {
            return;
        }
        $this->cancellations->reject($requestId, $actorId);
        $installmentId = (int) ($req['installment_id'] ?? 0);
        $this->audit->create('installment', $installmentId, 'cancel_rejected', $actorId, ['request_id' => $requestId]);
    }
}
