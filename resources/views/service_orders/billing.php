<?php
use App\Core\UI;
use App\Core\View;
use App\Services\ServiceOrderStatus;

$title = 'Definir cobrança';
$order = is_array($order ?? null) ? $order : [];
$receivables = is_array($receivables ?? null) ? $receivables : [];
$formData = is_array($formData ?? null) ? $formData : [];
$error = trim((string) ($error ?? ''));
$id = (int) ($order['id'] ?? 0);
$clientLabel = trim((string) ($order['client_company'] ?? '')) !== ''
    ? (string) $order['client_company']
    : (trim((string) ($order['client_name'] ?? '')) !== '' ? (string) $order['client_name'] : 'Cliente não vinculado');
$finalAmount = (float) ($order['final_amount'] ?? 0);
$alreadyBilled = $receivables !== [];
$selectedMode = (string) ($formData['mode'] ?? '');
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Definir cobrança</div>
    <div class="text-slate-600 mt-1">Escolha como a Ordem de Serviço <?= View::e((string) ($order['numero_os'] ?? '')) ?> será cobrada do cliente.</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/editar') ?>" aria-label="Voltar para a OS">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<?php if ($error !== ''): ?>
  <div class="mt-6 tr-card p-4 border border-red-200 bg-red-50 text-red-700 text-sm"><?= View::e($error) ?></div>
<?php endif; ?>

<div class="mt-6 tr-card p-6">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
    <div>
      <div class="text-slate-500">OS</div>
      <div class="font-semibold mt-1"><?= View::e((string) ($order['numero_os'] ?? '')) ?></div>
    </div>
    <div>
      <div class="text-slate-500">Cliente</div>
      <div class="font-semibold mt-1"><?= View::e($clientLabel) ?></div>
    </div>
    <div class="md:col-span-1">
      <div class="text-slate-500">Serviço</div>
      <div class="font-semibold mt-1"><?= View::e((string) ($order['service_name'] ?? '')) ?></div>
    </div>
    <div>
      <div class="text-slate-500">Valor da OS</div>
      <div class="font-semibold mt-1 text-lg">R$ <?= number_format($finalAmount, 2, ',', '.') ?></div>
    </div>
  </div>
</div>

<?php if ($alreadyBilled): ?>
  <div class="mt-6 tr-card p-6 border border-amber-200 bg-amber-50">
    <div class="font-semibold text-amber-800">Esta Ordem de Serviço já possui cobrança gerada.</div>
    <div class="text-amber-700 text-sm mt-1">Os títulos abaixo já foram lançados no financeiro para esta OS.</div>
    <?php $firstReceivableId = (int) ($receivables[0]['id'] ?? 0); ?>
    <?php if ($firstReceivableId > 0): ?>
      <a class="tr-btn tr-btn--accent mt-4 inline-flex" href="<?= View::e($base . '/financeiro/recebiveis/' . $firstReceivableId) ?>">Visualizar financeiro</a>
    <?php endif; ?>
  </div>

  <div class="mt-6 tr-card overflow-hidden">
    <div class="p-6 font-semibold">Parcelas geradas</div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-slate-700">
          <tr>
            <th class="text-left py-3 px-4">Parcela</th>
            <th class="text-left py-3 px-4">Vencimento</th>
            <th class="text-right py-3 px-4">Valor</th>
            <th class="text-right py-3 px-4">Recebido</th>
            <th class="text-right py-3 px-4">Saldo</th>
            <th class="text-left py-3 px-4">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($receivables as $item): ?>
            <tr class="border-t">
              <td class="px-4 py-3"><?= (int) ($item['installment_number'] ?? 1) ?>/<?= (int) ($item['total_installments'] ?? 1) ?></td>
              <td class="px-4 py-3"><?= View::e((string) ($item['due_date'] ?? '')) ?></td>
              <td class="px-4 py-3 text-right">R$ <?= number_format((float) ($item['original_amount'] ?? 0), 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-right">R$ <?= number_format((float) ($item['received_amount'] ?? 0), 2, ',', '.') ?></td>
              <td class="px-4 py-3 text-right">R$ <?= number_format((float) ($item['remaining_amount'] ?? 0), 2, ',', '.') ?></td>
              <td class="px-4 py-3"><?= View::e((string) ($item['status'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php else: ?>
  <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/faturar') ?>" class="mt-6 space-y-6" id="billingForm">
    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

    <div class="tr-card p-6">
      <label class="tr-label">Modelo de cobrança</label>
      <select name="mode" id="billingMode" class="mt-1 tr-input max-w-md">
        <option value="unico" <?= $selectedMode === 'unico' ? 'selected' : '' ?>>Pagamento único</option>
        <option value="parcelado" <?= $selectedMode === 'parcelado' ? 'selected' : '' ?>>Parcelado</option>
        <option value="personalizado" <?= $selectedMode === 'personalizado' ? 'selected' : '' ?>>Parcelamento personalizado</option>
      </select>
    </div>

    <div class="tr-card p-6" id="modeUnico">
      <div class="font-semibold mb-4">Pagamento único</div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="tr-label">Valor</label>
          <input class="mt-1 tr-input" value="R$ <?= number_format($finalAmount, 2, ',', '.') ?>" disabled>
          <div class="tr-hint mt-1">Valor final da OS, lançado integralmente em uma única parcela.</div>
        </div>
        <div>
          <label class="tr-label">Vencimento</label>
          <input type="date" name="due_date" class="mt-1 tr-input" value="<?= View::e((string) ($formData['due_date'] ?? date('Y-m-d'))) ?>">
        </div>
        <div class="md:col-span-3">
          <label class="tr-label">Descrição</label>
          <input name="description" class="mt-1 tr-input" value="<?= View::e((string) ($formData['description'] ?? '')) ?>" placeholder="Opcional">
        </div>
        <div class="md:col-span-3">
          <label class="tr-label">Observação</label>
          <textarea name="notes" class="mt-1 tr-input" rows="2" placeholder="Opcional"><?= View::e((string) ($formData['notes'] ?? '')) ?></textarea>
        </div>
      </div>
    </div>

    <div class="tr-card p-6" id="modeParcelado">
      <div class="font-semibold mb-4">Parcelado</div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="tr-label">Quantidade de parcelas</label>
          <input type="number" min="2" step="1" id="installmentsCount" name="installments_count" class="mt-1 tr-input" value="<?= View::e((string) ($formData['installments_count'] ?? 2)) ?>">
        </div>
        <div>
          <label class="tr-label">Primeiro vencimento</label>
          <input type="date" id="firstDueDate" name="first_due_date" class="mt-1 tr-input" value="<?= View::e((string) ($formData['first_due_date'] ?? date('Y-m-d'))) ?>">
        </div>
        <div>
          <label class="tr-label">Periodicidade</label>
          <select id="periodicity" name="periodicity" class="mt-1 tr-input">
            <option value="mensal" <?= (string) ($formData['periodicity'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>Mensal</option>
            <option value="quinzenal" <?= (string) ($formData['periodicity'] ?? '') === 'quinzenal' ? 'selected' : '' ?>>Quinzenal</option>
            <option value="semanal" <?= (string) ($formData['periodicity'] ?? '') === 'semanal' ? 'selected' : '' ?>>Semanal</option>
            <option value="personalizada" <?= (string) ($formData['periodicity'] ?? '') === 'personalizada' ? 'selected' : '' ?>>Personalizada (dias)</option>
          </select>
        </div>
        <div id="customIntervalWrap">
          <label class="tr-label">Intervalo (dias)</label>
          <input type="number" min="1" step="1" id="customIntervalDays" name="custom_interval_days" class="mt-1 tr-input" value="<?= View::e((string) ($formData['custom_interval_days'] ?? 30)) ?>">
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-slate-700">
            <tr>
              <th class="text-left py-2 px-3">Parcela</th>
              <th class="text-left py-2 px-3">Vencimento</th>
              <th class="text-right py-2 px-3">Valor</th>
            </tr>
          </thead>
          <tbody id="parceladoPreview"></tbody>
        </table>
      </div>
    </div>

    <div class="tr-card p-6" id="modePersonalizado">
      <div class="flex items-center justify-between mb-4">
        <div class="font-semibold">Parcelamento personalizado</div>
        <button type="button" class="tr-btn" id="addInstallmentRow">Adicionar parcela</button>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="text-slate-700">
            <tr>
              <th class="text-left py-2 px-3">Descrição</th>
              <th class="text-left py-2 px-3">Valor</th>
              <th class="text-left py-2 px-3">Vencimento</th>
              <th class="text-right py-2 px-3">Remover</th>
            </tr>
          </thead>
          <tbody id="customInstallmentsBody"></tbody>
        </table>
      </div>
      <div class="mt-4 text-sm flex items-center gap-3">
        <span>Total informado: <strong id="customTotal">R$ 0,00</strong></span>
        <span>Valor da OS: <strong>R$ <?= number_format($finalAmount, 2, ',', '.') ?></strong></span>
        <span id="customDiff" class="font-semibold"></span>
      </div>
      <template id="customInstallmentTemplate">
        <tr class="border-t customInstallmentRow">
          <td class="py-2 pr-2"><input name="installment_description[]" class="w-full tr-input" placeholder="Ex.: Entrada"></td>
          <td class="py-2 pr-2"><input name="installment_amount[]" class="w-full tr-input customAmount" data-money="brl" placeholder="0,00"></td>
          <td class="py-2 pr-2"><input type="date" name="installment_due_date[]" class="w-full tr-input customDueDate"></td>
          <td class="py-2 text-right"><button type="button" class="tr-icon-btn removeInstallmentRow" aria-label="Remover parcela"><?= UI::icon('trash') ?></button></td>
        </tr>
      </template>
    </div>

    <div class="flex flex-wrap gap-2">
      <button class="tr-btn tr-btn--accent" type="submit">Confirmar faturamento</button>
      <a class="tr-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/editar') ?>">Cancelar</a>
    </div>
  </form>

  <script>
    (function () {
      var finalAmount = <?= json_encode($finalAmount) ?>;
      var modeSelect = document.getElementById('billingMode');
      var sections = {
        unico: document.getElementById('modeUnico'),
        parcelado: document.getElementById('modeParcelado'),
        personalizado: document.getElementById('modePersonalizado'),
      };

      function syncMode() {
        var mode = modeSelect.value;
        Object.keys(sections).forEach(function (key) {
          sections[key].style.display = key === mode ? '' : 'none';
        });
      }
      modeSelect.addEventListener('change', syncMode);
      syncMode();

      function parseBrl(value) {
        var digits = String(value || '').replace(/\D/g, '');
        return digits ? parseInt(digits, 10) / 100 : 0;
      }
      function fmtMoney(value) {
        return 'R$ ' + Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }
      function addDays(date, days) {
        var d = new Date(date + 'T00:00:00');
        d.setDate(d.getDate() + days);
        return d.toISOString().slice(0, 10);
      }
      function addMonths(date, months) {
        var d = new Date(date + 'T00:00:00');
        d.setMonth(d.getMonth() + months);
        return d.toISOString().slice(0, 10);
      }

      var periodicitySelect = document.getElementById('periodicity');
      var customIntervalWrap = document.getElementById('customIntervalWrap');
      function syncPeriodicity() {
        customIntervalWrap.style.display = periodicitySelect.value === 'personalizada' ? '' : 'none';
      }
      periodicitySelect.addEventListener('change', syncPeriodicity);
      syncPeriodicity();

      var countInput = document.getElementById('installmentsCount');
      var firstDueInput = document.getElementById('firstDueDate');
      var customDaysInput = document.getElementById('customIntervalDays');
      var previewBody = document.getElementById('parceladoPreview');

      function renderParceladoPreview() {
        var count = Math.max(2, parseInt(countInput.value, 10) || 0);
        var firstDue = firstDueInput.value || new Date().toISOString().slice(0, 10);
        var periodicity = periodicitySelect.value;
        var customDays = Math.max(1, parseInt(customDaysInput.value, 10) || 30);
        var base = Math.round((finalAmount / count) * 100) / 100;
        var sum = 0;
        var rows = '';
        for (var i = 1; i <= count; i++) {
          var amount = i === count ? Math.round((finalAmount - sum) * 100) / 100 : base;
          sum = Math.round((sum + amount) * 100) / 100;
          var steps = i - 1;
          var due = firstDue;
          if (steps > 0) {
            if (periodicity === 'mensal') due = addMonths(firstDue, steps);
            else if (periodicity === 'quinzenal') due = addDays(firstDue, steps * 15);
            else if (periodicity === 'semanal') due = addDays(firstDue, steps * 7);
            else due = addDays(firstDue, steps * customDays);
          }
          rows += '<tr class="border-t"><td class="py-2 px-3">' + i + '/' + count + '</td><td class="py-2 px-3">' + due + '</td><td class="py-2 px-3 text-right">' + fmtMoney(amount) + '</td></tr>';
        }
        previewBody.innerHTML = rows;
      }
      [countInput, firstDueInput, periodicitySelect, customDaysInput].forEach(function (el) {
        el.addEventListener('input', renderParceladoPreview);
        el.addEventListener('change', renderParceladoPreview);
      });
      renderParceladoPreview();

      var addBtn = document.getElementById('addInstallmentRow');
      var tbody = document.getElementById('customInstallmentsBody');
      var tpl = document.getElementById('customInstallmentTemplate');
      var totalEl = document.getElementById('customTotal');
      var diffEl = document.getElementById('customDiff');

      function recalcCustomTotal() {
        var sum = 0;
        tbody.querySelectorAll('.customAmount').forEach(function (input) {
          sum += parseBrl(input.value);
        });
        sum = Math.round(sum * 100) / 100;
        totalEl.textContent = fmtMoney(sum);
        var diff = Math.round((finalAmount - sum) * 100) / 100;
        if (Math.abs(diff) < 0.005) {
          diffEl.textContent = 'Confere com o valor da OS.';
          diffEl.className = 'font-semibold text-emerald-700';
        } else {
          diffEl.textContent = 'Diferença: ' + fmtMoney(Math.abs(diff));
          diffEl.className = 'font-semibold text-red-700';
        }
      }

      function bindRow(row) {
        var removeBtn = row.querySelector('.removeInstallmentRow');
        removeBtn.addEventListener('click', function () {
          if (tbody.querySelectorAll('.customInstallmentRow').length > 1) {
            row.remove();
            recalcCustomTotal();
          }
        });
        row.querySelectorAll('.customAmount').forEach(function (input) {
          input.addEventListener('input', recalcCustomTotal);
        });
      }

      function addRow() {
        var row = tpl.content.firstElementChild.cloneNode(true);
        tbody.appendChild(row);
        bindRow(row);
        recalcCustomTotal();
      }
      addBtn.addEventListener('click', addRow);
      addRow();
    })();
  </script>
<?php endif; ?>
