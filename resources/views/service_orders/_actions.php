<?php
use App\Core\UI;
use App\Core\View;

$id = (int) ($id ?? 0);
$base = (string) ($base ?? '');
$csrf = (string) ($csrf ?? '');
$canManage = (bool) ($canManage ?? true);
$receivableId = (int) ($receivableId ?? 0);
?>
<div class="flex flex-wrap items-center gap-2">
  <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id) ?>" title="Visualizar detalhes" aria-label="Visualizar detalhes da OS">
    <?= UI::icon('eye') ?>
    <span class="sr-only">Visualizar</span>
  </a>
  <a class="tr-icon-btn" href="<?= View::e($base . '/ordens-servico/' . $id . '/editar') ?>" title="Editar" aria-label="Editar OS">
    <?= UI::icon('edit') ?>
    <span class="sr-only">Editar</span>
  </a>
  <a class="tr-icon-btn text-rose-600" href="<?= View::e($base . '/ordens-servico/' . $id . '/pdf') ?>" title="Abrir PDF" target="_blank" rel="noopener" aria-label="Abrir PDF da OS">
    <?= UI::icon('pdf') ?>
    <span class="sr-only">PDF</span>
  </a>
  <?php if ($receivableId > 0): ?>
    <a class="tr-icon-btn text-sky-700" href="<?= View::e($base . '/financeiro/recebiveis/' . $receivableId) ?>" title="Abrir recebível vinculado" aria-label="Abrir recebível vinculado">
      <?= UI::icon('eye') ?>
      <span class="sr-only">Recebível</span>
    </a>
  <?php endif; ?>
  <?php if ($canManage): ?>
    <form method="post" action="<?= View::e($base . '/ordens-servico/' . $id . '/excluir') ?>" onsubmit="return window.confirm('Excluir esta OS?');">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <button class="tr-icon-btn text-red-700" type="submit" title="Excluir" aria-label="Excluir OS">
        <?= UI::icon('trash') ?>
        <span class="sr-only">Excluir</span>
      </button>
    </form>
  <?php endif; ?>
</div>
