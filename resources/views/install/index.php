<?php
use App\Core\View;
use App\Core\UI;
?>
<!doctype html>
<html lang="pt-br">
<head>
<?php $pageTitle = 'Instalação - TRAXTER CRM'; require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
  <div class="w-full max-w-2xl tr-card p-6">
    <div class="text-2xl font-semibold text-traxterSidebar">Instalar TRAXTER CRM</div>
    <div class="text-slate-600 mt-1">Cria o .env, aplica o SQL e cria o admin.</div>

    <?php if (!empty($error)): ?>
      <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= View::e($base . '/install') ?>" class="mt-6 space-y-6">
      <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
          <label class="tr-label">URL do app</label>
          <input name="app_url" value="<?= View::e((string)($defaults['app_url'] ?? '')) ?>" class="mt-1 tr-input" placeholder="https://seu-dominio.com/gestor" required>
        </div>
        <div>
          <label class="tr-label">DB Host</label>
          <input name="db_host" value="<?= View::e((string)($defaults['db_host'] ?? '127.0.0.1')) ?>" class="mt-1 tr-input" required>
        </div>
        <div>
          <label class="tr-label">DB Port</label>
          <input name="db_port" value="<?= View::e((string)($defaults['db_port'] ?? '3306')) ?>" class="mt-1 tr-input" required>
        </div>
        <div>
          <label class="tr-label">DB Name</label>
          <input name="db_name" value="<?= View::e((string)($defaults['db_name'] ?? '')) ?>" class="mt-1 tr-input" required>
        </div>
        <div>
          <label class="tr-label">DB User</label>
          <input name="db_user" value="<?= View::e((string)($defaults['db_user'] ?? '')) ?>" class="mt-1 tr-input" required>
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">DB Pass</label>
          <input name="db_pass" type="password" class="mt-1 tr-input">
        </div>
      </div>

      <div class="border-t pt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="tr-label">Nome do admin</label>
          <input name="admin_name" value="<?= View::e((string)($defaults['admin_name'] ?? '')) ?>" class="mt-1 tr-input" required>
        </div>
        <div>
          <label class="tr-label">E-mail do admin</label>
          <input name="admin_email" type="email" value="<?= View::e((string)($defaults['admin_email'] ?? '')) ?>" class="mt-1 tr-input" required>
        </div>
        <div class="md:col-span-2">
          <label class="tr-label">Senha do admin (mín. 8)</label>
          <input name="admin_pass" type="password" class="mt-1 tr-input" required>
        </div>
      </div>

      <button class="w-full inline-flex items-center justify-center gap-2 rounded bg-traxterAccent text-traxterDark font-semibold py-2" aria-label="Instalar">
        <?= UI::icon('check') ?>
        <span class="sr-only">Instalar</span>
      </button>
    </form>
  </div>
</body>
</html>
