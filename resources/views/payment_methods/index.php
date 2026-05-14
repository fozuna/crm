<?php
use App\Core\UI;
use App\Core\View;

$title = 'Formas de pagamento';
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold">Formas de pagamento</div>
    <div class="text-slate-600 mt-1">Configuração de descontos, parcelamento e condições</div>
  </div>
  <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/pagamentos/novo') ?>" aria-label="Nova forma">
    <?= UI::icon('plus') ?>
    <span class="sr-only">Nova forma</span>
  </a>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-slate-700">
      <tr>
        <th class="text-left p-3">Nome</th>
        <th class="text-left p-3">Tipo</th>
        <th class="text-left p-3">Desconto</th>
        <th class="text-left p-3">Parcelas</th>
        <th class="text-left p-3">Entrada</th>
        <th class="text-left p-3">Ativo</th>
        <th class="text-left p-3">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($methods as $m): ?>
        <tr class="border-t">
          <td class="p-3 font-medium"><?= View::e((string)$m['name']) ?></td>
          <td class="p-3"><span class="tr-badge"><?= View::e((string)$m['type']) ?></span></td>
          <td class="p-3"><?= number_format((float)$m['discount_percent'], 2, ',', '.') ?>%</td>
          <td class="p-3"><?= (int)$m['installments_count'] ?> / <?= (int)$m['interval_days'] ?>d</td>
          <td class="p-3"><?= ((int)$m['has_down_payment'] === 1) ? (number_format((float)$m['down_payment_percent'], 2, ',', '.') . '%') : '—' ?></td>
          <td class="p-3"><?= ((int)$m['active'] === 1) ? 'Sim' : 'Não' ?></td>
          <td class="p-3">
            <div class="flex items-center gap-2">
              <a class="tr-icon-btn" href="<?= View::e($base . '/pagamentos/' . $m['id'] . '/editar') ?>" aria-label="Editar">
                <?= UI::icon('edit') ?>
                <span class="sr-only">Editar</span>
              </a>
              <form method="post" action="<?= View::e($base . '/pagamentos/' . $m['id'] . '/excluir') ?>" onsubmit="return confirm('Excluir esta forma de pagamento?')">
                <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
                <button class="tr-icon-btn" aria-label="Excluir">
                  <?= UI::icon('trash') ?>
                  <span class="sr-only">Excluir</span>
                </button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($methods) === 0): ?>
        <tr><td class="p-6 text-slate-600" colspan="7">Nenhuma forma cadastrada.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

