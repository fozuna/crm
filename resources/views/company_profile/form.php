<?php
use App\Core\Session;
use App\Core\UI;
use App\Core\View;

$title = 'Perfil Empresarial';
$p = is_array($profile ?? null) ? $profile : [];
$errors = is_array($errors ?? null) ? $errors : [];

$phonesValue = '';
$phones = $p['phones'] ?? null;
if (is_array($phones)) {
  $phonesValue = implode("\n", array_map('strval', $phones));
} else {
  $phonesValue = (string)($p['phones'] ?? '');
}

$logoLightUrl = $base . '/empresa/logo/light';
$logoDarkUrl = $base . '/empresa/logo/dark';
$faviconUrl = $base . '/empresa/ativo/favicon';
$metaImageUrl = $base . '/empresa/ativo/meta-image';
$hasLight = !empty($p['logo_light_path']);
$hasDark = !empty($p['logo_dark_path']);
$hasFavicon = !empty($p['favicon_path']);
$hasMetaImage = !empty($p['meta_image_path']);
?>

<div class="flex items-center justify-between">
  <div>
    <div class="text-2xl font-semibold">Perfil Empresarial</div>
    <div class="text-slate-600 mt-1">Dados oficiais e identidade visual para PDFs e telas</div>
  </div>
  <div class="flex items-center gap-2">
    <a class="tr-icon-btn" href="<?= View::e($base . '/empresa/auditoria') ?>" aria-label="Auditoria">
      <?= UI::icon('eye') ?>
      <span class="sr-only">Auditoria</span>
    </a>
  </div>
</div>

<?php if (!empty($error)): ?>
  <div class="mt-4 rounded bg-red-50 text-red-700 px-4 py-3 text-sm"><?= View::e((string)$error) ?></div>
<?php endif; ?>

<form method="post" action="<?= View::e($base . '/empresa') ?>" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
  <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">

  <div class="lg:col-span-2 tr-card p-6">
    <div class="text-sm font-semibold text-slate-700">Informações da empresa</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div class="md:col-span-2">
        <label class="tr-label">Razão Social</label>
        <input name="legal_name" value="<?= View::e((string)($p['legal_name'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['legal_name']) ? 'border-red-400 bg-red-50' : '' ?>" required>
        <?php if (isset($errors['legal_name'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['legal_name']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">Nome Fantasia</label>
        <input name="trade_name" value="<?= View::e((string)($p['trade_name'] ?? '')) ?>" class="mt-1 tr-input">
      </div>

      <div>
        <label class="tr-label">CNPJ</label>
        <input name="cnpj" value="<?= View::e((string)($p['cnpj'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['cnpj']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="00.000.000/0000-00" required>
        <?php if (isset($errors['cnpj'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['cnpj']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">Domínio</label>
        <input name="domain" value="<?= View::e((string)($p['domain'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['domain']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="suaempresa.com.br" required>
        <?php if (isset($errors['domain'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['domain']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">E-mail corporativo</label>
        <input name="email" value="<?= View::e((string)($p['email'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['email']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="contato@suaempresa.com.br" required>
        <?php if (isset($errors['email'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['email']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">Website</label>
        <input name="website" value="<?= View::e((string)($p['website'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['website']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="https://suaempresa.com.br">
        <?php if (isset($errors['website'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['website']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">Nome da marca</label>
        <input name="brand_name" value="<?= View::e((string)($p['brand_name'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['brand_name']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="TRAXTER">
        <?php if (isset($errors['brand_name'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['brand_name']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">Tagline</label>
        <input name="brand_tagline" value="<?= View::e((string)($p['brand_tagline'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['brand_tagline']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="Soluções SaaS para gestão">
        <?php if (isset($errors['brand_tagline'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['brand_tagline']) ?></div><?php endif; ?>
      </div>

      <div>
        <label class="tr-label">WhatsApp</label>
        <input name="whatsapp" value="<?= View::e((string)($p['whatsapp'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['whatsapp']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="+55 (67) 99999-9999" required>
        <?php if (isset($errors['whatsapp'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['whatsapp']) ?></div><?php endif; ?>
      </div>

      <div class="md:col-span-2">
        <label class="tr-label">Telefones comerciais (um por linha)</label>
        <textarea name="phones" rows="3" class="mt-1 tr-input <?= isset($errors['phones']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="(67) 3333-3333\n(67) 98888-8888"><?= View::e($phonesValue) ?></textarea>
        <?php if (isset($errors['phones'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['phones']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="mt-8 text-sm font-semibold text-slate-700">Endereço</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="tr-label">CEP</label>
        <input name="zip" value="<?= View::e((string)($p['address']['zip'] ?? $p['zip'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['zip']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="79000-000">
        <?php if (isset($errors['zip'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['zip']) ?></div><?php endif; ?>
      </div>
      <div>
        <label class="tr-label">UF</label>
        <input name="state" value="<?= View::e((string)($p['address']['state'] ?? $p['state'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['state']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="MS">
        <?php if (isset($errors['state'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['state']) ?></div><?php endif; ?>
      </div>
      <div class="md:col-span-2">
        <label class="tr-label">Logradouro</label>
        <input name="street" value="<?= View::e((string)($p['address']['street'] ?? $p['street'] ?? '')) ?>" class="mt-1 tr-input" placeholder="Rua ...">
      </div>
      <div>
        <label class="tr-label">Número</label>
        <input name="number" value="<?= View::e((string)($p['address']['number'] ?? $p['number'] ?? '')) ?>" class="mt-1 tr-input" placeholder="123">
      </div>
      <div>
        <label class="tr-label">Complemento</label>
        <input name="complement" value="<?= View::e((string)($p['address']['complement'] ?? $p['complement'] ?? '')) ?>" class="mt-1 tr-input" placeholder="Sala 10">
      </div>
      <div>
        <label class="tr-label">Bairro</label>
        <input name="neighborhood" value="<?= View::e((string)($p['address']['neighborhood'] ?? $p['neighborhood'] ?? '')) ?>" class="mt-1 tr-input">
      </div>
      <div>
        <label class="tr-label">Cidade</label>
        <input name="city" value="<?= View::e((string)($p['address']['city'] ?? $p['city'] ?? '')) ?>" class="mt-1 tr-input">
      </div>
    </div>

    <div class="mt-8 text-sm font-semibold text-slate-700">Identidade visual consolidada</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
      <div>
        <label class="tr-label">Cor primária</label>
        <div class="mt-1 flex items-center gap-3">
          <input name="primary_color" value="<?= View::e((string)($p['primary_color'] ?? '#293241')) ?>" class="tr-input <?= isset($errors['primary_color']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="#293241">
          <span class="h-10 w-10 rounded-lg border border-slate-300 shrink-0" style="background: <?= View::e((string)($p['primary_color'] ?? '#293241')) ?>"></span>
        </div>
        <?php if (isset($errors['primary_color'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['primary_color']) ?></div><?php endif; ?>
      </div>
      <div>
        <label class="tr-label">Cor de destaque</label>
        <div class="mt-1 flex items-center gap-3">
          <input name="accent_color" value="<?= View::e((string)($p['accent_color'] ?? '#ee6c4d')) ?>" class="tr-input <?= isset($errors['accent_color']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="#ee6c4d">
          <span class="h-10 w-10 rounded-lg border border-slate-300 shrink-0" style="background: <?= View::e((string)($p['accent_color'] ?? '#ee6c4d')) ?>"></span>
        </div>
        <?php if (isset($errors['accent_color'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['accent_color']) ?></div><?php endif; ?>
      </div>
      <div>
        <label class="tr-label">Tipografia base</label>
        <input name="font_name" value="<?= View::e((string)($p['font_name'] ?? 'Helvetica')) ?>" class="mt-1 tr-input <?= isset($errors['font_name']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="Helvetica">
        <?php if (isset($errors['font_name'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['font_name']) ?></div><?php endif; ?>
      </div>
      <div>
        <label class="tr-label">Meta title</label>
        <input name="meta_title" value="<?= View::e((string)($p['meta_title'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['meta_title']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="TRAXTER CRM">
        <?php if (isset($errors['meta_title'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['meta_title']) ?></div><?php endif; ?>
      </div>
      <div class="md:col-span-2">
        <label class="tr-label">Meta description</label>
        <textarea name="meta_description" rows="3" class="mt-1 tr-input <?= isset($errors['meta_description']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="Descrição institucional e de branding para SEO e compartilhamento."><?= View::e((string)($p['meta_description'] ?? '')) ?></textarea>
        <?php if (isset($errors['meta_description'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['meta_description']) ?></div><?php endif; ?>
      </div>
      <div class="md:col-span-2">
        <label class="tr-label">Meta keywords</label>
        <input name="meta_keywords" value="<?= View::e((string)($p['meta_keywords'] ?? '')) ?>" class="mt-1 tr-input <?= isset($errors['meta_keywords']) ? 'border-red-400 bg-red-50' : '' ?>" placeholder="crm, rh, recrutamento, saas">
        <?php if (isset($errors['meta_keywords'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['meta_keywords']) ?></div><?php endif; ?>
      </div>
    </div>

    <div class="flex items-center justify-end gap-3 mt-8">
      <button class="tr-icon-btn tr-icon-btn--accent" aria-label="Salvar">
        <?= UI::icon('save') ?>
        <span class="sr-only">Salvar</span>
      </button>
    </div>
  </div>

  <div class="tr-card p-6">
    <div class="text-sm font-semibold text-slate-700">Ativos visuais</div>
    <div class="text-xs text-slate-500 mt-1">Todos os elementos de branding ficam centralizados aqui. A logo legada de Branding não é mais utilizada.</div>

    <div class="mt-4">
      <div class="text-xs font-semibold text-slate-700">Logo claro (para fundo escuro)</div>
      <div class="mt-2 rounded-lg border border-slate-200 bg-traxterSidebar p-3 flex items-center justify-center h-28">
        <?php if ($hasLight): ?>
          <img src="<?= View::e($logoLightUrl) ?>" alt="Logo claro" class="max-h-20 max-w-full object-contain" loading="lazy">
        <?php else: ?>
          <div class="text-traxterText/70 text-xs font-semibold">Sem logo claro</div>
        <?php endif; ?>
      </div>
      <input name="logo_light" type="file" accept="image/png,image/jpeg,image/svg+xml" class="mt-3 tr-input">
      <?php if (isset($errors['logo_light'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['logo_light']) ?></div><?php endif; ?>
    </div>

    <div class="mt-6">
      <div class="text-xs font-semibold text-slate-700">Logo escuro (para fundo claro)</div>
      <div class="mt-2 rounded-lg border border-slate-200 bg-white p-3 flex items-center justify-center h-28">
        <?php if ($hasDark): ?>
          <img src="<?= View::e($logoDarkUrl) ?>" alt="Logo escuro" class="max-h-20 max-w-full object-contain" loading="lazy">
        <?php else: ?>
          <div class="text-slate-400 text-xs font-semibold">Sem logo escuro</div>
        <?php endif; ?>
      </div>
      <input name="logo_dark" type="file" accept="image/png,image/jpeg,image/svg+xml" class="mt-3 tr-input">
      <?php if (isset($errors['logo_dark'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['logo_dark']) ?></div><?php endif; ?>
    </div>

    <div class="mt-6">
      <div class="text-xs font-semibold text-slate-700">Favicon</div>
      <div class="mt-2 rounded-lg border border-slate-200 bg-white p-3 flex items-center justify-center h-24">
        <?php if ($hasFavicon): ?>
          <img src="<?= View::e($faviconUrl) ?>" alt="Favicon" class="max-h-12 max-w-full object-contain" loading="lazy">
        <?php else: ?>
          <div class="text-slate-400 text-xs font-semibold">Sem favicon</div>
        <?php endif; ?>
      </div>
      <input name="favicon" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,.ico" class="mt-3 tr-input">
      <?php if (isset($errors['favicon'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['favicon']) ?></div><?php endif; ?>
    </div>

    <div class="mt-6">
      <div class="text-xs font-semibold text-slate-700">Imagem social / metatag</div>
      <div class="mt-2 rounded-lg border border-slate-200 bg-white p-3 flex items-center justify-center h-32">
        <?php if ($hasMetaImage): ?>
          <img src="<?= View::e($metaImageUrl) ?>" alt="Imagem social" class="max-h-28 max-w-full object-contain" loading="lazy">
        <?php else: ?>
          <div class="text-slate-400 text-xs font-semibold">Sem imagem social</div>
        <?php endif; ?>
      </div>
      <input name="meta_image" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="mt-3 tr-input">
      <?php if (isset($errors['meta_image'])): ?><div class="mt-1 text-xs text-red-700"><?= View::e((string)$errors['meta_image']) ?></div><?php endif; ?>
    </div>

    <div class="mt-6 text-xs text-slate-600">
      <div class="font-semibold">Acesso</div>
      <div class="mt-1">Visível apenas para administradores: <?= View::e((string)Session::get('user_name','')) ?></div>
    </div>
  </div>
</form>
