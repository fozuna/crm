<?php
use App\Core\UI;
use App\Core\View;

$title = 'Conta a Receber';
$receivable = is_array($receivable ?? null) ? $receivable : [];
$receipts = is_array($receipts ?? null) ? $receipts : [];
$latestReceipt = is_array($latestReceipt ?? null) ? $latestReceipt : [];
$audit = is_array($audit ?? null) ? $audit : [];
$status = (string) ($receivable['status'] ?? 'pending');
$originalAmount = (float) ($receivable['original_amount'] ?? 0);
$receivedAmount = (float) ($receivable['received_amount'] ?? 0);
$remainingAmount = (float) ($receivable['remaining_amount'] ?? 0);
$dueDate = (string) ($receivable['due_date'] ?? '');
$today = date('Y-m-d');
$daysOverdue = ($remainingAmount > 0 && $dueDate !== '' && $dueDate < $today) ? (int) floor((strtotime($today) - strtotime($dueDate)) / 86400) : 0;
?>

<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
  <div>
    <div class="text-2xl font-semibold"><?= View::e((string) ($receivable['title'] ?? 'Conta a receber')) ?></div>
    <div class="text-slate-600 mt-1">Cliente: <?= View::e((string) (($receivable['client_company'] ?? '') !== '' ? $receivable['client_company'] : ($receivable['client_name'] ?? '—'))) ?> | Projeto: <?= View::e((string) ($receivable['project_title'] ?? '—')) ?></div>
  </div>
  <div class="flex flex-wrap gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis') ?>">Voltar</a>
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/imprimir') ?>" target="_blank" rel="noopener">Imprimir</a>
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/pdf') ?>">PDF</a>
    <?php if ((int) ($latestReceipt['id'] ?? 0) > 0): ?>
      <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/recibos/' . (int) $latestReceipt['id'] . '/preview') ?>" target="_blank" rel="noopener">Preview Recibo</a>
      <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/recibos/' . (int) $latestReceipt['id'] . '/pdf') ?>">Baixar Recibo</a>
    <?php endif; ?>
    <?php if (($canManage ?? false) === true): ?>
      <a class="tr-icon-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/editar') ?>" title="Editar" aria-label="Editar">
        <?= UI::icon('edit') ?>
        <span class="sr-only">Editar</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if (trim((string) ($ok ?? '')) !== ''): ?>
  <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= View::e((string) $ok) ?></div>
<?php endif; ?>
<?php if (trim((string) ($error ?? '')) !== ''): ?>
  <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= View::e((string) $error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mt-6">
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Valor original</div>
    <div class="text-2xl font-semibold mt-2">R$ <?= number_format($originalAmount, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Recebido</div>
    <div class="text-2xl font-semibold mt-2 text-emerald-700">R$ <?= number_format($receivedAmount, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Saldo restante</div>
    <div class="text-2xl font-semibold mt-2 <?= $remainingAmount > 0 ? 'text-amber-700' : 'text-emerald-700' ?>">R$ <?= number_format($remainingAmount, 2, ',', '.') ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Status</div>
    <div class="text-2xl font-semibold mt-2"><?= View::e($status) ?></div>
  </div>
  <div class="tr-card p-5">
    <div class="text-sm text-slate-600">Dias em atraso</div>
    <div class="text-2xl font-semibold mt-2"><?= $daysOverdue ?></div>
  </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 mt-6">
  <div class="tr-card p-6 xl:col-span-2">
    <div class="font-semibold">Detalhes do titulo</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 text-sm">
      <div><span class="text-slate-500">Parcela:</span> <span class="font-semibold"><?= (int) ($receivable['installment_number'] ?? 1) ?>/<?= (int) ($receivable['total_installments'] ?? 1) ?></span></div>
      <div><span class="text-slate-500">Vencimento:</span> <span class="font-semibold"><?= View::e($dueDate !== '' ? $dueDate : '—') ?></span></div>
      <div><span class="text-slate-500">Emissao:</span> <span class="font-semibold"><?= View::e((string) ($receivable['issue_date'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Competencia:</span> <span class="font-semibold"><?= View::e((string) ($receivable['competence_date'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Categoria:</span> <span class="font-semibold"><?= View::e((string) ($receivable['category_name'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Centro de custo:</span> <span class="font-semibold"><?= View::e((string) ($receivable['cost_center_name'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Forma:</span> <span class="font-semibold"><?= View::e((string) ($receivable['payment_method'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Canal:</span> <span class="font-semibold"><?= View::e((string) ($receivable['payment_channel'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">NF:</span> <span class="font-semibold"><?= View::e((string) ($receivable['invoice_number'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Referencia:</span> <span class="font-semibold"><?= View::e((string) ($receivable['external_reference'] ?? '—')) ?></span></div>
      <div><span class="text-slate-500">Banco:</span> <span class="font-semibold"><?= View::e(trim((string) (($receivable['bank_name'] ?? '') . ' ' . ($receivable['account_name'] ?? ''))) ?: '—') ?></span></div>
      <div><span class="text-slate-500">Contrato:</span> <span class="font-semibold"><?= (int) ($receivable['contract_id'] ?? 0) > 0 ? 'Contrato #' . (int) $receivable['contract_id'] : '—' ?></span></div>
      <div class="md:col-span-2"><span class="text-slate-500">Descricao:</span> <span class="font-semibold"><?= View::e((string) ($receivable['description'] ?? '—')) ?></span></div>
      <div class="md:col-span-2"><span class="text-slate-500">Observacoes:</span> <span class="font-semibold"><?= View::e((string) ($receivable['notes'] ?? '—')) ?></span></div>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="font-semibold">Acoes rapidas</div>
    <div class="mt-4 space-y-3">
      <?php if (($canManage ?? false) === true): ?>
        <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/duplicar') ?>" class="js-confirm-action" data-confirm="Duplicar este titulo financeiro?">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <button class="tr-btn w-full" type="submit">Duplicar titulo</button>
        </form>
        <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/excluir') ?>" class="js-confirm-action" data-confirm="Excluir este titulo? A remocao sera logica.">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <button class="tr-btn w-full text-red-700" type="submit">Excluir titulo</button>
        </form>
      <?php else: ?>
        <div class="text-sm text-slate-600">Perfil somente leitura. As acoes de manutencao ficam disponiveis apenas para Financeiro/Administrador.</div>
      <?php endif; ?>
      <a class="tr-btn w-full" href="<?= View::e($base . '/financeiro/relatorios') ?>">Abrir relatorios</a>
    </div>
  </div>
</div>

<?php if (($canManage ?? false) === true): ?>
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-6">
    <div id="receipt-form" class="tr-card p-6">
      <div class="font-semibold">Registrar baixa financeira</div>
      <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/baixa') ?>" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <div>
          <label class="tr-label">Valor recebido</label>
          <input class="mt-1 tr-input" data-money="brl" type="text" name="amount_received" value="<?= View::e(number_format($remainingAmount, 2, ',', '.')) ?>" required>
        </div>
        <div>
          <label class="tr-label">Data do recebimento</label>
          <input class="mt-1 tr-input" type="date" name="payment_date" value="<?= View::e(date('Y-m-d')) ?>" required>
        </div>
        <div>
          <label class="tr-label">Juros</label>
          <input class="mt-1 tr-input" data-money="brl" type="text" name="interest_amount" value="0,00">
        </div>
        <div>
          <label class="tr-label">Multa</label>
          <input class="mt-1 tr-input" data-money="brl" type="text" name="fine_amount" value="0,00">
        </div>
        <div>
          <label class="tr-label">Desconto</label>
          <input class="mt-1 tr-input" data-money="brl" type="text" name="discount_amount" value="0,00">
        </div>
        <div>
          <label class="tr-label">Forma de pagamento</label>
          <input class="mt-1 tr-input" type="text" name="payment_method" placeholder="PIX, TED, boleto..." required>
        </div>
        <div>
          <label class="tr-label">Referencia da transacao</label>
          <input class="mt-1 tr-input" type="text" name="transaction_reference">
        </div>
        <div>
          <label class="tr-label">Referencia bancaria</label>
          <input class="mt-1 tr-input" type="text" name="bank_reference">
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">Comprovante</label>
          <input class="mt-1 tr-input" type="file" name="receipt_file" accept=".pdf,.png,.jpg,.jpeg,.webp">
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">Observacoes</label>
          <textarea class="mt-1 tr-input" name="observation" rows="3" placeholder="Detalhes do recebimento, conta, operador ou conciliacao futura."></textarea>
        </div>
        <div class="md:col-span-2 flex gap-2">
          <button class="tr-btn tr-btn--accent" type="submit">Registrar baixa</button>
        </div>
      </form>
    </div>

    <div id="renegotiate-form" class="tr-card p-6">
      <div class="font-semibold">Renegociar titulo</div>
      <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/renegociar') ?>" class="grid grid-cols-1 gap-4 mt-4">
        <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
        <div>
          <label class="tr-label">Nova data de vencimento</label>
          <input class="mt-1 tr-input" type="date" name="new_due_date" value="<?= View::e($dueDate !== '' ? $dueDate : date('Y-m-d')) ?>" required>
        </div>
        <div>
          <label class="tr-label">Observacoes da renegociacao</label>
          <textarea class="mt-1 tr-input" name="notes" rows="4" placeholder="Motivo, tratativa comercial e condicoes acordadas."></textarea>
        </div>
        <div class="flex gap-2">
          <button class="tr-btn" type="submit">Renegociar</button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<div id="receipts" class="tr-card overflow-hidden mt-6">
  <div class="p-6 border-b">
    <div class="font-semibold">Historico de recebimentos</div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-700">
        <tr>
          <th class="text-left p-3">Data</th>
          <th class="text-left p-3">Valor</th>
          <th class="text-left p-3">Juros</th>
          <th class="text-left p-3">Multa</th>
          <th class="text-left p-3">Desconto</th>
          <th class="text-left p-3">Metodo</th>
          <th class="text-left p-3">Comprovante</th>
          <th class="text-left p-3">Obs.</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Recibo</th>
          <th class="text-left p-3">Acoes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($receipts as $row): ?>
          <?php $reversed = ($row['reversed_at'] ?? null) !== null; ?>
          <tr class="border-t">
            <td class="p-3 whitespace-nowrap"><?= View::e((string) ($row['payment_date'] ?? '')) ?></td>
            <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['amount_received'] ?? 0), 2, ',', '.') ?></td>
            <td class="p-3">R$ <?= number_format((float) ($row['interest_amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="p-3">R$ <?= number_format((float) ($row['fine_amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="p-3">R$ <?= number_format((float) ($row['discount_amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="p-3"><?= View::e((string) ($row['payment_method'] ?? '—')) ?></td>
            <td class="p-3">
              <?php if (trim((string) ($row['receipt_file_path'] ?? '')) !== ''): ?>
                <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/comprovantes/' . (int) ($row['id'] ?? 0)) ?>" target="_blank" rel="noopener">Abrir</a>
              <?php else: ?>
                <span class="text-slate-500">—</span>
              <?php endif; ?>
            </td>
            <td class="p-3"><?= View::e((string) ($row['observation'] ?? '—')) ?></td>
            <td class="p-3"><?= $reversed ? 'estornado' : 'ativo' ?></td>
            <td class="p-3">
              <?php if (!$reversed): ?>
                <div class="flex flex-wrap gap-2">
                  <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/recibos/' . (int) ($row['id'] ?? 0) . '/preview') ?>" target="_blank" rel="noopener">Preview</a>
                  <a class="tr-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/recibos/' . (int) ($row['id'] ?? 0) . '/pdf') ?>">PDF</a>
                </div>
              <?php else: ?>
                <span class="text-slate-500">Indisponivel</span>
              <?php endif; ?>
            </td>
            <td class="p-3">
              <?php if (($canManage ?? false) === true && !$reversed): ?>
                <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . (int) $receivable['id'] . '/estornar') ?>" class="flex flex-wrap gap-2 items-center js-confirm-action" data-confirm="Confirmar estorno desta baixa?">
                  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                  <input type="hidden" name="receipt_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                  <input class="tr-input max-w-xs" type="text" name="reason" placeholder="Motivo do estorno" required>
                  <button class="tr-btn text-red-700" type="submit">Estornar</button>
                </form>
              <?php else: ?>
                <span class="text-slate-500">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($receipts) === 0): ?>
          <tr><td class="p-6 text-slate-600" colspan="11">Nenhuma baixa registrada para este titulo.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="tr-card overflow-hidden mt-6">
  <div class="p-6 border-b">
    <div class="font-semibold">Auditoria financeira</div>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-700">
        <tr>
          <th class="text-left p-3">Data</th>
          <th class="text-left p-3">Acao</th>
          <th class="text-left p-3">IP</th>
          <th class="text-left p-3">Detalhes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($audit as $row): ?>
          <tr class="border-t">
            <td class="p-3 whitespace-nowrap"><?= View::e((string) ($row['created_at'] ?? '')) ?></td>
            <td class="p-3 font-semibold"><?= View::e((string) ($row['action'] ?? '')) ?></td>
            <td class="p-3"><?= View::e((string) ($row['ip_address'] ?? '—')) ?></td>
            <td class="p-3 text-xs text-slate-600"><?= View::e((string) json_encode((array) ($row['metadata'] ?? []), JSON_UNESCAPED_UNICODE)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($audit) === 0): ?>
          <tr><td class="p-6 text-slate-600" colspan="4">Nenhum log de auditoria registrado ate o momento.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="confirmModal" class="hidden fixed inset-0 bg-black/40 z-50">
  <div class="min-h-full flex items-end md:items-center justify-center p-4">
    <div class="tr-card w-full max-w-lg overflow-hidden">
      <div class="p-5 border-b font-semibold">Confirmar acao</div>
      <div class="p-5 text-sm text-slate-700" id="confirmModalMessage">Confirma esta operacao?</div>
      <div class="p-5 flex justify-end gap-2 border-t">
        <button id="confirmModalCancel" type="button" class="tr-btn">Cancelar</button>
        <button id="confirmModalOk" type="button" class="tr-btn tr-btn--accent">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const modal = document.getElementById('confirmModal');
    const message = document.getElementById('confirmModalMessage');
    const cancelBtn = document.getElementById('confirmModalCancel');
    const okBtn = document.getElementById('confirmModalOk');
    let pendingForm = null;

    document.querySelectorAll('.js-confirm-action').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        pendingForm = form;
        if (message) message.textContent = form.getAttribute('data-confirm') || 'Confirma esta operacao?';
        if (modal) modal.classList.remove('hidden');
      });
    });

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        if (modal) modal.classList.add('hidden');
        pendingForm = null;
      });
    }

    if (okBtn) {
      okBtn.addEventListener('click', function () {
        if (!pendingForm) return;
        pendingForm.submit();
      });
    }

    if (modal) {
      modal.addEventListener('click', function (event) {
        if (event.target === modal) {
          modal.classList.add('hidden');
          pendingForm = null;
        }
      });
    }
  })();
</script>
