<?php
use App\Core\UI;
use App\Core\View;

$title = 'Serviços';
$filters = is_array($filters ?? null) ? $filters : [];
$data = is_array($data ?? null) ? $data : [];
$rows = is_array($data['rows'] ?? null) ? $data['rows'] : [];
$page = (int) ($data['page'] ?? 1);
$per = (int) ($data['per_page'] ?? 20);
$total = (int) ($data['total'] ?? 0);
$pages = (int) max(1, (int) ceil($total / max(1, $per)));

$q = (string) ($filters['q'] ?? '');
$status = (string) ($filters['status'] ?? '');
$type = (string) ($filters['type'] ?? '');
$sort = (string) ($filters['sort'] ?? 'name_asc');

function linkWith(array $baseParams, array $override): string {
  $p = array_merge($baseParams, $override);
  foreach ($p as $k => $v) {
    if ($v === '' || $v === null || $v === 0) {
      unset($p[$k]);
    }
  }
  return '?' . http_build_query($p);
}

$baseParams = ['q' => $q, 'status' => $status, 'type' => $type, 'sort' => $sort];
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold">Serviços</div>
    <div class="text-slate-600 mt-1">Catálogo de serviços para propostas (inclui bônus)</div>
  </div>
  <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/servicos/novo') ?>" aria-label="Novo serviço">
    <?= UI::icon('plus') ?>
    <span class="sr-only">Novo</span>
  </a>
</div>

<div class="mt-6 tr-card p-6">
  <form method="get" action="<?= View::e($base . '/servicos') ?>" class="grid grid-cols-1 lg:grid-cols-6 gap-4">
    <div class="lg:col-span-2">
      <label class="tr-label">Pesquisar</label>
      <input name="q" class="mt-1 tr-input" value="<?= View::e($q) ?>" placeholder="Nome ou descrição">
    </div>
    <div>
      <label class="tr-label">Status</label>
      <select name="status" class="mt-1 tr-input">
        <?php $opts = ['' => 'Todos', 'ativo' => 'Ativo', 'inativo' => 'Inativo']; ?>
        <?php foreach ($opts as $k => $label): ?>
          <option value="<?= View::e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Tipo</label>
      <select name="type" class="mt-1 tr-input">
        <?php $opts2 = ['' => 'Todos', 'normal' => 'Normal', 'bonus' => 'Bônus']; ?>
        <?php foreach ($opts2 as $k => $label): ?>
          <option value="<?= View::e($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="tr-label">Ordenar</label>
      <select name="sort" class="mt-1 tr-input">
        <?php $opts3 = ['name_asc' => 'Nome A-Z', 'name_desc' => 'Nome Z-A', 'updated_desc' => 'Atualização']; ?>
        <?php foreach ($opts3 as $k => $label): ?>
          <option value="<?= View::e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="lg:col-span-6 flex justify-end">
      <button class="tr-btn" type="submit">Filtrar</button>
    </div>
  </form>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <div class="p-6 font-semibold">Lista</div>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-slate-700">
        <tr>
          <th class="text-left py-3 px-4">Serviço</th>
          <th class="text-left py-3 px-4">Preço padrão</th>
          <th class="text-left py-3 px-4">Status</th>
          <th class="text-left py-3 px-4">Tipo</th>
          <th class="text-left py-3 px-4">Ações</th>
        </tr>
      </thead>
      <tbody id="servicesTbody">
        <?php if (count($rows) === 0): ?>
          <tr><td class="px-4 py-6 text-slate-600" colspan="5">Nenhum serviço encontrado.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $id = (int) ($r['id'] ?? 0);
            $active = (int) ($r['active'] ?? 0) === 1;
            $bonus = (int) ($r['is_bonus'] ?? 0) === 1;
            $name = (string) ($r['name'] ?? '');
            $desc = (string) ($r['description'] ?? '');
            $price = (float) ($r['default_price'] ?? 0);
          ?>
          <tr class="border-t <?= !$active ? 'bg-slate-50' : '' ?>">
            <td class="px-4 py-3">
              <div class="font-semibold <?= !$active ? 'text-slate-500 line-through' : '' ?>"><?= View::e($name) ?></div>
              <div class="text-xs text-slate-600 mt-1"><?= View::e(mb_strlen($desc) > 120 ? (mb_substr($desc, 0, 120) . '…') : $desc) ?></div>
            </td>
            <td class="px-4 py-3">R$ <?= number_format($price, 2, ',', '.') ?></td>
            <td class="px-4 py-3">
              <span class="tr-badge <?= $active ? '' : 'opacity-70' ?>"><?= $active ? 'ativo' : 'inativo' ?></span>
            </td>
            <td class="px-4 py-3">
              <span class="tr-badge <?= $bonus ? '' : 'opacity-70' ?>"><?= $bonus ? 'bônus' : 'normal' ?></span>
            </td>
            <td class="px-4 py-3">
              <a class="tr-icon-btn" href="<?= View::e($base . '/servicos/' . $id . '/editar') ?>" aria-label="Editar">
                <?= UI::icon('edit') ?>
                <span class="sr-only">Editar</span>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  (function(){
    const base = <?= json_encode((string)$base, JSON_UNESCAPED_UNICODE) ?>;
    const tbody = document.getElementById('servicesTbody');
    if (!tbody) return;

    function esc(s){
      return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]));
    }

    async function fetchJson(url){
      const res = await fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      const json = await res.json().catch(() => null);
      if (!res.ok || !json) throw new Error('Falha ao carregar serviços');
      return json;
    }

    function render(rows){
      if (!Array.isArray(rows) || rows.length === 0) {
        tbody.innerHTML = '<tr><td class="px-4 py-6 text-slate-600" colspan="5">Nenhum serviço encontrado.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(r => {
        const id = Number(r.id || 0);
        const active = Number(r.active || 0) === 1;
        const bonus = Number(r.is_bonus || 0) === 1;
        const name = String(r.name || '');
        const desc = String(r.description || '');
        const price = Number(r.default_price || 0);
        const descShort = desc.length > 120 ? (desc.slice(0,120) + '…') : desc;
        const priceTxt = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(isFinite(price) ? price : 0);
        return `<tr class="border-t ${active ? '' : 'bg-slate-50'}">
          <td class="px-4 py-3">
            <div class="font-semibold ${active ? '' : 'text-slate-500 line-through'}">${esc(name)}</div>
            <div class="text-xs text-slate-600 mt-1">${esc(descShort)}</div>
          </td>
          <td class="px-4 py-3">R$ ${esc(priceTxt)}</td>
          <td class="px-4 py-3"><span class="tr-badge ${active ? '' : 'opacity-70'}">${active ? 'ativo' : 'inativo'}</span></td>
          <td class="px-4 py-3"><span class="tr-badge ${bonus ? '' : 'opacity-70'}">${bonus ? 'bônus' : 'normal'}</span></td>
          <td class="px-4 py-3"><a class="tr-icon-btn" href="${esc(base + '/servicos/' + id + '/editar')}" aria-label="Editar">✎</a></td>
        </tr>`;
      }).join('');
    }

    const url = base + '/api/services' + window.location.search;
    fetchJson(url)
      .then(res => {
        const data = res && res.data ? res.data : null;
        const rows = data && Array.isArray(data.rows) ? data.rows : [];
        render(rows);
      })
      .catch(() => {});
  })();
</script>

<div class="mt-6 flex items-center justify-between">
  <div class="text-sm text-slate-600">Total: <?= $total ?> | Página <?= $page ?> de <?= $pages ?></div>
  <div class="flex gap-2">
    <a class="tr-btn" href="<?= View::e($base . '/servicos' . linkWith($baseParams, ['page' => max(1, $page - 1)])) ?>">Anterior</a>
    <a class="tr-btn" href="<?= View::e($base . '/servicos' . linkWith($baseParams, ['page' => min($pages, $page + 1)])) ?>">Próxima</a>
  </div>
</div>
