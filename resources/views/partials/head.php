<?php
use App\Core\View;
use App\Repositories\ProposalBrandingRepository;

$branding = [
  'company_name' => 'TRAXTER',
  'primary_color' => '#293241',
  'accent_color' => '#ee6c4d',
  'font_name' => 'Helvetica',
  'meta_title' => '',
  'meta_description' => '',
  'meta_keywords' => '',
  'favicon_path' => '',
  'meta_image_path' => '',
];
try {
  $branding = array_merge($branding, (new ProposalBrandingRepository())->get());
} catch (\Throwable $e) {
}

$pageTitleResolved = (string)($pageTitle ?? ($branding['meta_title'] !== '' ? $branding['meta_title'] : 'TRAXTER CRM'));
$primaryColor = (string)($branding['primary_color'] ?? '#293241');
$accentColor = (string)($branding['accent_color'] ?? '#ee6c4d');
$fontFamily = trim((string)($branding['font_name'] ?? 'Helvetica'));
$metaDescription = trim((string)($pageDescription ?? ($branding['meta_description'] ?? '')));
$metaKeywords = trim((string)($pageKeywords ?? ($branding['meta_keywords'] ?? '')));
$basePath = (string)($base ?? '');
$metaImage = trim((string)($pageImage ?? ''));
if ($metaImage === '' && !empty($branding['meta_image_path'])) {
  $metaImage = $basePath . '/empresa/ativo/meta-image';
}
$faviconHref = !empty($branding['favicon_path']) ? ($basePath . '/empresa/ativo/favicon') : '';
$canonicalHref = trim((string)($pageCanonical ?? ''));
$ogType = trim((string)($pageOgType ?? 'website'));
$metaRobots = trim((string)($pageRobots ?? 'index,follow'));
$schemaJsonLd = $pageSchemaJsonLd ?? null;
?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= View::e($pageTitleResolved) ?></title>
  <?php if ($metaDescription !== ''): ?><meta name="description" content="<?= View::e($metaDescription) ?>"><?php endif; ?>
  <?php if ($metaKeywords !== ''): ?><meta name="keywords" content="<?= View::e($metaKeywords) ?>"><?php endif; ?>
  <?php if ($metaRobots !== ''): ?><meta name="robots" content="<?= View::e($metaRobots) ?>"><?php endif; ?>
  <meta name="theme-color" content="<?= View::e($primaryColor) ?>">
  <?php if ($faviconHref !== ''): ?><link rel="icon" href="<?= View::e($faviconHref) ?>"><?php endif; ?>
  <?php if ($canonicalHref !== ''): ?><link rel="canonical" href="<?= View::e($canonicalHref) ?>"><?php endif; ?>
  <meta property="og:title" content="<?= View::e($pageTitleResolved) ?>">
  <?php if ($metaDescription !== ''): ?><meta property="og:description" content="<?= View::e($metaDescription) ?>"><?php endif; ?>
  <meta property="og:type" content="<?= View::e($ogType !== '' ? $ogType : 'website') ?>">
  <?php if ($canonicalHref !== ''): ?><meta property="og:url" content="<?= View::e($canonicalHref) ?>"><?php endif; ?>
  <?php if ($metaImage !== ''): ?><meta property="og:image" content="<?= View::e($metaImage) ?>"><?php endif; ?>
  <meta name="twitter:card" content="<?= $metaImage !== '' ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= View::e($pageTitleResolved) ?>">
  <?php if ($metaDescription !== ''): ?><meta name="twitter:description" content="<?= View::e($metaDescription) ?>"><?php endif; ?>
  <?php if ($metaImage !== ''): ?><meta name="twitter:image" content="<?= View::e($metaImage) ?>"><?php endif; ?>
  <?php if (is_array($schemaJsonLd) && count($schemaJsonLd) > 0): ?>
  <script type="application/ld+json"><?= json_encode($schemaJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
  <?php endif; ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme: { extend: { colors: { traxterSidebar:<?= json_encode($primaryColor, JSON_UNESCAPED_UNICODE) ?>, traxterAccent:<?= json_encode($accentColor, JSON_UNESCAPED_UNICODE) ?>, traxterText:'#fefefe', traxterDark:<?= json_encode($primaryColor, JSON_UNESCAPED_UNICODE) ?> }}}}
  </script>
  <style>
    :root{--tr-primary:<?= View::e($primaryColor) ?>;--tr-accent:<?= View::e($accentColor) ?>;--tr-font:<?= View::e($fontFamily !== '' ? $fontFamily : 'Helvetica') ?>}
    body{font-family:var(--tr-font),Arial,sans-serif}
    .tr-label{display:block;font-size:.875rem;line-height:1.25rem;font-weight:600;color:#0f172a}
    .tr-hint{font-size:.75rem;line-height:1rem;color:#475569}
    .tr-input{width:100%;border-radius:.625rem;border:1px solid #94a3b8;background:#f8fafc;padding:.6rem .75rem;color:#0f172a}
    .tr-input:focus{outline:none;border-color:var(--tr-accent);box-shadow:0 0 0 3px rgba(238,108,77,.25)}
    .tr-input::placeholder{color:#64748b}
    .tr-card{border-radius:.75rem;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.06)}
    .tr-icon-btn{display:inline-flex;align-items:center;justify-content:center;gap:.25rem;width:2.25rem;height:2.25rem;border-radius:.625rem;border:1px solid #cbd5e1;background:#fff;color:#0f172a}
    .tr-icon-btn:hover{background:#f1f5f9}
    .tr-icon-btn:focus{outline:none;box-shadow:0 0 0 3px rgba(238,108,77,.25);border-color:var(--tr-accent)}
    .tr-icon-btn--accent{background:var(--tr-accent);border-color:var(--tr-accent);color:var(--tr-primary)}
    .tr-icon-btn--accent:hover{filter:brightness(.98)}
    .tr-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.625rem;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-weight:700;padding:.6rem 1rem}
    .tr-btn:hover{background:#f1f5f9}
    .tr-btn:focus{outline:none;box-shadow:0 0 0 3px rgba(238,108,77,.25);border-color:var(--tr-accent)}
    .tr-btn--icon-only{width:2.5rem;min-width:2.5rem;height:2.5rem;padding:0;gap:0;font-size:0;line-height:0;border-radius:.75rem}
    .tr-btn--icon-only svg,.tr-icon-btn svg{width:1.15rem;height:1.15rem}
    .tr-badge{display:inline-flex;align-items:center;border-radius:9999px;padding:.25rem .5rem;font-size:.75rem;line-height:1rem;font-weight:600;background:#f1f5f9;color:#0f172a;border:1px solid #cbd5e1}
    .tr-toast{position:fixed;top:1rem;right:1rem;z-index:60;display:none;max-width:min(420px,calc(100vw - 2rem));border-radius:.75rem;padding:.75rem 1rem;box-shadow:0 10px 25px rgba(0,0,0,.18);font-size:.875rem;line-height:1.25rem}
    .tr-toast.show{display:block}
    .tr-toast.success{background:#065f46;color:#fff}
    .tr-toast.error{background:#991b1b;color:#fff}
    .tr-toast.info{background:#1d4ed8;color:#fff}
    .tr-toast.warning{background:#a16207;color:#111827}
  </style>
  <script>
    (function(){
      var iconPaths = {
        eye: '<path d="M1.5 12s4-7.5 10.5-7.5S22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z"></path><path d="M12 15.75A3.75 3.75 0 1 0 12 8.25a3.75 3.75 0 0 0 0 7.5Z"></path>',
        edit: '<path d="M16.862 3.487a2.25 2.25 0 0 1 3.182 3.182L8.56 18.153a4.5 4.5 0 0 1-1.9 1.125l-3.026 1.01a.75.75 0 0 1-.948-.948l1.01-3.026a4.5 4.5 0 0 1 1.125-1.9L16.862 3.487Z"></path><path d="M15.75 4.6 19.4 8.25"></path>',
        trash: '<path d="M9 3.75h6m-9 3h12m-10.5 0 .6 13.2A1.5 1.5 0 0 0 9.6 21.75h4.8a1.5 1.5 0 0 0 1.5-1.8l.6-13.2M10.5 10.5v7.5m3-7.5v7.5"></path><path d="M10.5 3.75a1.5 1.5 0 0 1 3 0"></path>',
        plus: '<path d="M12 5.25v13.5M5.25 12h13.5"></path>',
        check: '<path d="M20.25 6.75 9.75 17.25 3.75 11.25"></path>',
        x: '<path d="M6 6l12 12"></path><path d="M18 6 6 18"></path>',
        'arrow-left': '<path d="M10.5 19.5 3 12l7.5-7.5"></path><path d="M3 12h18"></path>',
        dollar: '<path d="M12 3.75v16.5"></path><path d="M16.5 7.5a3.75 3.75 0 1 0-7.5 0c0 2.071 1.679 3.75 3.75 3.75s3.75 1.679 3.75 3.75a3.75 3.75 0 1 1-7.5 0"></path>',
        pdf: '<path d="M6 2.25h8.25L18 6v15A.75.75 0 0 1 17.25 21H6.75A.75.75 0 0 1 6 20.25V2.25Z"></path><path d="M14.25 2.25V6H18"></path><path d="M7.5 13.5h9m-9 3h6"></path>',
        excel: '<path d="M6 2.25h8.25L18 6v15A.75.75 0 0 1 17.25 21H6.75A.75.75 0 0 1 6 20.25V2.25Z"></path><path d="M14.25 2.25V6H18"></path><path d="M7.5 10.5h9"></path><path d="M7.5 13.5h9"></path><path d="M7.5 16.5h9"></path><path d="M9 10.5v6"></path><path d="M12 10.5v6"></path><path d="M15 10.5v6"></path>',
        print: '<path d="M7.5 8.25V3.75h9v4.5"></path><path d="M6.75 16.5h10.5v3.75H6.75z"></path><path d="M5.25 8.25h13.5A2.25 2.25 0 0 1 21 10.5v4.5a2.25 2.25 0 0 1-2.25 2.25h-1.5V13.5H6.75v3.75h-1.5A2.25 2.25 0 0 1 3 15v-4.5a2.25 2.25 0 0 1 2.25-2.25Z"></path><path d="M17.25 11.25h.01"></path>',
        download: '<path d="M12 3.75v11.25"></path><path d="M7.5 10.5 12 15l4.5-4.5"></path><path d="M4.5 18.75h15"></path>',
        filter: '<path d="M4.5 5.25h15"></path><path d="M7.5 12h9"></path><path d="M10.5 18.75h3"></path>',
        search: '<path d="M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Z"></path><path d="M16.06 16.06 21 21"></path>',
        chart: '<path d="M4.5 19.5h15"></path><path d="M7.5 16.5V9"></path><path d="M12 16.5V4.5"></path><path d="M16.5 16.5v-6"></path>',
        list: '<path d="M8.25 6.75h11.25"></path><path d="M8.25 12h11.25"></path><path d="M8.25 17.25h11.25"></path><path d="M4.5 6.75h.01M4.5 12h.01M4.5 17.25h.01"></path>',
        save: '<path d="M4.5 4.5h12l3 3v12a.75.75 0 0 1-.75.75H4.5a.75.75 0 0 1-.75-.75V5.25a.75.75 0 0 1 .75-.75Z"></path><path d="M7.5 4.5V9h9V4.5"></path><path d="M8.25 15h7.5"></path>',
        refresh: '<path d="M20.25 12a8.25 8.25 0 1 1-2.11-5.49"></path><path d="M20.25 4.5v6h-6"></path>'
      };

      function normalizeLabel(value){
        return String(value || '').replace(/\s+/g, ' ').trim();
      }

      function iconSvg(name){
        var paths = iconPaths[name] || iconPaths.save;
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" aria-hidden="true" focusable="false">' + paths + '</svg>';
      }

      function inferIcon(label, el){
        var txt = normalizeLabel(label).toLowerCase();
        try {
          txt = txt.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        } catch (e) {}
        var href = String((el && el.getAttribute && (el.getAttribute('href') || el.getAttribute('action'))) || '').toLowerCase();
        if (/voltar|retornar|anterior/.test(txt)) return 'arrow-left';
        if (/imprimir/.test(txt)) return 'print';
        if (/pdf/.test(txt)) return 'pdf';
        if (/excel|xlsx|planilha/.test(txt)) return 'excel';
        if (/preview|visualizar|detalhes|ver|abrir/.test(txt)) return 'eye';
        if (/baixar|download|comprovante/.test(txt)) return 'download';
        if (/editar|alterar/.test(txt)) return 'edit';
        if (/excluir|remover|deletar/.test(txt)) return 'trash';
        if (/novo|nova|adicionar|criar|duplicar/.test(txt)) return 'plus';
        if (/salvar|enviar|gerar|registrar|instalar|entrar/.test(txt)) return 'save';
        if (/filtro|filtrar|aplicar/.test(txt)) return 'filter';
        if (/buscar|pesquisar/.test(txt)) return 'search';
        if (/dashboard|painel|indicador/.test(txt)) return 'chart';
        if (/recebiveis/.test(txt)) return 'dollar';
        if (/lista|listagem|relatorios/.test(txt)) return 'list';
        if (/cancelar|fechar/.test(txt)) return 'x';
        if (/aprovar|confirmar|pagar|baixa/.test(txt)) return 'check';
        if (/renegociar|estornar|atualizar|sincronizar|reabrir/.test(txt) || /refresh/.test(href)) return 'refresh';
        return 'save';
      }

      function ensureAccessible(el){
        var label = normalizeLabel(el.getAttribute('aria-label') || el.getAttribute('title') || el.dataset.originalLabel || el.textContent);
        if (label !== '' && !el.getAttribute('aria-label')) {
          el.setAttribute('aria-label', label);
        }
        if (label !== '' && !el.getAttribute('title')) {
          el.setAttribute('title', label);
        }
        var svg = el.querySelector('svg');
        if (svg) {
          svg.setAttribute('aria-hidden', 'true');
          svg.setAttribute('focusable', 'false');
        }
      }

      function iconify(el){
        if (!el || el.dataset.noIconify === 'true') {
          return;
        }

        if (el.classList.contains('tr-icon-btn')) {
          ensureAccessible(el);
          return;
        }

        if (!el.classList.contains('tr-btn')) {
          return;
        }

        if (el.querySelector('svg, img')) {
          ensureAccessible(el);
          return;
        }

        var label = normalizeLabel(el.textContent);
        if (label === '') {
          return;
        }

        el.dataset.originalLabel = label;
        el.innerHTML = iconSvg(inferIcon(label, el));
        el.classList.add('tr-btn--icon-only');
        ensureAccessible(el);
      }

      function run(root){
        var scope = root || document;
        scope.querySelectorAll('.tr-btn, .tr-icon-btn').forEach(iconify);
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ run(document); }, { once: true });
      } else {
        run(document);
      }

      if ('MutationObserver' in window) {
        var observer = new MutationObserver(function(records){
          records.forEach(function(record){
            record.addedNodes.forEach(function(node){
              if (!node || node.nodeType !== 1) {
                return;
              }
              if (node.matches && (node.matches('.tr-btn') || node.matches('.tr-icon-btn'))) {
                iconify(node);
              }
              if (node.querySelectorAll) {
                run(node);
              }
            });
          });
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
      }

      window.trIconify = run;

      function ensure(){
        var el = document.getElementById('trToast');
        if (!el) {
          el = document.createElement('div');
          el.id = 'trToast';
          el.className = 'tr-toast';
          if (document.body) {
            document.body.appendChild(el);
          } else {
            document.addEventListener('DOMContentLoaded', function(){
              if (document.body && !document.getElementById('trToast')) {
                document.body.appendChild(el);
              }
            }, { once: true });
          }
        }
        return el;
      }
      window.trToast = function(type, message){
        var el = ensure();
        var t = (type === 'success' || type === 'error' || type === 'warning' || type === 'info') ? type : 'info';
        el.className = 'tr-toast ' + t + ' show';
        el.textContent = String(message || '');
        window.clearTimeout(window.__trToastT);
        window.__trToastT = window.setTimeout(function(){ el.classList.remove('show'); }, 4200);
      };
    })();
  </script>
  <script>
    (function(){
      function formatDigitsToBrl(digits){
        var n = digits ? (parseInt(digits, 10) / 100) : 0;
        if (!isFinite(n)) n = 0;
        return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      }

      function maskInput(el){
        var digits = String(el.value || '').replace(/\D/g, '');
        if (digits === '') {
          el.value = '';
          return;
        }
        el.value = formatDigitsToBrl(digits);
      }

      document.addEventListener('input', function(e){
        var t = e.target;
        if (t && t.matches && t.matches('input[data-money="brl"]')) {
          maskInput(t);
        }
      });
    })();
  </script>
