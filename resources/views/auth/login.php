<?php
use App\Core\Config;
use App\Core\UI;
use App\Core\View;
use App\Services\CompanyProfileService;

$branding = [
    'company_name' => 'TRAXTER CRM',
    'brand_tagline' => 'Gestão empresarial moderna para operações comerciais, financeiras e operacionais.',
    'primary_color' => '#293241',
    'accent_color' => '#ee6c4d',
    'meta_title' => 'TRAXTER CRM',
    'meta_description' => '',
    'meta_keywords' => '',
    'company_cnpj' => '',
    'company_email' => 'comercial@traxter.com.br',
    'company_whatsapp' => '+5567993256260',
    'company_website' => '',
];

try {
    $branding = array_merge($branding, (new CompanyProfileService())->branding());
} catch (\Throwable $e) {
}

$companyName = trim((string) ($branding['company_name'] ?? 'TRAXTER CRM'));
$tagline = trim((string) ($branding['brand_tagline'] ?? ''));
if ($tagline === '') {
    $tagline = 'Gestão comercial, financeira e operacional em um software SaaS premium.';
}

$canonicalUrl = rtrim((string) Config::get('APP_URL', ''), '/');
if ($canonicalUrl === '') {
    $canonicalUrl = $base . '/login';
} else {
    $canonicalUrl .= '/login';
}

$pageTitle = $companyName . ' | Gestão inteligente para grandes resultados';
$pageDescription = 'TRAXTER CRM centraliza clientes, propostas, projetos, financeiro, contratos e ordens de serviço em uma experiência SaaS moderna, segura e profissional.';
$pageKeywords = 'CRM, SaaS, gestão comercial, financeiro, propostas, projetos, contratos, ordem de serviço';
$pageCanonical = $canonicalUrl;
$pageOgType = 'website';
$pageSchemaJsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $companyName,
    'url' => (string) ($branding['company_website'] ?? $canonicalUrl),
    'email' => (string) ($branding['company_email'] ?? ''),
    'telephone' => (string) ($branding['company_whatsapp'] ?? ''),
];

$logoDataUri = '';
$logoPath = trim((string) ($branding['logo_dark_path'] ?? ''));
$logoMime = trim((string) ($branding['logo_dark_mime'] ?? ''));
if ($logoPath !== '' && is_file($logoPath)) {
    $binary = @file_get_contents($logoPath);
    if ($binary !== false && $binary !== '') {
        $logoDataUri = 'data:' . ($logoMime !== '' ? $logoMime : 'image/png') . ';base64,' . base64_encode($binary);
    }
}

$featureCards = [
    ['icon' => 'users', 'title' => 'Gestão de Clientes', 'description' => 'Centralize cadastro, relacionamento, histórico e funil comercial em um único lugar.'],
    ['icon' => 'briefcase', 'title' => 'Propostas Comerciais', 'description' => 'Crie propostas profissionais, acompanhe aprovações e formalize oportunidades com agilidade.'],
    ['icon' => 'folder', 'title' => 'Projetos', 'description' => 'Organize entregas, etapas, responsáveis e acompanhamento operacional do início ao fim.'],
    ['icon' => 'wallet', 'title' => 'Financeiro', 'description' => 'Controle recebíveis, pagamentos, fluxo de caixa e indicadores com rastreabilidade.'],
    ['icon' => 'chart', 'title' => 'Dashboard', 'description' => 'Visualize indicadores essenciais com leitura rápida e foco na tomada de decisão.'],
    ['icon' => 'list', 'title' => 'Agenda', 'description' => 'Monitore compromissos, prazos e atividades com uma rotina mais previsível.'],
    ['icon' => 'book-open', 'title' => 'Ordem de Serviço', 'description' => 'Registre demandas pontuais, histórico técnico, anexos e cobranças vinculadas.'],
    ['icon' => 'shield', 'title' => 'Contratos', 'description' => 'Formalize acordos, acompanhe versões e preserve o padrão institucional da operação.'],
];

$benefits = [
    'Organização completa de clientes, funil, propostas e execução.',
    'Controle financeiro integrado com mais previsibilidade.',
    'Centralização de documentos, contratos e históricos.',
    'Acompanhamento de tarefas e prazos sem dispersão.',
    'Automatização de processos que reduzem retrabalho.',
    'Experiência premium com foco em produtividade e clareza.',
];

$productivityItems = [
    'Economize tempo com fluxos mais organizados.',
    'Organize seus clientes e oportunidades com clareza.',
    'Nunca perca um prazo importante.',
    'Controle financeiro integrado sem planilhas paralelas.',
    'Gestão completa em um único ambiente.',
];

$errorMessage = trim((string) ($error ?? ''));
$openModal = $errorMessage !== '';
?>
<!doctype html>
<html lang="pt-br">
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
  .landing-grid{
    background-image:
      linear-gradient(rgba(41,50,65,.07) 1px, transparent 1px),
      linear-gradient(90deg, rgba(41,50,65,.07) 1px, transparent 1px);
    background-size: 28px 28px;
  }
  .landing-glow{
    background:
      radial-gradient(circle at top left, rgba(238,108,77,.18), transparent 34%),
      radial-gradient(circle at bottom right, rgba(41,50,65,.18), transparent 38%);
  }
  .landing-shell{
    background: linear-gradient(180deg, rgba(255,255,255,.98) 0%, rgba(248,250,252,.96) 100%);
  }
  .mock-chart-bar{border-radius:9999px 9999px .5rem .5rem;background:linear-gradient(180deg, rgba(238,108,77,.95), rgba(238,108,77,.45));}
  .mock-line{position:relative;height:96px}
  .mock-line svg{position:absolute;inset:0;width:100%;height:100%}
  .modal-overlay{transition:opacity .24s ease}
  .modal-panel{transition:transform .24s ease, opacity .24s ease}
  .modal-hidden{pointer-events:none}
  .modal-hidden .modal-overlay{opacity:0}
  .modal-hidden .modal-panel{opacity:0;transform:translateY(16px) scale(.96)}
  .menu-hidden{display:none}
  .site-header{z-index:40}
  .site-header__menu{display:none}
  .site-header__menu.is-open{display:block}
  .site-header__logo{max-height:2.25rem;width:auto;object-fit:contain}
  @media (min-width: 768px){
    .site-header__menu{display:none !important}
  }
</style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
  <div class="relative overflow-hidden">
    <div class="absolute inset-0 landing-grid landing-glow pointer-events-none"></div>

    <header id="siteHeader" class="site-header fixed top-0 inset-x-0 border-b border-slate-200/80 bg-white/95 backdrop-blur">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="#topo" class="flex min-w-0 items-center gap-2 sm:gap-3" aria-label="TRAXTER CRM">
          <?php if ($logoDataUri !== ''): ?>
            <span class="flex h-10 shrink-0 items-center rounded-xl bg-white px-1.5 sm:h-11">
              <img src="<?= View::e($logoDataUri) ?>" alt="<?= View::e($companyName) ?>" class="site-header__logo">
            </span>
          <?php else: ?>
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-traxterSidebar text-traxterText shadow-sm sm:h-11 sm:w-11">
              <?= UI::icon('chart') ?>
            </span>
          <?php endif; ?>
          
        </a>

        <button id="mobileMenuButton" type="button" class="tr-icon-btn md:hidden" aria-expanded="false" aria-controls="mobileMenu" aria-label="Abrir menu">
          <?= UI::icon('list') ?>
        </button>

        <nav class="hidden items-center gap-2 md:flex">
          <a class="px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-traxterAccent" href="#recursos">Recursos</a>
          <a class="px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-traxterAccent" href="#solucoes">Soluções</a>
          <a class="px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-traxterAccent" href="#beneficios">Benefícios</a>
          <a class="px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-traxterAccent" href="#planos">Planos</a>
          <a class="px-3 py-2 text-sm font-medium text-slate-600 transition hover:text-traxterAccent" href="#contato">Contato</a>
          <button type="button" class="tr-btn tr-icon-btn--accent ml-3 px-5 py-2 text-sm" data-no-iconify="true" data-open-login="1">Entrar</button>
        </nav>
      </div>

      <div id="mobileMenu" class="site-header__menu border-t border-slate-200 bg-white md:hidden" hidden aria-hidden="true">
        <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 sm:px-6">
          <a class="rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" href="#recursos">Recursos</a>
          <a class="rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" href="#solucoes">Soluções</a>
          <a class="rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" href="#beneficios">Benefícios</a>
          <a class="rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" href="#planos">Planos</a>
          <a class="rounded-xl px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100" href="#contato">Contato</a>
          <button type="button" class="tr-btn tr-icon-btn--accent mt-2 w-full justify-center px-5 py-3 text-sm" data-no-iconify="true" data-open-login="1">Entrar</button>
        </div>
      </div>
    </header>

    <div id="headerSpacer" aria-hidden="true"></div>

    <main id="topo" class="relative z-10">
      <section class="px-4 pb-16 pt-10 sm:px-6 lg:px-8 lg:pb-24 lg:pt-16">
        <div class="mx-auto grid max-w-7xl items-center gap-12 lg:grid-cols-[1.05fr_.95fr]">
          <div>
            <span class="tr-badge border-orange-200 bg-orange-50 text-traxterAccent">Gestão inteligente para operações premium</span>
            <h1 class="mt-6 max-w-3xl text-4xl font-semibold leading-tight text-traxterSidebar sm:text-5xl lg:text-6xl">
              Gestão inteligente para quem desenvolve grandes resultados.
            </h1>
            <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">
              O <?= View::e($companyName) ?> organiza clientes, propostas, contratos, projetos, financeiro e ordens de serviço em uma experiência SaaS moderna, segura e focada em produtividade.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <button type="button" class="tr-btn tr-icon-btn--accent px-6 py-3 text-base" data-no-iconify="true" data-open-login="1">Começar Agora</button>
              <button type="button" class="tr-btn px-6 py-3 text-base" data-no-iconify="true" data-open-login="1">Entrar</button>
            </div>

            <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-3">
              <div class="tr-card border border-slate-200 p-4">
                <div class="text-sm text-slate-500">Clientes organizados</div>
                <div class="mt-2 text-2xl font-semibold text-traxterSidebar">+1.280</div>
              </div>
              <div class="tr-card border border-slate-200 p-4">
                <div class="text-sm text-slate-500">Financeiro rastreável</div>
                <div class="mt-2 text-2xl font-semibold text-traxterSidebar">99,9%</div>
              </div>
              <div class="tr-card border border-slate-200 p-4">
                <div class="text-sm text-slate-500">Fluxos integrados</div>
                <div class="mt-2 text-2xl font-semibold text-traxterSidebar">360°</div>
              </div>
            </div>
          </div>

          <div class="relative">
            <div class="landing-shell tr-card relative overflow-hidden border border-slate-200 p-4 shadow-xl shadow-slate-900/5 sm:p-6">
              <div class="flex items-center justify-between rounded-2xl bg-traxterSidebar px-5 py-4 text-traxterText">
                <div>
                  <div class="text-sm font-medium text-slate-300">Painel executivo</div>
                  <div class="mt-1 text-2xl font-semibold"><?= View::e($companyName) ?></div>
                </div>
                <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold text-white">Tempo real</span>
              </div>

              <div class="mt-5 grid gap-4 lg:grid-cols-[1.2fr_.8fr]">
                <div class="space-y-4">
                  <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                      <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">Receita prevista</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">+18%</span>
                      </div>
                      <div class="mt-3 text-3xl font-semibold text-traxterSidebar">R$ 84 mil</div>
                      <div class="mt-2 text-xs text-slate-500">Visão consolidada comercial + financeiro</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                      <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-500">OS em andamento</span>
                        <span class="rounded-full bg-orange-50 px-2 py-1 text-xs font-semibold text-traxterAccent">24 ativas</span>
                      </div>
                      <div class="mt-3 text-3xl font-semibold text-traxterSidebar">06</div>
                      <div class="mt-2 text-xs text-slate-500">Equipes com prioridade clara e prazo visível</div>
                    </div>
                  </div>

                  <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                      <div>
                        <div class="text-sm font-medium text-slate-500">Performance mensal</div>
                        <div class="text-xs text-slate-400">Indicadores integrados do CRM</div>
                      </div>
                      <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Dashboard</span>
                    </div>
                    <div class="mt-5 flex items-end gap-3">
                      <div class="mock-chart-bar h-16 w-full"></div>
                      <div class="mock-chart-bar h-24 w-full"></div>
                      <div class="mock-chart-bar h-20 w-full"></div>
                      <div class="mock-chart-bar h-28 w-full"></div>
                      <div class="mock-chart-bar h-36 w-full"></div>
                      <div class="mock-chart-bar h-24 w-full"></div>
                    </div>
                  </div>
                </div>

                <div class="space-y-4">
                  <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <div class="text-sm font-medium text-slate-500">Pipeline comercial</div>
                    <div class="mock-line mt-4 rounded-2xl bg-slate-50 p-2">
                      <svg viewBox="0 0 300 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 78C42 70 46 24 86 30C120 35 141 82 182 66C215 53 235 15 292 21" stroke="#ee6c4d" stroke-width="4" stroke-linecap="round"/>
                        <path d="M8 78C42 70 46 24 86 30C120 35 141 82 182 66C215 53 235 15 292 21" stroke="url(#fadeLine)" stroke-width="10" stroke-linecap="round" opacity=".2"/>
                        <defs>
                          <linearGradient id="fadeLine" x1="8" y1="10" x2="292" y2="10" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#ee6c4d"/>
                            <stop offset="1" stop-color="#293241"/>
                          </linearGradient>
                        </defs>
                      </svg>
                    </div>
                  </div>

                  <div class="rounded-2xl border border-slate-200 bg-traxterSidebar p-4 text-white">
                    <div class="flex items-center justify-between">
                      <div>
                        <div class="text-sm text-slate-300">Automação e controle</div>
                        <div class="mt-1 text-xl font-semibold">Tudo conectado</div>
                      </div>
                      <span class="rounded-full border border-white/15 px-3 py-1 text-xs font-semibold">SaaS Premium</span>
                    </div>
                    <ul class="mt-4 space-y-3 text-sm text-slate-200">
                      <li class="flex items-center gap-2"><?= UI::icon('check', 'h-4 w-4') ?><span>Clientes, propostas e contratos sincronizados</span></li>
                      <li class="flex items-center gap-2"><?= UI::icon('check', 'h-4 w-4') ?><span>Financeiro integrado com rastreabilidade</span></li>
                      <li class="flex items-center gap-2"><?= UI::icon('check', 'h-4 w-4') ?><span>Ordens de serviço e projetos sob controle</span></li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="pointer-events-none absolute -bottom-10 -left-10 h-28 w-28 rounded-full bg-traxterAccent/20 blur-3xl"></div>
              <div class="pointer-events-none absolute -right-8 top-12 h-24 w-24 rounded-full bg-traxterSidebar/15 blur-3xl"></div>
            </div>
          </div>
        </div>
      </section>

      <section id="recursos" class="px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
          <div class="max-w-3xl">
            <span class="tr-badge">Recursos principais</span>
            <h2 class="mt-4 text-3xl font-semibold text-traxterSidebar sm:text-4xl">Tudo o que sua operação precisa para vender, executar e faturar melhor.</h2>
            <p class="mt-4 text-lg text-slate-600">Recursos integrados para manter informações centralizadas, processos organizados e tomada de decisão orientada por dados.</p>
          </div>

          <div class="mt-10 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($featureCards as $card): ?>
              <article class="tr-card group border border-slate-200 p-5 transition duration-200 hover:-translate-y-1 hover:border-orange-200 hover:shadow-lg hover:shadow-orange-100/40">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-traxterAccent transition group-hover:bg-orange-50">
                  <?= UI::icon((string) $card['icon']) ?>
                </div>
                <h3 class="mt-5 text-lg font-semibold text-traxterSidebar"><?= View::e((string) $card['title']) ?></h3>
                <p class="mt-3 text-sm leading-6 text-slate-600"><?= View::e((string) $card['description']) ?></p>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section id="solucoes" class="bg-traxterSidebar px-4 py-16 text-white sm:px-6 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[.95fr_1.05fr] lg:items-center">
          <div>
            <span class="tr-badge border-white/15 bg-white/10 text-white">Diferenciais</span>
            <h2 class="mt-4 text-3xl font-semibold sm:text-4xl">Um CRM pensado para operações que exigem organização, controle e credibilidade.</h2>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-slate-300">O TRAXTER CRM conecta comercial, operação e financeiro em uma jornada única para sua empresa crescer com mais previsibilidade e menos retrabalho.</p>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <?php foreach ($benefits as $item): ?>
              <div class="rounded-2xl border border-white/10 bg-white/5 p-5 backdrop-blur-sm">
                <div class="flex items-start gap-3">
                  <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-white/10 text-traxterAccent"><?= UI::icon('check', 'h-4 w-4') ?></span>
                  <p class="text-sm leading-6 text-slate-200"><?= View::e($item) ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>

      <section id="beneficios" class="px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1fr_.9fr] lg:items-center">
          <div>
            <span class="tr-badge">Produtividade</span>
            <h2 class="mt-4 text-3xl font-semibold text-traxterSidebar sm:text-4xl">Sua gestão em um único lugar, com menos ruído e mais resultado.</h2>
            <div class="mt-8 space-y-4">
              <?php foreach ($productivityItems as $item): ?>
                <div class="tr-card flex items-start gap-4 border border-slate-200 p-4">
                  <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-orange-50 text-traxterAccent"><?= UI::icon('check') ?></span>
                  <div>
                    <div class="font-semibold text-traxterSidebar"><?= View::e($item) ?></div>
                    <div class="mt-1 text-sm text-slate-600">Fluxos intuitivos, indicadores claros e acompanhamento operacional com foco em eficiência.</div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="tr-card border border-slate-200 p-6">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-sm font-medium text-slate-500">Panorama de produtividade</div>
                <div class="text-2xl font-semibold text-traxterSidebar">Operação organizada</div>
              </div>
              <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-traxterAccent">+Eficiência</span>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
              <div class="rounded-2xl bg-slate-50 p-4">
                <div class="text-sm text-slate-500">Prazos monitorados</div>
                <div class="mt-2 text-3xl font-semibold text-traxterSidebar">97%</div>
              </div>
              <div class="rounded-2xl bg-slate-50 p-4">
                <div class="text-sm text-slate-500">Processos centralizados</div>
                <div class="mt-2 text-3xl font-semibold text-traxterSidebar">8 módulos</div>
              </div>
              <div class="rounded-2xl bg-slate-50 p-4 sm:col-span-2">
                <div class="text-sm text-slate-500">Experiência operacional</div>
                <div class="mt-4 flex items-center gap-3">
                  <span class="h-3 flex-1 rounded-full bg-slate-200"><span class="block h-3 rounded-full bg-traxterAccent" style="width:82%"></span></span>
                  <span class="text-sm font-semibold text-traxterSidebar">82%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="bg-slate-100/80 px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
          <div class="max-w-3xl">
            <span class="tr-badge">Dashboard</span>
            <h2 class="mt-4 text-3xl font-semibold text-traxterSidebar sm:text-4xl">Visual moderno para acompanhar o que realmente importa.</h2>
            <p class="mt-4 text-lg text-slate-600">Indicadores, cards, badges e gráficos organizados para leitura executiva e acompanhamento contínuo da operação.</p>
          </div>

          <div class="mt-10 grid gap-6 lg:grid-cols-[1.15fr_.85fr]">
            <div class="tr-card border border-slate-200 p-6">
              <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                  <div class="text-sm text-slate-500">Recebíveis</div>
                  <div class="mt-2 text-2xl font-semibold text-traxterSidebar">R$ 132 mil</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                  <div class="text-sm text-slate-500">Propostas ativas</div>
                  <div class="mt-2 text-2xl font-semibold text-traxterSidebar">18</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                  <div class="text-sm text-slate-500">Projetos</div>
                  <div class="mt-2 text-2xl font-semibold text-traxterSidebar">11</div>
                </div>
              </div>
              <div class="mt-6 rounded-2xl border border-slate-200 p-5">
                <div class="flex items-center justify-between">
                  <div class="text-base font-semibold text-traxterSidebar">Indicadores consolidados</div>
                  <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Atualizado</span>
                </div>
                <div class="mt-6 flex items-end gap-4">
                  <div class="mock-chart-bar h-24 w-full"></div>
                  <div class="mock-chart-bar h-32 w-full"></div>
                  <div class="mock-chart-bar h-20 w-full"></div>
                  <div class="mock-chart-bar h-36 w-full"></div>
                  <div class="mock-chart-bar h-28 w-full"></div>
                  <div class="mock-chart-bar h-40 w-full"></div>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <div class="tr-card border border-slate-200 p-6">
                <div class="text-base font-semibold text-traxterSidebar">Radar operacional</div>
                <div class="mt-4 grid gap-3">
                  <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm text-slate-600">Clientes ativos</span>
                    <span class="tr-badge">426</span>
                  </div>
                  <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm text-slate-600">Ordens de serviço</span>
                    <span class="tr-badge">24</span>
                  </div>
                  <div class="flex items-center justify-between rounded-2xl bg-slate-50 px-4 py-3">
                    <span class="text-sm text-slate-600">Contratos vigentes</span>
                    <span class="tr-badge">39</span>
                  </div>
                </div>
              </div>
              <div class="tr-card border border-slate-200 p-6">
                <div class="text-base font-semibold text-traxterSidebar">Visão premium</div>
                <p class="mt-3 text-sm leading-6 text-slate-600">Design limpo, contraste equilibrado, componentes consistentes e foco em confiança para demonstrar maturidade de produto SaaS corporativo.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="planos" class="px-4 py-16 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
          <div class="rounded-[2rem] bg-traxterSidebar px-6 py-12 text-center text-white sm:px-10">
            <span class="tr-badge border-white/15 bg-white/10 text-white">CTA final</span>
            <h2 class="mt-4 text-3xl font-semibold sm:text-4xl">Pronto para transformar sua gestão?</h2>
            <p class="mx-auto mt-4 max-w-2xl text-lg leading-8 text-slate-300">Entre no <?= View::e($companyName) ?> e leve sua operação para um padrão mais profissional, organizado e escalável.</p>
            <div class="mt-8 flex justify-center">
              <button type="button" class="tr-btn tr-icon-btn--accent px-6 py-3 text-base" data-no-iconify="true" data-open-login="1">Entrar no TRAXTER CRM</button>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer id="contato" class="border-t border-slate-200 bg-white px-4 py-12 sm:px-6 lg:px-8">
      <div class="mx-auto grid max-w-7xl gap-10 lg:grid-cols-[1.2fr_.8fr]">
        <div>
          <div class="flex items-center gap-3">
            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-traxterSidebar text-traxterText"><?= UI::icon('building') ?></span>
            <div>
              <div class="text-lg font-semibold text-traxterSidebar"><?= View::e($companyName) ?></div>
              <div class="text-sm text-slate-500"><?= View::e($tagline) ?></div>
            </div>
          </div>
          <p class="mt-4 max-w-xl text-sm leading-7 text-slate-600">Software profissional para centralizar operações comerciais, financeiras e operacionais com a consistência visual e a confiança que um produto SaaS premium exige.</p>
          <?php if ((string) ($branding['company_cnpj'] ?? '') !== ''): ?>
            <div class="mt-3 text-sm text-slate-500">CNPJ: <?= View::e((string) $branding['company_cnpj']) ?></div>
          <?php endif; ?>
        </div>

        <div class="grid gap-8 sm:grid-cols-2">
          <div>
            <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Links rápidos</div>
            <div class="mt-4 flex flex-col gap-3 text-sm">
              <a class="text-slate-600 hover:text-traxterAccent" href="#recursos">Recursos</a>
              <a class="text-slate-600 hover:text-traxterAccent" href="#solucoes">Soluções</a>
              <a class="text-slate-600 hover:text-traxterAccent" href="#beneficios">Benefícios</a>
              <a class="text-slate-600 hover:text-traxterAccent" href="#planos">Planos</a>
            </div>
          </div>
          <div>
            <div class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500">Contato</div>
            <div class="mt-4 flex flex-col gap-3 text-sm text-slate-600">
              <?php if ((string) ($branding['company_email'] ?? '') !== ''): ?><span><?= View::e((string) $branding['company_email']) ?></span><?php endif; ?>
              <?php if ((string) ($branding['company_whatsapp'] ?? '') !== ''): ?><span><?= View::e((string) $branding['company_whatsapp']) ?></span><?php endif; ?>
              <?php if ((string) ($branding['company_website'] ?? '') !== ''): ?><a class="hover:text-traxterAccent" href="<?= View::e((string) $branding['company_website']) ?>" target="_blank" rel="noopener"><?= View::e((string) $branding['company_website']) ?></a><?php endif; ?>
              <div class="mt-2 flex items-center gap-2">
                <span class="tr-icon-btn" aria-label="LinkedIn"><?= UI::icon('users') ?></span>
                <span class="tr-icon-btn" aria-label="Instagram"><?= UI::icon('palette') ?></span>
                <span class="tr-icon-btn" aria-label="Website"><?= UI::icon('building') ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mx-auto mt-10 max-w-7xl border-t border-slate-200 pt-6 text-sm text-slate-500">
        © <?= date('Y') ?> <?= View::e($companyName) ?>. Todos os direitos reservados.
      </div>
    </footer>
  </div>

  <div id="loginModal" class="<?= $openModal ? '' : 'modal-hidden' ?> fixed inset-0 z-50" <?= $openModal ? '' : 'hidden' ?> aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
    <div class="modal-overlay absolute inset-0 bg-slate-950/55"></div>
    <div class="relative flex min-h-screen items-center justify-center p-4 sm:p-6">
      <div class="modal-panel tr-card relative w-full max-w-lg overflow-hidden border border-slate-200 shadow-2xl shadow-slate-950/15">
        <button id="closeLoginModal" type="button" class="tr-icon-btn absolute right-4 top-4 z-10" aria-label="Fechar modal">
          <?= UI::icon('x') ?>
        </button>
        <div class="grid md:grid-cols-[.92fr_1.08fr]">
          <div class="hidden bg-traxterSidebar p-6 text-white md:block">
            <div class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold">TRAXTER CRM</div>
            <h2 class="mt-6 text-2xl font-semibold">Acesse sua conta</h2>
            <p class="mt-3 text-sm leading-6 text-slate-300">Entre para acompanhar clientes, propostas, contratos, projetos, financeiro e ordens de serviço com total integração.</p>
            <div class="mt-8 space-y-4 text-sm text-slate-200">
              <div class="flex items-start gap-3"><?= UI::icon('check', 'h-4 w-4 mt-1') ?><span>Experiência segura e profissional</span></div>
              <div class="flex items-start gap-3"><?= UI::icon('check', 'h-4 w-4 mt-1') ?><span>Dados centralizados em um único ambiente</span></div>
              <div class="flex items-start gap-3"><?= UI::icon('check', 'h-4 w-4 mt-1') ?><span>Visual alinhado ao restante do CRM</span></div>
            </div>
          </div>

          <div class="p-6 sm:p-8">
            <div class="text-2xl font-semibold text-traxterSidebar">TRAXTER CRM</div>
            <div class="mt-1 text-slate-600">Acesse sua conta</div>

            <?php if ($errorMessage !== ''): ?>
              <div class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= View::e($errorMessage) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= View::e($base . '/login') ?>" class="mt-6 space-y-4">
              <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
              <div>
                <label class="tr-label" for="loginEmail">E-mail</label>
                <input id="loginEmail" name="email" type="email" class="mt-1 tr-input" autocomplete="email" required>
              </div>
              <div>
                <label class="tr-label" for="loginPassword">Senha</label>
                <input id="loginPassword" name="password" type="password" class="mt-1 tr-input" autocomplete="current-password" required>
              </div>

              <div class="flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <label class="inline-flex items-center gap-2 text-slate-600">
                  <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-traxterAccent focus:ring-traxterAccent">
                  <span>Lembrar-me</span>
                </label>
                <a href="#contato" class="font-medium text-traxterAccent hover:underline">Esqueci minha senha</a>
              </div>

              <div class="flex flex-col gap-3 pt-2">
                <button class="tr-btn tr-icon-btn--accent w-full justify-center px-5 py-3 text-base" data-no-iconify="true" type="submit">Entrar</button>
                <button class="tr-btn w-full justify-center px-5 py-3 text-base" data-no-iconify="true" type="button">Solicitar Demonstração</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const modal = document.getElementById('loginModal');
      const closeButton = document.getElementById('closeLoginModal');
      const emailField = document.getElementById('loginEmail');
      const mobileMenuButton = document.getElementById('mobileMenuButton');
      const mobileMenu = document.getElementById('mobileMenu');
      const siteHeader = document.getElementById('siteHeader');
      const headerSpacer = document.getElementById('headerSpacer');
      const openButtons = document.querySelectorAll('[data-open-login="1"]');
      let lastTrigger = null;

      function syncHeaderOffset() {
        if (!siteHeader || !headerSpacer) return;
        headerSpacer.style.height = siteHeader.offsetHeight + 'px';
      }

      function closeMobileMenu() {
        if (!mobileMenu || !mobileMenuButton) return;
        mobileMenu.hidden = true;
        mobileMenu.classList.remove('is-open');
        mobileMenu.setAttribute('aria-hidden', 'true');
        mobileMenuButton.setAttribute('aria-expanded', 'false');
        syncHeaderOffset();
      }

      function openMobileMenu() {
        if (!mobileMenu || !mobileMenuButton) return;
        mobileMenu.hidden = false;
        mobileMenu.classList.add('is-open');
        mobileMenu.setAttribute('aria-hidden', 'false');
        mobileMenuButton.setAttribute('aria-expanded', 'true');
        syncHeaderOffset();
      }

      if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
          const opened = !mobileMenu.hidden;
          if (opened) {
            closeMobileMenu();
          } else {
            openMobileMenu();
          }
        });

        mobileMenu.querySelectorAll('a, button').forEach((item) => {
          item.addEventListener('click', () => {
            closeMobileMenu();
          });
        });
      }

      function openModal(trigger) {
        if (!modal) return;
        lastTrigger = trigger instanceof HTMLElement ? trigger : document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.remove('modal-hidden');
        window.requestAnimationFrame(() => {
          if (emailField) emailField.focus();
        });
        document.body.classList.add('overflow-hidden');
      }

      function closeModal() {
        if (!modal) return;
        modal.classList.add('modal-hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        window.setTimeout(() => {
          if (modal.classList.contains('modal-hidden')) {
            modal.hidden = true;
          }
        }, 240);
        if (lastTrigger instanceof HTMLElement) {
          lastTrigger.focus();
        }
      }

      openButtons.forEach((button) => {
        button.addEventListener('click', () => openModal(button));
      });

      if (closeButton) {
        closeButton.addEventListener('click', closeModal);
      }

      if (modal) {
        modal.addEventListener('click', (event) => {
          if (event.target === modal || event.target.classList.contains('modal-overlay')) {
            closeModal();
          }
        });
      }

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.classList.contains('modal-hidden')) {
          closeModal();
        }
      });

      window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
          closeMobileMenu();
        } else {
          syncHeaderOffset();
        }
      });

      if (!<?= $openModal ? 'true' : 'false' ?> && modal) {
        closeModal();
      } else if (<?= $openModal ? 'true' : 'false' ?>) {
        openModal();
      }

      closeMobileMenu();
      syncHeaderOffset();
    })();
  </script>
</body>
</html>
