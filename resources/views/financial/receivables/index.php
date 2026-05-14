<?php
use App\Core\UI;
use App\Core\View;

$title = 'Contas a Receber';
$filters = is_array($filters ?? null) ? $filters : [];
$list = is_array($list ?? null) ? $list : ['rows' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
$rows = is_array($list['rows'] ?? null) ? $list['rows'] : [];
$clients = is_array($clients ?? null) ? $clients : [];
$projects = is_array($projects ?? null) ? $projects : [];
$catalog = is_array($catalog ?? null) ? $catalog : [];
$categories = is_array($catalog['categories'] ?? null) ? $catalog['categories'] : [];
$costCenters = is_array($catalog['cost_centers'] ?? null) ? $catalog['cost_centers'] : [];
$sortOptions = ['due_date' => 'Vencimento', 'client' => 'Cliente', 'project' => 'Projeto', 'amount' => 'Valor', 'remaining' => 'Saldo', 'status' => 'Status', 'days_overdue' => 'Dias em atraso', 'created_at' => 'Criação'];
$canManage = ($canManage ?? false) === true;
?>

<div class="flex items-center justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Contas a Receber</div>
    <div class="text-slate-600 mt-1">Gestão financeira completa dos recebíveis vinculados a projetos, clientes e contratos.</div>
  </div>
  <div class="flex gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/financeiro/dashboard') ?>">Dashboard</a>
    <?php if ($canManage): ?>
      <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/financeiro/recebiveis/novo') ?>" aria-label="Nova conta a receber">
        <?= UI::icon('plus') ?>
        <span class="sr-only">Nova conta a receber</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="mt-6 tr-card p-6">
  <form id="financialFilters" method="get" action="<?= View::e($base . '/financeiro/recebiveis') ?>" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
    <div>
      <label class="tr-label">Busca</label>
      <input class="mt-1 tr-input" type="text" name="q" value="<?= View::e((string) ($filters['q'] ?? '')) ?>" placeholder="Cliente, projeto, NF, referência...">
    </div>
    <div>
      <label class="tr-label">Status</label>
      <select class="mt-1 tr-input" name="status">
        <?php foreach (['' => 'Todos', 'pending' => 'Pending', 'partially_paid' => 'Partially paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'canceled' => 'Canceled', 'renegotiated' => 'Renegotiated'] as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= (string) ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Cliente</label>
      <select class="mt-1 tr-input" name="client_id">
        <option value="0">Todos</option>
        <?php foreach ($clients as $client): ?>
          <?php $cid = (int) ($client['id'] ?? 0); $name = trim((string) (($client['company'] ?? '') !== '' ? $client['company'] : ($client['name'] ?? ''))); ?>
          <option value="<?= $cid ?>" <?= (int) ($filters['client_id'] ?? 0) === $cid ? 'selected' : '' ?>><?= View::e($name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Projeto</label>
      <select class="mt-1 tr-input" name="project_id">
        <option value="0">Todos</option>
        <?php foreach ($projects as $project): ?>
          <?php $pid = (int) ($project['id'] ?? 0); ?>
          <option value="<?= $pid ?>" <?= (int) ($filters['project_id'] ?? 0) === $pid ? 'selected' : '' ?>><?= View::e((string) ($project['title'] ?? 'Projeto #' . $pid)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Categoria</label>
      <select class="mt-1 tr-input" name="category_id">
        <option value="0">Todas</option>
        <?php foreach ($categories as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) ($filters['category_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= View::e((string) $item['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Centro de custo</label>
      <select class="mt-1 tr-input" name="cost_center_id">
        <option value="0">Todos</option>
        <?php foreach ($costCenters as $item): ?>
          <option value="<?= (int) $item['id'] ?>" <?= (int) ($filters['cost_center_id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>><?= View::e((string) $item['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Vencimento de</label>
      <input class="mt-1 tr-input" type="date" name="due_from" value="<?= View::e((string) ($filters['due_from'] ?? '')) ?>">
    </div>
    <div>
      <label class="tr-label">Vencimento até</label>
      <input class="mt-1 tr-input" type="date" name="due_to" value="<?= View::e((string) ($filters['due_to'] ?? '')) ?>">
    </div>
    <div>
      <label class="tr-label">Ordenar por</label>
      <select class="mt-1 tr-input" name="sort">
        <?php foreach ($sortOptions as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= (string) ($filters['sort'] ?? 'due_date') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Direção</label>
      <select class="mt-1 tr-input" name="direction">
        <option value="asc" <?= (string) ($filters['direction'] ?? 'asc') === 'asc' ? 'selected' : '' ?>>Crescente</option>
        <option value="desc" <?= (string) ($filters['direction'] ?? 'asc') === 'desc' ? 'selected' : '' ?>>Decrescente</option>
      </select>
    </div>
    <div class="flex items-end gap-2">
      <button class="tr-btn tr-btn--accent" type="submit">Aplicar</button>
      <button id="saveFinancialFilters" class="tr-btn" type="button">Salvar filtros</button>
      <button id="restoreFinancialFilters" class="tr-btn" type="button">Restaurar</button>
    </div>
  </form>
</div>

<div class="mt-4 flex flex-wrap gap-2">
  <a class="tr-btn" href="<?= View::e($base . '/financeiro/relatorios') ?>">Relatórios</a>
  <a class="tr-btn" href="<?= View::e($base . '/financeiro/relatorios/export/csv?' . http_build_query(array_filter($filters))) ?>">CSV</a>
  <a class="tr-btn" href="<?= View::e($base . '/financeiro/relatorios/export/excel?' . http_build_query(array_filter($filters))) ?>">Excel</a>
  <a class="tr-btn" href="<?= View::e($base . '/financeiro/relatorios/export/pdf?' . http_build_query(array_filter($filters))) ?>">PDF</a>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-4 text-sm text-slate-600">Total encontrado: <span class="font-semibold"><?= (int) ($list['total'] ?? 0) ?></span></div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-700">
        <tr>
          <th class="text-left p-3">Cliente</th>
          <th class="text-left p-3">Projeto</th>
          <th class="text-left p-3">Parcela</th>
          <th class="text-left p-3">Vencimento</th>
          <th class="text-left p-3">Valor</th>
          <th class="text-left p-3">Saldo</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Dias em atraso</th>
          <th class="text-left p-3">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $id = (int) ($row['id'] ?? 0); ?>
          <tr class="border-t">
            <td class="p-3"><?= View::e(trim((string) (($row['client_company'] ?? '') !== '' ? $row['client_company'] : ($row['client_name'] ?? '—')))) ?></td>
            <td class="p-3"><?= View::e((string) ($row['project_title'] ?? '—')) ?></td>
            <td class="p-3"><?= (int) ($row['installment_number'] ?? 0) ?>/<?= (int) ($row['total_installments'] ?? 0) ?></td>
            <td class="p-3 whitespace-nowrap"><?= View::e((string) ($row['due_date'] ?? '')) ?></td>
            <td class="p-3">R$ <?= number_format((float) ($row['original_amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="p-3 font-semibold">R$ <?= number_format((float) ($row['remaining_amount'] ?? 0), 2, ',', '.') ?></td>
            <td class="p-3"><span class="tr-badge"><?= View::e((string) ($row['status'] ?? '')) ?></span></td>
            <td class="p-3"><?= (int) ($row['days_overdue'] ?? 0) ?></td>
            <td class="p-3">
              <div class="flex flex-wrap gap-2">
                <a class="tr-icon-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . $id) ?>" title="Visualizar"><?= UI::icon('eye') ?><span class="sr-only">Visualizar</span></a>
                <?php if ($canManage): ?>
                  <a class="tr-icon-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . $id . '/editar') ?>" title="Editar"><?= UI::icon('edit') ?><span class="sr-only">Editar</span></a>
                  <a class="tr-icon-btn text-emerald-700" href="<?= View::e($base . '/financeiro/recebiveis/' . $id . '#receipt-form') ?>" title="Baixar"><?= UI::icon('check') ?><span class="sr-only">Baixar</span></a>
                  <a class="tr-icon-btn text-amber-700" href="<?= View::e($base . '/financeiro/recebiveis/' . $id . '#receipts') ?>" title="Estornar"><?= UI::icon('x') ?><span class="sr-only">Estornar</span></a>
                  <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . $id . '/duplicar') ?>" class="js-confirm-action" data-confirm="Duplicar este título?" data-loading-label="Duplicando...">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <button class="tr-icon-btn text-indigo-700" title="Duplicar"><?= UI::icon('plus') ?><span class="sr-only">Duplicar</span></button>
                  </form>
                  <a class="tr-icon-btn text-sky-700" href="<?= View::e($base . '/financeiro/recebiveis/' . $id . '#renegotiate-form') ?>" title="Renegociar"><?= UI::icon('refresh') ?><span class="sr-only">Renegociar</span></a>
                  <form method="post" action="<?= View::e($base . '/financeiro/recebiveis/' . $id . '/excluir') ?>" class="js-confirm-action" data-confirm="Excluir este título? A ação fará soft delete." data-loading-label="Excluindo...">
                    <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                    <button class="tr-icon-btn text-red-700" title="Excluir"><?= UI::icon('trash') ?><span class="sr-only">Excluir</span></button>
                  </form>
                <?php endif; ?>
                <a class="tr-icon-btn" href="<?= View::e($base . '/financeiro/recebiveis/' . $id . '/imprimir') ?>" title="Imprimir"><?= UI::icon('eye') ?><span class="sr-only">Imprimir</span></a>
                <a class="tr-icon-btn text-rose-600" href="<?= View::e($base . '/financeiro/recebiveis/' . $id . '/pdf') ?>" title="Gerar PDF"><?= UI::icon('pdf') ?><span class="sr-only">Gerar PDF</span></a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($rows) === 0): ?>
          <tr><td class="p-6 text-slate-600" colspan="9">Nenhuma conta a receber encontrada para os filtros informados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if (($list['pages'] ?? 1) > 1): ?>
  <div class="mt-4 flex flex-wrap gap-2">
    <?php for ($p = 1; $p <= (int) $list['pages']; $p++): ?>
      <a class="tr-btn <?= $p === (int) ($list['page'] ?? 1) ? 'tr-btn--accent' : '' ?>" href="<?= View::e($base . '/financeiro/recebiveis?' . http_build_query(array_merge($filters, ['page' => $p]))) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

<div id="confirmModal" class="hidden fixed inset-0 bg-black/40 z-50">
  <div class="min-h-full flex items-end md:items-center justify-center p-4">
    <div class="tr-card w-full max-w-lg overflow-hidden">
      <div class="p-5 border-b font-semibold">Confirmar ação</div>
      <div class="p-5 text-sm text-slate-700" id="confirmModalMessage">Confirma esta ação?</div>
      <div class="p-5 flex justify-end gap-2 border-t">
        <button id="confirmModalCancel" type="button" class="tr-btn">Cancelar</button>
        <button id="confirmModalOk" type="button" class="tr-btn tr-btn--accent">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function(){
    const ok = <?= json_encode((string) ($ok ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    const error = <?= json_encode((string) ($error ?? ''), JSON_UNESCAPED_UNICODE) ?>;
    if (ok && window.trToast) window.trToast('success', ok);
    if (error && window.trToast) window.trToast('error', error);

    const form = document.getElementById('financialFilters');
    const saveBtn = document.getElementById('saveFinancialFilters');
    const restoreBtn = document.getElementById('restoreFinancialFilters');
    const storageKey = 'traxter.financial.receivables.filters';
    if (saveBtn && form) {
      saveBtn.addEventListener('click', function(){
        const data = {};
        Array.from(form.elements).forEach(function(el){ if (el.name) data[el.name] = el.value; });
        localStorage.setItem(storageKey, JSON.stringify(data));
        if (window.trToast) window.trToast('success', 'Filtros salvos com sucesso.');
      });
    }
    if (restoreBtn && form) {
      restoreBtn.addEventListener('click', function(){
        const raw = localStorage.getItem(storageKey);
        if (!raw) {
          if (window.trToast) window.trToast('info', 'Nenhum filtro salvo encontrado.');
          return;
        }
        try {
          const data = JSON.parse(raw);
          Object.keys(data).forEach(function(key){
            const el = form.querySelector('[name="' + key + '"]');
            if (el) el.value = data[key];
          });
          form.submit();
        } catch (e) {
          if (window.trToast) window.trToast('error', 'Falha ao restaurar filtros salvos.');
        }
      });
    }

    const modal = document.getElementById('confirmModal');
    const msg = document.getElementById('confirmModalMessage');
    const cancelBtn = document.getElementById('confirmModalCancel');
    const okBtn = document.getElementById('confirmModalOk');
    let pendingForm = null;
    document.querySelectorAll('.js-confirm-action').forEach(function(item){
      item.addEventListener('submit', function(ev){
        ev.preventDefault();
        pendingForm = item;
        msg.textContent = item.getAttribute('data-confirm') || 'Confirma esta ação?';
        modal.classList.remove('hidden');
      });
    });
    if (cancelBtn) cancelBtn.addEventListener('click', function(){ modal.classList.add('hidden'); pendingForm = null; });
    if (okBtn) okBtn.addEventListener('click', function(){
      if (!pendingForm) return;
      const btn = pendingForm.querySelector('button');
      if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-60', 'pointer-events-none');
      }
      modal.classList.add('hidden');
      pendingForm.submit();
    });
    if (modal) modal.addEventListener('click', function(ev){ if (ev.target === modal) { modal.classList.add('hidden'); pendingForm = null; }});
  })();
</script>
