<?php
use App\Core\UI;
use App\Core\View;

$title = 'Auditoria - Perfil Empresarial';
$rows = is_array($rows ?? null) ? $rows : [];
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold">Auditoria do Perfil</div>
    <div class="text-slate-600 mt-1">Histórico rastreável de alterações</div>
  </div>
  <a class="tr-icon-btn" href="<?= View::e($base . '/empresa') ?>" aria-label="Voltar">
    <?= UI::icon('arrow-left') ?>
    <span class="sr-only">Voltar</span>
  </a>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-slate-700">
      <tr>
        <th class="text-left p-3">Quando</th>
        <th class="text-left p-3">Usuário</th>
        <th class="text-left p-3">Ação</th>
        <th class="text-left p-3">Mudanças</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $diff = is_array($r['diff'] ?? null) ? $r['diff'] : [];
          $keys = array_keys($diff);
          $summary = count($keys) > 0 ? implode(', ', array_slice($keys, 0, 4)) : '—';
          if (count($keys) > 4) {
            $summary .= '…';
          }
        ?>
        <tr class="border-t">
          <td class="p-3 whitespace-nowrap"><?= View::e((string)($r['created_at'] ?? '')) ?></td>
          <td class="p-3">#<?= (int)($r['actor_id'] ?? 0) ?></td>
          <td class="p-3"><span class="tr-badge"><?= View::e((string)($r['action'] ?? '')) ?></span></td>
          <td class="p-3 text-slate-700"><?= View::e($summary) ?></td>
        </tr>
      <?php endforeach; ?>

      <?php if (count($rows) === 0): ?>
        <tr class="border-t">
          <td class="p-3 text-slate-600" colspan="4">Nenhum evento de auditoria ainda.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

