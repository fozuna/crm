<?php
use App\Core\View;
use App\Core\UI;
?>
<!doctype html>
<html lang="pt-br">
<head>
<?php $pageTitle = 'Login - TRAXTER CRM'; require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
  <div class="w-full max-w-md tr-card p-6">
    <div class="text-2xl font-semibold text-traxterSidebar">TRAXTER CRM</div>
    <div class="text-slate-600 mt-1">Acesso ao painel</div>

    <?php if (!empty($error)): ?>
      <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= View::e($base . '/login') ?>" class="mt-6 space-y-4">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
      <div>
        <label class="tr-label">E-mail</label>
        <input name="email" type="email" class="mt-1 tr-input" autocomplete="email" required>
      </div>
      <div>
        <label class="tr-label">Senha</label>
        <input name="password" type="password" class="mt-1 tr-input" autocomplete="current-password" required>
      </div>
      <button class="w-full inline-flex items-center justify-center gap-2 rounded bg-traxterAccent text-traxterDark font-semibold py-2" aria-label="Entrar">
        <?= UI::icon('check') ?>
        <span class="sr-only">Entrar</span>
      </button>
    </form>
  </div>
</body>
</html>
