<?php
use App\Core\View;
use App\Core\UI;
$title = 'Propostas';
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold">Propostas</div>
    <div class="text-slate-600 mt-1">Principal módulo do TRAXTER CRM</div>
  </div>
  <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/propostas/nova') ?>" aria-label="Nova proposta">
    <?= UI::icon('plus') ?>
    <span class="sr-only">Nova proposta</span>
  </a>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-slate-700">
      <tr>
        <th class="text-left p-3">#</th>
        <th class="text-left p-3">Título</th>
        <th class="text-left p-3">Cliente</th>
        <th class="text-left p-3">Status</th>
        <th class="text-left p-3">Total</th>
        <th class="text-left p-3">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($proposals as $p): ?>
        <tr class="border-t">
          <td class="p-3"><?= (int)$p['id'] ?></td>
          <td class="p-3 font-medium"><?= View::e((string)$p['title']) ?></td>
          <td class="p-3"><?= View::e((string)$p['client_name']) ?></td>
          <td class="p-3">
            <span class="tr-badge"><?= View::e((string)$p['status']) ?></span>
          </td>
          <td class="p-3">R$ <?= number_format((float)$p['total'], 2, ',', '.') ?></td>
          <td class="p-3">
            <a class="tr-icon-btn" href="<?= View::e($base . '/propostas/' . $p['id']) ?>" aria-label="Visualizar">
              <?= UI::icon('eye') ?>
              <span class="sr-only">Visualizar</span>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($proposals) === 0): ?>
        <tr><td class="p-6 text-slate-600" colspan="6">Nenhuma proposta ainda.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
