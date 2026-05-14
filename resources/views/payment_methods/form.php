<?php
use App\Core\UI;
use App\Core\View;

$isEdit = is_array($method) && isset($method['id']);
$title = $isEdit ? 'Editar forma de pagamento' : 'Nova forma de pagamento';
$action = $isEdit ? ($base . '/pagamentos/' . $method['id']) : ($base . '/pagamentos');

$type = (string)($method['type'] ?? 'avista');
$active = (string)($method['active'] ?? '1');
$hasDown = (string)($method['has_down_payment'] ?? '0');
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold"><?= View::e($title) ?></div>
    <div class="text-slate-600 mt-1">Defina regras reutilizáveis para propostas</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/pagamentos') ?>" aria-label="Voltar">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<?php if (!empty($error)): ?>
  <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 tr-card p-6 space-y-4" id="pmForm">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="tr-label">Nome</label>
      <input name="name" value="<?= View::e((string)($method['name'] ?? '')) ?>" class="mt-1 tr-input" required>
    </div>
    <div>
      <label class="tr-label">Ativo</label>
      <select name="active" class="mt-1 tr-input">
        <option value="1" <?= ($active === '1' || $active === 'on') ? 'selected' : '' ?>>Sim</option>
        <option value="0" <?= ($active === '0') ? 'selected' : '' ?>>Não</option>
      </select>
    </div>
    <div>
      <label class="tr-label">Tipo</label>
      <select name="type" class="mt-1 tr-input" id="pmType">
        <option value="avista" <?= $type === 'avista' ? 'selected' : '' ?>>À vista</option>
        <option value="parcelado" <?= $type === 'parcelado' ? 'selected' : '' ?>>Parcelado</option>
      </select>
    </div>
    <div>
      <label class="tr-label">Desconto (%)</label>
      <input name="discount_percent" value="<?= View::e((string)($method['discount_percent'] ?? '0')) ?>" class="mt-1 tr-input" inputmode="decimal">
      <div class="tr-hint mt-1">Aplicado automaticamente no total quando selecionado.</div>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="pmInstallments">
    <div>
      <label class="tr-label">Número de parcelas</label>
      <input name="installments_count" value="<?= View::e((string)($method['installments_count'] ?? '1')) ?>" class="mt-1 tr-input" inputmode="numeric">
    </div>
    <div>
      <label class="tr-label">Intervalo (dias)</label>
      <input name="interval_days" value="<?= View::e((string)($method['interval_days'] ?? '30')) ?>" class="mt-1 tr-input" inputmode="numeric">
    </div>
    <div>
      <label class="tr-label">Entrada</label>
      <div class="mt-1 flex items-center gap-3">
        <label class="inline-flex items-center gap-2">
          <input type="checkbox" name="has_down_payment" value="1" <?= ($hasDown === '1' || $hasDown === 'on') ? 'checked' : '' ?>>
          <span class="text-sm font-semibold text-slate-800">Com entrada</span>
        </label>
        <input name="down_payment_percent" value="<?= View::e((string)($method['down_payment_percent'] ?? '0')) ?>" class="tr-input" style="max-width: 160px" inputmode="decimal" placeholder="%">
      </div>
    </div>
  </div>

  <div>
    <label class="tr-label">Condições especiais</label>
    <textarea name="special_terms" rows="4" class="mt-1 tr-input" placeholder="Ex.: multa por atraso, reajustes, escopo..."><?= View::e((string)($method['special_terms'] ?? '')) ?></textarea>
  </div>

  <div class="flex justify-end">
    <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar">
      <?= UI::icon('save') ?>
      <span class="sr-only">Salvar</span>
    </button>
  </div>
</form>

<script>
  const typeEl = document.getElementById('pmType');
  const installmentsEl = document.getElementById('pmInstallments');
  const sync = () => {
    const isParcelado = typeEl.value === 'parcelado';
    installmentsEl.style.display = isParcelado ? '' : 'none';
  };
  typeEl.addEventListener('change', sync);
  sync();
</script>

