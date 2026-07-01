<?php
use App\Core\View;
use App\Core\UI;
use App\Core\Session;
use App\Core\Config;
use App\Repositories\ProposalBrandingRepository;

$brandingLayout = ['company_name' => (string) Config::get('APP_NAME', 'TRAXTER CRM')];
try {
    $brandingLayout = array_merge($brandingLayout, (new ProposalBrandingRepository())->get());
} catch (\Throwable $e) {
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<?php $pageTitle = (string)($title ?? 'TRAXTER CRM'); require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-slate-100 text-slate-900">
  <div class="flex min-h-screen">
    <aside class="w-64 bg-traxterSidebar text-traxterText fixed inset-y-0 left-0">
      <?php
        $appName = trim((string) ($brandingLayout['company_name'] ?? ''));
        if ($appName === '') {
          $appName = (string) Config::get('APP_NAME', 'TRAXTER CRM');
        }
        $userName = (string) Session::get('user_name', '');
        $isAdmin = (int) Session::get('is_admin', 0) === 1;
        $role = (string) Session::get('user_role', '');
        if ($role === '' && $isAdmin) {
          $role = 'admin';
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $questionPos = strpos($requestUri, '?');
        if ($questionPos !== false) {
          $requestUri = substr($requestUri, 0, $questionPos);
        }
        if ($base !== '' && str_starts_with($requestUri, $base)) {
          $requestUri = substr($requestUri, strlen($base));
        }
        $currentPath = '/' . ltrim($requestUri, '/');
        if ($currentPath === '//') {
          $currentPath = '/';
        }

        $isActiveNav = static function (string $currentPath, string $href): bool {
          if ($href === '/dashboard') {
            return $currentPath === '/dashboard' || $currentPath === '/';
          }

          if ($currentPath === $href) {
            return true;
          }

          return str_starts_with($currentPath, rtrim($href, '/') . '/');
        };

        $navLink = static function (string $href, string $label, string $icon) use ($base, $currentPath, $isActiveNav): string {
          $isActive = $isActiveNav($currentPath, $href);
          $linkClass = $isActive
            ? 'group relative flex items-center gap-3 rounded-lg border border-white/10 bg-white/10 px-3 py-2.5 text-white shadow-sm transition-all duration-200'
            : 'group relative flex items-center gap-3 rounded-lg border border-transparent px-3 py-2.5 text-traxterText/95 transition-all duration-200 hover:bg-white/10 hover:text-white';
          $iconClass = $isActive ? 'w-5 h-5 text-traxterAccent' : 'w-5 h-5 text-traxterAccent';
          $labelClass = $isActive ? 'truncate font-semibold' : 'truncate font-medium';
          $activeBar = $isActive
            ? '<span class="absolute left-0 top-1/2 h-7 w-1 -translate-y-1/2 rounded-r-full bg-traxterAccent" aria-hidden="true"></span>'
            : '';

          return '<a class="' . $linkClass . '" href="'
            . View::e($base . $href)
            . '" title="' . View::e($label)
            . '" aria-label="' . View::e($label)
            . '"'
            . ($isActive ? ' aria-current="page"' : '')
            . '>' . $activeBar
            . '<span class="shrink-0">'
            . UI::icon($icon, $iconClass)
            . '</span><span class="' . $labelClass . '">' . View::e($label) . '</span></a>';
        };
      ?>
      <div class="p-6 text-xl font-semibold tracking-wide flex items-center justify-between gap-3">
        <span><?= View::e($appName) ?></span>
        <?php if ($isAdmin): ?>
          <a class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10" href="<?= View::e($base . '/empresa') ?>" aria-label="Perfil Empresarial">
            <?= UI::icon('building', 'w-5 h-5') ?>
            <span class="sr-only">Perfil Empresarial</span>
          </a>
        <?php endif; ?>
      </div>
      <nav class="px-3 space-y-1">
        <?= $navLink('/dashboard', 'Dashboard', 'home') ?>
        <?php if (in_array($role, ['admin','pm'], true)): ?>
          <?= $navLink('/leads', 'Leads', 'users') ?>
        <?php endif; ?>
        <?= $navLink('/clientes', 'Clientes', 'users') ?>
        <?= $navLink('/propostas', 'Propostas', 'briefcase') ?>
        <?php if (in_array($role, ['admin','pm','finance','auditor'], true)): ?>
          <?= $navLink('/contratos', 'Contratos', 'pdf') ?>
        <?php endif; ?>
        <?php if (in_array($role, ['admin','pm'], true)): ?>
          <?= $navLink('/servicos', 'Serviços', 'list') ?>
        <?php endif; ?>
        <?php if (in_array($role, ['admin','pm','finance','auditor'], true)): ?>
          <?= $navLink('/projetos', 'Projetos', 'folder') ?>
        <?php endif; ?>
        <?php if (in_array($role, ['admin','finance','auditor'], true)): ?>
          <?= $navLink('/financeiro/dashboard', 'Financeiro', 'wallet') ?>
          <?= $navLink('/relatorios/financeiro', 'Relatórios', 'chart') ?>
        <?php endif; ?>
        <?php if (in_array($role, ['admin','finance','auditor','pm'], true)): ?>
          <?= $navLink('/auditoria', 'Auditoria', 'shield') ?>
        <?php endif; ?>
        <?= $navLink('/pagamentos', 'Pagamentos', 'credit-card') ?>
        <?php if ($isAdmin): ?>
          <?= $navLink('/branding', 'Branding Legado', 'palette') ?>
        <?php endif; ?>
        <?= $navLink('/manual', 'Manual do Sistema', 'book-open') ?>
      </nav>
      <div class="px-6 mt-6">
        <form method="post" action="<?= View::e($base . '/logout') ?>">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <button class="w-full inline-flex items-center justify-center gap-2 rounded bg-traxterAccent text-traxterDark font-semibold py-2" aria-label="Sair">
            <?= UI::icon('logout') ?>
            <span class="sr-only">Sair</span>
          </button>
        </form>
      </div>
    </aside>

    <main class="flex-1 ml-64 p-6">
      <div class="flex items-center justify-end mb-6">
        <?php if ($userName !== ''): ?>
          <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-sm text-slate-700">
            <span><?= View::e($userName) ?></span>
            <?php if ($isAdmin): ?>
              <span class="tr-badge">admin</span>
            <?php elseif ($role !== ''): ?>
              <span class="tr-badge"><?= View::e($role) ?></span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <?= $content ?>
    </main>
  </div>
</body>
</html>
