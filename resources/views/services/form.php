<?php
use App\Core\UI;
use App\Core\View;

$title = 'Serviço';
$service = is_array($service ?? null) ? $service : null;
$id = (int) ($service['id'] ?? 0);
$name = (string) ($service['name'] ?? '');
$price = (string) ($service['default_price'] ?? '');
$active = isset($service['active']) ? (int) $service['active'] : 1;
$bonus = isset($service['is_bonus']) ? (int) $service['is_bonus'] : 0;
$desc = (string) ($service['description'] ?? '');
$error = (string) ($error ?? '');

$action = $id > 0 ? ($base . '/servicos/' . $id) : ($base . '/servicos');
?>

<div class="flex items-start justify-between gap-4">
  <div>
    <div class="text-2xl font-semibold"><?= $id > 0 ? 'Editar serviço' : 'Novo serviço' ?></div>
    <div class="text-slate-600 mt-1">Use bônus para itens gratuitos que não entram no total</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/servicos') ?>" aria-label="Voltar">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<?php if ($error !== ''): ?>
  <div class="mt-6 tr-card p-4 border border-red-200 bg-red-50 text-red-700 text-sm">
    <?= View::e($error) ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 tr-card p-6 space-y-4" id="serviceForm">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div>
      <label class="tr-label">Nome do serviço</label>
      <input name="name" class="mt-1 tr-input" value="<?= View::e($name) ?>" maxlength="100" required>
      <div id="nameHelp" class="text-xs text-slate-600 mt-1"></div>
    </div>
    <div>
      <label class="tr-label">Preço padrão</label>
      <input name="default_price" class="mt-1 tr-input" value="<?= View::e($price) ?>" placeholder="0,00" required>
      <div id="priceHelp" class="text-xs text-slate-600 mt-1"></div>
    </div>
  </div>

  <div>
    <label class="tr-label">Descrição detalhada</label>
    <textarea name="description" class="mt-1 tr-input" rows="6" required><?= View::e($desc) ?></textarea>
    <div id="descHelp" class="text-xs text-slate-600 mt-1"></div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <label class="flex items-center gap-2">
      <input type="checkbox" name="active" value="1" <?= $active ? 'checked' : '' ?> class="rounded border-slate-300">
      <span class="text-sm">Ativo</span>
    </label>
    <label class="flex items-center gap-2">
      <input type="checkbox" name="is_bonus" value="1" <?= $bonus ? 'checked' : '' ?> class="rounded border-slate-300">
      <span class="text-sm">Pode ser oferecido como bônus</span>
    </label>
  </div>

  <div class="flex justify-end gap-2 pt-2">
    <a class="tr-btn" href="<?= View::e($base . '/servicos') ?>">Cancelar</a>
    <button class="tr-btn tr-icon-btn--accent" type="submit" id="saveBtn">Salvar</button>
  </div>
</form>

<script>
  (function(){
    const form = document.getElementById('serviceForm');
    const save = document.getElementById('saveBtn');
    const name = form.querySelector('input[name=name]');
    const price = form.querySelector('input[name=default_price]');
    const desc = form.querySelector('textarea[name=description]');
    const nameHelp = document.getElementById('nameHelp');
    const priceHelp = document.getElementById('priceHelp');
    const descHelp = document.getElementById('descHelp');

    function isMoney(v){
      const s = String(v || '').trim().replace(/\./g,'').replace(',', '.');
      if (s === '') return false;
      if (!/^\d+(\.\d{1,2})?$/.test(s)) return false;
      return Number(s) >= 0;
    }

    function validate(){
      let ok = true;
      const n = String(name.value || '').trim();
      if (n.length === 0) {
        ok = false;
        nameHelp.textContent = 'Obrigatório.';
        nameHelp.className = 'text-xs mt-1 text-red-700';
      } else if (n.length > 100) {
        ok = false;
        nameHelp.textContent = 'Máximo 100 caracteres.';
        nameHelp.className = 'text-xs mt-1 text-red-700';
      } else {
        nameHelp.textContent = n.length + '/100';
        nameHelp.className = 'text-xs mt-1 text-slate-600';
      }

      const p = String(price.value || '').trim();
      if (!isMoney(p)) {
        ok = false;
        priceHelp.textContent = 'Formato inválido. Use 0,00 (até 2 casas decimais).';
        priceHelp.className = 'text-xs mt-1 text-red-700';
      } else {
        priceHelp.textContent = 'OK';
        priceHelp.className = 'text-xs mt-1 text-slate-600';
      }

      const d = String(desc.value || '').trim();
      if (d.length < 50) {
        ok = false;
        descHelp.textContent = 'Mínimo 50 caracteres (' + d.length + '/50).';
        descHelp.className = 'text-xs mt-1 text-red-700';
      } else {
        descHelp.textContent = d.length + ' caracteres';
        descHelp.className = 'text-xs mt-1 text-slate-600';
      }

      save.disabled = !ok;
    }

    name.addEventListener('input', validate);
    price.addEventListener('input', validate);
    desc.addEventListener('input', validate);
    validate();
  })();
</script>

