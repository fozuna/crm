<?php
use App\Core\View;
use App\Core\UI;

$title = 'Financeiro - Projeto #' . (int)$project['id'];
$installments = is_array($installments ?? null) ? $installments : [];
$requests = is_array($requests ?? null) ? $requests : [];
$isAdmin = (bool)($isAdmin ?? false);
$today = date('Y-m-d');
$ok = trim((string)($ok ?? ''));
$error = trim((string)($error ?? ''));
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Financeiro do projeto</div>
    <div class="text-slate-600 mt-1">Parcelas, pagamentos, cancelamento e reabertura</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/projetos/' . (int)$project['id']) ?>" aria-label="Voltar">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">Parcela</th>
          <th class="text-left py-3 px-4">Vencimento</th>
          <th class="text-left py-3 px-4">Valor</th>
          <th class="text-left py-3 px-4">Pago</th>
          <th class="text-left py-3 px-4">Aberto</th>
          <th class="text-left py-3 px-4">Status</th>
          <th class="text-right py-3 px-4">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($installments) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="7">Sem parcelas geradas.</td></tr>
        <?php endif; ?>
        <?php foreach ($installments as $it): ?>
          <?php
            $iid = (int)$it['id'];
            $no = (int)$it['installment_no'];
            $amount = (float)$it['amount'];
            $paid = (float)$it['paid_amount'];
            $open = max(0, $amount - $paid);
            $status = (string)$it['status'];
            $due = (string)$it['due_date'];
            $isOverdue = ($status !== 'pago' && $status !== 'cancelado' && $due !== '' && $due < $today);
          ?>
          <tr class="border-t">
            <td class="px-4 py-3 font-semibold">#<?= $no ?></td>
            <td class="px-4 py-3"><?= $due !== '' ? View::e(date('d/m/Y', strtotime($due))) : '—' ?><?= $isOverdue ? ' • <span class="text-red-700 font-semibold">atrasada</span>' : '' ?></td>
            <td class="px-4 py-3">R$ <?= number_format($amount, 2, ',', '.') ?></td>
            <td class="px-4 py-3">R$ <?= number_format($paid, 2, ',', '.') ?></td>
            <td class="px-4 py-3">R$ <?= number_format($open, 2, ',', '.') ?></td>
            <td class="px-4 py-3"><span class="tr-badge"><?= View::e($status) ?></span></td>
            <td class="px-4 py-3 text-right">
              <div class="flex justify-end gap-2">
                <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/financeiro/' . $iid . '/pagar') ?>" class="flex items-center gap-2">
                  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                  <input name="amount" data-money="brl" class="tr-input text-sm w-28" placeholder="0,00" value="<?= View::e(number_format($open, 2, ',', '.')) ?>">
                  <input name="method" class="tr-input text-sm w-28" placeholder="Método">
                  <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Baixar">
                    <?= UI::icon('check') ?>
                    <span class="sr-only">Baixar</span>
                  </button>
                </form>
                <?php if ($status !== 'cancelado'): ?>
                  <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/financeiro/' . $iid . '/cancelar') ?>" class="flex items-center gap-2">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <input name="reason" class="tr-input text-sm w-44" placeholder="Motivo">
                    <button class="tr-icon-btn" aria-label="Cancelar">
                      <?= UI::icon('x') ?>
                      <span class="sr-only">Cancelar</span>
                    </button>
                  </form>
                <?php else: ?>
                  <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/financeiro/' . $iid . '/reabrir') ?>">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <button class="tr-icon-btn" aria-label="Reabrir">
                      <?= UI::icon('refresh') ?>
                      <span class="sr-only">Reabrir</span>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (count($requests) > 0): ?>
  <div class="mt-6 tr-card p-6">
    <div class="font-semibold">Solicitações de cancelamento</div>
    <div class="mt-4 space-y-2">
      <?php foreach ($requests as $r): ?>
        <?php
          $rid = (int)($r['id'] ?? 0);
          $no = (int)($r['installment_no'] ?? 0);
          $penalty = (float)($r['penalty_amount'] ?? 0);
          $interest = (float)($r['interest_amount'] ?? 0);
          $total = (float)($r['total_amount'] ?? 0);
        ?>
        <div class="rounded border border-slate-200 bg-white p-4 flex items-start justify-between gap-4">
          <div>
            <div class="font-semibold">Parcela #<?= $no ?></div>
            <div class="text-sm text-slate-700 mt-1"><?= View::e((string)($r['reason'] ?? '')) ?></div>
            <div class="text-xs text-slate-600 mt-2">Multa: R$ <?= number_format($penalty, 2, ',', '.') ?> • Juros: R$ <?= number_format($interest, 2, ',', '.') ?> • Total: R$ <?= number_format($total, 2, ',', '.') ?></div>
          </div>
          <?php if ($isAdmin): ?>
            <div class="flex items-center gap-2">
              <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/financeiro/cancelamentos/' . $rid . '/aprovar') ?>">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Aprovar">
                  <?= UI::icon('check') ?>
                  <span class="sr-only">Aprovar</span>
                </button>
              </form>
              <form method="post" action="<?= View::e($base . '/projetos/' . (int)$project['id'] . '/financeiro/cancelamentos/' . $rid . '/rejeitar') ?>">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <button class="tr-icon-btn" aria-label="Rejeitar">
                  <?= UI::icon('x') ?>
                  <span class="sr-only">Rejeitar</span>
                </button>
              </form>
            </div>
          <?php else: ?>
            <span class="tr-badge">pendente</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div id="confirmModal" class="hidden fixed inset-0 bg-black/40 z-50">
  <div class="min-h-full flex items-end md:items-center justify-center p-4">
    <div class="tr-card w-full max-w-xl overflow-hidden">
      <div class="p-5 flex items-center justify-between border-b">
        <div id="confirmTitle" class="font-semibold">Confirmar ação</div>
        <button id="confirmCancel" type="button" class="tr-icon-btn" title="Cancelar" aria-label="Cancelar">
          <?= UI::icon('x', 'w-5 h-5') ?>
          <span class="sr-only">Cancelar</span>
        </button>
      </div>
      <div class="p-5">
        <div id="confirmMessage" class="text-sm text-slate-700"></div>
        <div id="confirmCountdown" class="mt-3 text-xs text-slate-600"></div>
      </div>
      <div class="p-5 flex justify-end gap-2 border-t">
        <button id="confirmCancel2" type="button" class="tr-icon-btn" title="Cancelar" aria-label="Cancelar">
          <?= UI::icon('x', 'w-5 h-5') ?>
          <span class="sr-only">Cancelar</span>
        </button>
        <button id="confirmOk" type="button" class="tr-icon-btn bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700" title="Confirmar" aria-label="Confirmar">
          <?= UI::icon('check', 'w-5 h-5') ?>
          <span class="sr-only">Confirmar</span>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    var okMsg = <?= json_encode($ok, JSON_UNESCAPED_UNICODE) ?>;
    var errMsg = <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>;
    try {
      if (okMsg) window.trToast && window.trToast('success', okMsg);
      if (errMsg) window.trToast && window.trToast('error', errMsg);
      if (okMsg || errMsg) {
        window.history && window.history.replaceState && window.history.replaceState(null, '', window.location.pathname);
      }
    } catch (e) {}

    var modal = document.getElementById('confirmModal');
    var titleEl = document.getElementById('confirmTitle');
    var msgEl = document.getElementById('confirmMessage');
    var cdEl = document.getElementById('confirmCountdown');
    var cancelBtn = document.getElementById('confirmCancel');
    var cancelBtn2 = document.getElementById('confirmCancel2');
    var okBtn = document.getElementById('confirmOk');
    var resolveFn = null;
    var timer = 0;
    var tick = 0;

    function toast(type, message){
      try {
        if (window.trToast) {
          window.trToast(type, message);
          return;
        }
      } catch (e) {}
      alert(String(message || ''));
    }

    function closeModal(expired){
      if (!modal) return;
      modal.classList.add('hidden');
      if (timer) window.clearTimeout(timer);
      if (tick) window.clearInterval(tick);
      timer = 0;
      tick = 0;
      var r = resolveFn;
      resolveFn = null;
      if (typeof r === 'function') r(false);
      if (expired) toast('warning', 'Confirmação expirada por segurança.');
    }

    function confirmDialog(opts){
      if (!modal || !titleEl || !msgEl || !cdEl || !okBtn || !cancelBtn) return Promise.resolve(false);
      var title = String(opts && opts.title ? opts.title : 'Confirmar ação');
      var message = String(opts && opts.message ? opts.message : '');
      var okLabel = String(opts && opts.okLabel ? opts.okLabel : 'Confirmar');
      var okClass = String(opts && opts.okClass ? opts.okClass : 'tr-icon-btn bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700');
      var timeoutMs = Number(opts && opts.timeoutMs ? opts.timeoutMs : 12000);
      var until = Date.now() + timeoutMs;

      titleEl.textContent = title;
      msgEl.textContent = message;
      okBtn.className = okClass;
      okBtn.setAttribute('title', okLabel);
      okBtn.setAttribute('aria-label', okLabel);
      okBtn.removeAttribute('disabled');
      cancelBtn.removeAttribute('disabled');
      modal.classList.remove('hidden');

      function updateCountdown(){
        var left = Math.max(0, until - Date.now());
        cdEl.textContent = 'Tempo para confirmar: ' + Math.ceil(left / 1000) + 's';
      }
      updateCountdown();
      tick = window.setInterval(updateCountdown, 250);
      timer = window.setTimeout(function(){ closeModal(true); }, timeoutMs);

      return new Promise(function(resolve){ resolveFn = resolve; });
    }

    if (cancelBtn) cancelBtn.addEventListener('click', function(){ closeModal(false); });
    if (cancelBtn2) cancelBtn2.addEventListener('click', function(){ closeModal(false); });
    if (okBtn) okBtn.addEventListener('click', function(){
      if (!modal) return;
      modal.classList.add('hidden');
      if (timer) window.clearTimeout(timer);
      if (tick) window.clearInterval(tick);
      timer = 0;
      tick = 0;
      var r = resolveFn;
      resolveFn = null;
      if (typeof r === 'function') r(true);
    });
    if (modal) modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(false); });

    function normMoney(s){
      var v = String(s || '').trim();
      if (!v) return 0;
      v = v.replace(/\./g, '').replace(',', '.').replace(/[^\d.-]/g, '');
      var n = parseFloat(v);
      return isFinite(n) ? n : 0;
    }

    document.querySelectorAll('form').forEach(function(f){
      var action = String(f.getAttribute('action') || '');
      if (!/\/financeiro\//.test(action)) return;

      f.addEventListener('submit', function(ev){
        if (f.dataset && f.dataset.confirmed === '1') return;
        var title = 'Confirmar ação';
        var message = 'Confirmar esta operação?';
        var okClass = 'tr-icon-btn bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700';

        if (/\/pagar$/.test(action)) {
          var amountEl = f.querySelector('input[name=amount]');
          var methodEl = f.querySelector('input[name=method]');
          var amount = amountEl ? amountEl.value : '';
          var method = methodEl ? methodEl.value : '';
          if (normMoney(amount) <= 0) {
            ev.preventDefault();
            toast('warning', 'Informe um valor válido para baixar.');
            return;
          }
          title = 'Baixar parcela';
          message = 'Confirmar baixa desta parcela no valor informado? Método: ' + String(method || '—');
          okClass = 'tr-icon-btn bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700';
        } else if (/\/cancelar$/.test(action)) {
          var reasonEl = f.querySelector('input[name=reason]');
          var reason = reasonEl ? String(reasonEl.value || '').trim() : '';
          if (!reason) {
            ev.preventDefault();
            toast('warning', 'Informe o motivo para cancelar.');
            return;
          }
          title = 'Cancelar parcela';
          message = 'Confirmar solicitação/cancelamento desta parcela? Motivo: ' + reason;
          okClass = 'tr-icon-btn bg-red-600 border-red-600 text-white hover:bg-red-700';
        } else if (/\/reabrir$/.test(action)) {
          title = 'Reabrir parcela';
          message = 'Confirmar reabertura desta parcela?';
          okClass = 'tr-icon-btn bg-indigo-600 border-indigo-600 text-white hover:bg-indigo-700';
        } else if (/\/aprovar$/.test(action)) {
          title = 'Aprovar cancelamento';
          message = 'Confirmar aprovação do cancelamento? Esta ação cancela a parcela.';
          okClass = 'tr-icon-btn bg-emerald-600 border-emerald-600 text-white hover:bg-emerald-700';
        } else if (/\/rejeitar$/.test(action)) {
          title = 'Rejeitar cancelamento';
          message = 'Confirmar rejeição do cancelamento?';
          okClass = 'tr-icon-btn bg-red-600 border-red-600 text-white hover:bg-red-700';
        } else {
          return;
        }

        ev.preventDefault();
        confirmDialog({ title: title, message: message, okLabel: 'Confirmar', okClass: okClass, timeoutMs: 12000 }).then(function(ok){
          if (!ok) return;
          if (f.dataset) f.dataset.confirmed = '1';
          try {
            var btn = f.querySelector('button[type=submit],button:not([type]),button');
            if (btn) btn.setAttribute('disabled', 'disabled');
          } catch (e) {}
          toast('info', 'Executando…');
          f.submit();
        });
      });
    });
  })();
</script>
