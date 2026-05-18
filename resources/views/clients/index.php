<?php
use App\Core\View;
use App\Core\UI;
$title = 'Clientes';
$toastType = (string) ($toastType ?? '');
$toastMessage = (string) ($toastMessage ?? '');
?>

<?php if ($toastType !== '' && $toastMessage !== ''): ?>
  <script>window.trToast && window.trToast('<?= View::e($toastType) ?>', '<?= View::e($toastMessage) ?>');</script>
<?php endif; ?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold">Clientes</div>
    <div class="text-slate-600 mt-1">Cadastro enxuto para o fluxo de propostas</div>
  </div>
  <a class="tr-icon-btn tr-icon-btn--accent" href="<?= View::e($base . '/clientes/novo') ?>" aria-label="Novo cliente">
    <?= UI::icon('plus') ?>
    <span class="sr-only">Novo cliente</span>
  </a>
</div>

<div class="mt-6 tr-card overflow-hidden">
  <table class="w-full text-sm">
    <thead class="bg-slate-50 text-slate-700">
      <tr>
        <th class="text-left p-3">Nome</th>
        <th class="text-left p-3">Empresa</th>
        <th class="text-left p-3">E-mail</th>
        <th class="text-left p-3">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
        <tr class="border-t">
          <td class="p-3 font-medium">
            <a class="text-traxterAccent" href="<?= View::e($base . '/clientes/' . $c['id']) ?>"><?= View::e((string)$c['name']) ?></a>
          </td>
          <td class="p-3"><?= View::e((string)($c['company'] ?? '')) ?></td>
          <td class="p-3"><?= View::e((string)($c['email'] ?? '')) ?></td>
          <td class="p-3">
            <div class="flex items-center gap-3">
              <a class="tr-icon-btn" href="<?= View::e($base . '/clientes/' . $c['id']) ?>" aria-label="Visualizar">
                <?= UI::icon('eye') ?>
                <span class="sr-only">Visualizar</span>
              </a>
              <a class="tr-icon-btn" href="<?= View::e($base . '/clientes/' . $c['id'] . '/editar') ?>" aria-label="Editar">
                <?= UI::icon('edit') ?>
                <span class="sr-only">Editar</span>
              </a>
              <form method="post" action="<?= View::e($base . '/clientes/' . $c['id'] . '/excluir') ?>" onsubmit="return confirm('Excluir este cliente?')">
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
      <?php if (count($clients) === 0): ?>
        <tr><td class="p-6 text-slate-600" colspan="4">Nenhum cliente cadastrado.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
