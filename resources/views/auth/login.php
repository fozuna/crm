<?php
use App\Core\Config;
use App\Core\UI;
use App\Core\View;
use App\Services\CompanyProfileService;

$branding = [
    'company_name' => 'TRAXTER CRM',
    'brand_tagline' => 'Sistema de gestão comercial, operacional e financeira.',
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
    $tagline = 'Sistema de gestão comercial, operacional e financeira.';
}

$canonicalUrl = rtrim((string) Config::get('APP_URL', ''), '/');
if ($canonicalUrl === '') {
    $canonicalUrl = $base . '/login';
} else {
    $canonicalUrl .= '/login';
}

$pageTitle = $companyName . ' | Acesso ao sistema';
$pageDescription = 'TRAXTER CRM centraliza clientes, propostas, contratos, projetos, financeiro e ordens de serviço em um único sistema, com controle de acesso por papel e auditoria.';
$pageKeywords = 'CRM, gestão comercial, financeiro, propostas, projetos, contratos, ordem de serviço';
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

// Módulos reais do sistema (config/routes.php + resources/views/layout.php) — nenhum item
// fictício ou fora do que o produto de fato oferece.
$modules = [
    ['icon' => 'users', 'title' => 'Clientes', 'description' => 'Cadastro, relacionamento e histórico de interações centralizados.'],
    ['icon' => 'briefcase', 'title' => 'Propostas', 'description' => 'Criação, aprovação e acompanhamento até a conversão em contrato.'],
    ['icon' => 'pdf', 'title' => 'Contratos', 'description' => 'Geração a partir da proposta, com versões e notificações.'],
    ['icon' => 'folder', 'title' => 'Projetos', 'description' => 'Tarefas, marcos, eventos e histórico de status de execução.'],
    ['icon' => 'wallet', 'title' => 'Financeiro', 'description' => 'Recebíveis, pagamentos, fluxo de caixa e indicadores.'],
    ['icon' => 'list', 'title' => 'Ordens de Serviço', 'description' => 'Abertura, anexos, histórico técnico e aprovação externa por link.'],
    ['icon' => 'shield', 'title' => 'Auditoria', 'description' => 'Rastreabilidade de ações críticas por usuário e por módulo.'],
    ['icon' => 'chart', 'title' => 'Relatórios', 'description' => 'Leitura consolidada dos indicadores operacionais e financeiros.'],
];

// Framing do problema com base no que o CLAUDE.md documenta como motivação real do produto
// ("reduz dispersão operacional entre planilhas, documentos avulsos e controles paralelos").
$problemPoints = [
    [
        'title' => 'Dados espalhados entre planilhas e documentos avulsos',
        'description' => 'Cliente, proposta, contrato e ordem de serviço — cada um num arquivo diferente, sem um histórico único para consultar.',
    ],
    [
        'title' => 'Controles paralelos sem rastreabilidade',
        'description' => 'Decisões, aprovações e alterações se perdem fora do sistema, sem registro de quem fez o quê e quando.',
    ],
    [
        'title' => 'Financeiro fora do fluxo operacional',
        'description' => 'Recebíveis e pagamentos controlados à parte dificultam enxergar o caixa real da operação.',
    ],
];

// Ciclo real de uma ordem de serviço (database/schema.sql: aberto → em_andamento → concluido → faturado)
// e o que cada etapa efetivamente faz no código (ServiceOrderService, ServiceOrderApprovalController).
$lifecycleStages = [
    [
        'num' => '01 · ABERTO',
        'title' => 'Registre a demanda',
        'description' => 'A ordem de serviço nasce vinculada ao cliente e, quando existir, ao contrato correspondente.',
    ],
    [
        'num' => '02 · EM ANDAMENTO',
        'title' => 'Execute e acompanhe',
        'description' => 'Status, anexos e histórico técnico atualizados conforme o atendimento avança.',
    ],
    [
        'num' => '03 · CONCLUÍDO',
        'title' => 'Feche com o cliente',
        'description' => 'A aprovação pode acontecer externamente, por link assinado — sem exigir login do cliente.',
    ],
    [
        'num' => '04 · FATURADO',
        'title' => 'Siga para o financeiro',
        'description' => 'O lançamento em contas a receber é sincronizado automaticamente com a ordem de serviço.',
    ],
];

// Afirmações técnicas verificáveis no código (RBAC por middleware, auditoria em tabelas dedicadas,
// aprovação externa por token), não adjetivos de marketing.
$operationPoints = [
    [
        'title' => 'Um papel, um nível de acesso.',
        'description' => 'Permissões por papel (admin, PM, financeiro, auditor) aplicadas por middleware — não por checagem isolada em cada tela.',
    ],
    [
        'title' => 'Nada se perde no caminho.',
        'description' => 'Operações críticas registram histórico e auditoria em tabelas dedicadas, com rastreabilidade ponta a ponta.',
    ],
    [
        'title' => 'Aprovação sem fricção.',
        'description' => 'Ordens de serviço podem ser aprovadas externamente por link assinado, sem exigir login do cliente.',
    ],
];

// Respostas restritas ao que é verificável em código/documentação — sem promessas comerciais
// (planos, cancelamento, importação em lote) que não existem no produto atual.
$faqItems = [
    [
        'q' => 'Preciso instalar alguma coisa?',
        'a' => 'Não. O sistema roda inteiramente no navegador, renderizado no servidor — sem aplicativo para baixar ou instalar na máquina do usuário.',
    ],
    [
        'q' => 'Como funciona o acesso de cada pessoa da equipe?',
        'a' => 'O administrador cria os usuários e define o papel de cada um (admin, PM, financeiro ou auditor). O que cada pessoa vê e pode fazer é controlado por esse papel, não configurado tela a tela.',
    ],
    [
        'q' => 'Como o sistema protege os dados?',
        'a' => 'Senhas são armazenadas com hash, toda mutação exige verificação CSRF e ações críticas ficam registradas em auditoria — com consultas ao banco sempre por prepared statements.',
    ],
    [
        'q' => 'O cliente final precisa de login para aprovar uma ordem de serviço?',
        'a' => 'Não. A aprovação externa acontece por um link assinado, específico daquela ordem de serviço, sem exigir conta no sistema.',
    ],
];

$navItems = [
    ['href' => '#solucao', 'label' => 'Solução'],
    ['href' => '#modulos', 'label' => 'Módulos'],
    ['href' => '#como-funciona', 'label' => 'Como funciona'],
    ['href' => '#faq', 'label' => 'Dúvidas'],
];

$errorMessage = trim((string) ($error ?? ''));
$openModal = $errorMessage !== '';
?>
<!doctype html>
<html lang="pt-br">
<head>
<?php require __DIR__ . '/../partials/head.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800;900&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --tr-ease: cubic-bezier(.16,1,.3,1);
    --tr-font-display: 'Archivo', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    --tr-font-mono: 'IBM Plex Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
    --tr-graphite: color-mix(in srgb, var(--tr-primary) 92%, black);
    --tr-graphite-2: color-mix(in srgb, var(--tr-primary) 82%, white 6%);
    --tr-graphite-3: color-mix(in srgb, var(--tr-primary) 74%, white 10%);
    --tr-line: color-mix(in srgb, var(--tr-primary) 55%, white 24%);
    --tr-line-light: #DAD5C8;
    --tr-paper: #F6F4EF;
    --tr-text-dim: #9CA5B4;
    --tr-text-dim-2: #64748B;
  }
  html{scroll-behavior:smooth}
  body{
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", var(--tr-font), Roboto, "Helvetica Neue", Arial, sans-serif;
    background:var(--tr-graphite);
    color:#E9EBEF;
  }
  .tr-display{font-family:var(--tr-font-display); letter-spacing:-0.02em;}
  .tr-mono{font-family:var(--tr-font-mono);}

  .skip-link{
    position:absolute; left:1rem; top:-3rem; z-index:70;
    background:#0f172a; color:#fff; padding:.6rem 1rem; border-radius:.5rem;
    font-size:.875rem; font-weight:600; transition:top .18s var(--tr-ease);
  }
  .skip-link:focus{top:1rem}

  .section-pad{padding:5.5rem 1.5rem}
  @media(max-width:768px){.section-pad{padding:3.5rem 1.25rem}}
  .wrap{max-width:72rem; margin:0 auto}
  .paper{background:var(--tr-paper); color:#14151A}

  .eyebrow{
    font-family:var(--tr-font-mono); font-size:.75rem; letter-spacing:.14em; text-transform:uppercase;
    color:var(--tr-accent); display:inline-flex; align-items:center; gap:.5rem;
  }
  .eyebrow::before{content:''; width:1rem; height:1px; background:var(--tr-accent)}
  .eyebrow--dark{color:color-mix(in srgb, var(--tr-accent) 85%, black 15%)}

  .btn-tr-primary{
    background:var(--tr-accent); color:#14151A; font-weight:700;
    padding:.85rem 1.75rem; border-radius:.5rem; display:inline-flex; align-items:center; gap:.5rem;
    transition:transform .15s var(--tr-ease), box-shadow .15s var(--tr-ease), filter .15s var(--tr-ease);
  }
  .btn-tr-primary:hover{filter:brightness(1.06); transform:translateY(-1px); box-shadow:0 8px 24px -8px color-mix(in srgb, var(--tr-accent) 60%, transparent)}
  .btn-tr-ghost{
    border:1px solid var(--tr-line); color:#E9EBEF; padding:.8rem 1.6rem; border-radius:.5rem; font-weight:600;
    transition:border-color .15s var(--tr-ease), background .15s var(--tr-ease);
  }
  .btn-tr-ghost:hover{border-color:color-mix(in srgb, var(--tr-line) 60%, white 20%); background:rgba(255,255,255,.03)}
  .btn-tr-dark{
    background:#14151A; color:var(--tr-paper); font-weight:700;
    padding:.85rem 1.75rem; border-radius:.5rem; display:inline-flex; align-items:center; gap:.5rem;
    transition:opacity .15s var(--tr-ease), transform .15s var(--tr-ease);
  }
  .btn-tr-dark:hover{opacity:.85; transform:translateY(-1px)}

  .glow-hero{
    background: radial-gradient(60% 50% at 78% 15%, color-mix(in srgb, var(--tr-accent) 16%, transparent), transparent 70%);
  }

  .reveal{opacity:0; transform:translateY(16px); transition:opacity .6s var(--tr-ease), transform .6s var(--tr-ease)}
  .reveal.is-visible{opacity:1; transform:translateY(0)}
  .reveal-delay-1.is-visible{transition-delay:.06s}
  .reveal-delay-2.is-visible{transition-delay:.12s}
  .reveal-delay-3.is-visible{transition-delay:.18s}

  @keyframes heroIn{from{opacity:0; transform:translateY(16px)} to{opacity:1; transform:translateY(0)}}
  .hero-in{animation:heroIn .7s var(--tr-ease) both}
  .hero-in-1{animation-delay:.05s}
  .hero-in-2{animation-delay:.14s}
  .hero-in-3{animation-delay:.22s}
  .hero-in-4{animation-delay:.3s}

  @media (prefers-reduced-motion: reduce){
    html{scroll-behavior:auto}
    .reveal, .hero-in{opacity:1 !important; transform:none !important; animation:none !important; transition:none !important}
  }

  /* Cartão de OS: ilustra o ciclo real (aberto → em_andamento → concluido → faturado),
     não uma métrica ou cliente real — sinalizado abaixo do cartão. */
  .os-card{
    background:var(--tr-graphite-2); border:1px solid var(--tr-line); border-radius:.9rem;
    overflow:hidden; box-shadow:0 40px 80px -30px rgba(0,0,0,.6);
  }
  .os-head{
    padding:1.15rem 1.35rem; border-bottom:1px solid var(--tr-line);
    display:flex; align-items:center; justify-content:space-between; gap:.75rem;
  }
  .os-status-track{display:flex; align-items:center; padding:1.35rem; gap:0}
  .os-step{display:flex; flex-direction:column; align-items:flex-start; flex:1; position:relative}
  .os-dot{width:.7rem; height:.7rem; border-radius:9999px; background:var(--tr-line); border:2px solid var(--tr-line); margin-bottom:.6rem; z-index:2}
  .os-step.done .os-dot{background:var(--tr-accent); border-color:var(--tr-accent)}
  .os-step.active .os-dot{background:var(--tr-graphite-2); border-color:var(--tr-accent); box-shadow:0 0 0 4px color-mix(in srgb, var(--tr-accent) 22%, transparent)}
  .os-connector{position:absolute; top:.3rem; left:1rem; right:-50%; height:2px; background:var(--tr-line); z-index:1}
  .os-step.done .os-connector{background:var(--tr-accent)}
  .os-label{font-size:.72rem; color:var(--tr-text-dim); font-weight:600}
  .os-step.done .os-label, .os-step.active .os-label{color:#E9EBEF}
  .os-body{padding:0 1.35rem 1.35rem}
  .os-row{display:flex; justify-content:space-between; gap:1rem; padding:.7rem 0; border-bottom:1px solid var(--tr-line); font-size:.85rem}
  .os-row:last-child{border-bottom:none}
  .os-row span:first-child{color:var(--tr-text-dim)}
  .tag-mono{font-family:var(--tr-font-mono); font-size:.72rem; color:var(--tr-text-dim)}

  .feature-card{
    background:var(--tr-graphite-2); border:1px solid var(--tr-line); border-radius:.75rem; padding:1.5rem;
    transition:border-color .2s var(--tr-ease), transform .2s var(--tr-ease);
  }
  .feature-card:hover{border-color:color-mix(in srgb, var(--tr-accent) 55%, var(--tr-line)); transform:translateY(-3px)}
  .feature-num{font-family:var(--tr-font-mono); font-size:.72rem; color:var(--tr-accent); margin-bottom:.9rem; display:block}

  .stage{border-top:2px solid var(--tr-line-light); padding-top:1.1rem}
  .stage-num{font-family:var(--tr-font-mono); font-size:.78rem; color:color-mix(in srgb, var(--tr-accent) 80%, black 15%); font-weight:600}

  .faq-item{border-bottom:1px solid var(--tr-line-light)}
  .faq-q{
    width:100%; text-align:left; padding:1.3rem 0; display:flex; justify-content:space-between; align-items:center; gap:1rem;
    font-weight:600; font-size:.95rem; color:#14151A; cursor:pointer; background:none; border:none; font-family:'Inter',sans-serif;
  }
  .faq-icon{transition:transform .25s var(--tr-ease); flex-shrink:0}
  .faq-item.open .faq-icon{transform:rotate(45deg)}
  .faq-a{max-height:0; overflow:hidden; transition:max-height .3s var(--tr-ease)}
  .faq-item.open .faq-a{max-height:220px}
  .faq-a-inner{padding-bottom:1.3rem; color:var(--tr-text-dim-2); font-size:.88rem; line-height:1.65; max-width:40rem}

  .mockup-caption{font-family:var(--tr-font-mono); font-size:.7rem; color:var(--tr-text-dim); text-align:center; margin-top:.75rem}

  ::selection{background:var(--tr-accent); color:#14151A}

  .site-header{z-index:40; background:color-mix(in srgb, var(--tr-graphite) 92%, transparent); backdrop-filter:blur(8px)}
  .site-header__menu{display:none}
  .site-header__menu.is-open{display:block}
  .site-header__logo{max-height:2rem; width:auto; object-fit:contain}
  @media (min-width: 768px){ .site-header__menu{display:none !important} }
  .brand-dot{width:.5rem; height:.5rem; border-radius:9999px; background:var(--tr-accent)}

  .modal-overlay{transition:opacity .22s var(--tr-ease)}
  .modal-panel{transition:transform .22s var(--tr-ease), opacity .22s var(--tr-ease)}
  .modal-hidden{pointer-events:none}
  .modal-hidden .modal-overlay{opacity:0}
  .modal-hidden .modal-panel{opacity:0; transform:translateY(12px) scale(.98)}

  .pw-toggle{display:inline-flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:.5rem; color:#64748b}
  .pw-toggle:hover{background:#f1f5f9; color:#0f172a}
  .pw-toggle:focus-visible{outline:none; box-shadow:0 0 0 3px color-mix(in srgb, var(--tr-accent) 25%, transparent)}

  a:focus-visible, button:focus-visible{outline:2px solid var(--tr-accent); outline-offset:2px}
</style>
</head>
<body class="antialiased">
  <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>

  <header id="siteHeader" class="site-header fixed top-0 inset-x-0 border-b" style="border-color:var(--tr-line)">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
      <a href="#topo" class="flex min-w-0 items-center gap-2.5" aria-label="<?= View::e($companyName) ?>">
        <?php if ($logoDataUri !== ''): ?>
          <img src="<?= View::e($logoDataUri) ?>" alt="<?= View::e($companyName) ?>" class="site-header__logo">
        <?php else: ?>
          <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md" style="background:var(--tr-accent)">
            <span class="tr-mono" style="font-weight:700; color:#14151A; font-size:.9rem"><?= View::e(mb_substr($companyName, 0, 1)) ?></span>
          </span>
          <span class="tr-display text-[1.05rem] font-extrabold tracking-tight text-white"><?= View::e($companyName) ?></span>
        <?php endif; ?>
      </a>

      <button id="mobileMenuButton" type="button" class="tr-icon-btn md:hidden" style="border-color:var(--tr-line); background:transparent; color:#E9EBEF" aria-expanded="false" aria-controls="mobileMenu" aria-label="Abrir menu">
        <?= UI::icon('list') ?>
      </button>

      <nav class="hidden items-center gap-1 md:flex" aria-label="Navegação principal">
        <?php foreach ($navItems as $item): ?>
          <a class="rounded-md px-3 py-2 text-[0.83rem] font-medium transition" style="color:var(--tr-text-dim)" href="<?= View::e($item['href']) ?>"><?= View::e($item['label']) ?></a>
        <?php endforeach; ?>
        <button type="button" class="btn-tr-primary ml-2 !py-2 !px-4 text-[0.83rem]" data-no-iconify="true" data-open-login="1">Entrar</button>
      </nav>
    </div>

    <div id="mobileMenu" class="site-header__menu border-t md:hidden" style="border-color:var(--tr-line); background:var(--tr-graphite)" hidden aria-hidden="true">
      <div class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3 sm:px-6">
        <?php foreach ($navItems as $item): ?>
          <a class="rounded-md px-3 py-2.5 text-sm font-medium text-white/80 hover:bg-white/5" href="<?= View::e($item['href']) ?>"><?= View::e($item['label']) ?></a>
        <?php endforeach; ?>
        <button type="button" class="btn-tr-primary mt-1 w-full justify-center" data-no-iconify="true" data-open-login="1">Entrar</button>
      </div>
    </div>
  </header>

  <div id="headerSpacer" aria-hidden="true"></div>

  <main id="conteudo">
    <section id="topo" class="glow-hero px-4 pb-16 pt-14 sm:px-6 lg:px-8 lg:pb-24 lg:pt-20">
      <div class="mx-auto grid max-w-6xl items-center gap-14 lg:grid-cols-[1.05fr_.95fr]">
        <div>
          <div class="hero-in hero-in-1 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[0.75rem] font-medium" style="border-color:var(--tr-line); color:var(--tr-text-dim)">
            <span class="brand-dot"></span><?= View::e($companyName) ?>
          </div>
          <h1 class="hero-in hero-in-2 tr-display mt-5 max-w-xl text-[2.4rem] font-extrabold leading-[1.08] tracking-tight text-white sm:text-[2.75rem]">
            Cada cliente, proposta, contrato e ordem de serviço —<br>
            <span style="color:var(--tr-accent)">num só sistema.</span>
          </h1>
          <p class="hero-in hero-in-3 mt-5 max-w-lg text-base leading-7 sm:text-lg" style="color:var(--tr-text-dim)">
            Chega de dispersão entre planilhas e documentos avulsos. O <?= View::e($companyName) ?> centraliza clientes, propostas, contratos, projetos, financeiro e ordens de serviço em um único fluxo autenticado, com auditoria.
          </p>

          <div class="hero-in hero-in-4 mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
            <button type="button" class="btn-tr-primary" data-no-iconify="true" data-open-login="1">Entrar no sistema
              <?= UI::icon('arrow-right', 'w-4 h-4') ?>
            </button>
            <a href="#modulos" class="btn-tr-ghost">Ver módulos</a>
          </div>
          <p class="hero-in hero-in-4 mt-4 tr-mono text-[0.72rem]" style="color:var(--tr-text-dim)">
            Acesso mediante credenciais fornecidas pelo administrador do sistema.
          </p>
        </div>

        <div class="hero-in hero-in-4">
          <div class="os-card reveal">
            <div class="os-head">
              <div>
                <div class="tag-mono">OS #1042</div>
                <div style="font-weight:600; font-size:.95rem; margin-top:.25rem; color:#fff">Manutenção preventiva</div>
              </div>
              <div class="tr-mono text-[0.7rem] font-bold rounded-md px-2.5 py-1" style="background:color-mix(in srgb, var(--tr-accent) 14%, transparent); color:var(--tr-accent)">
                EM ANDAMENTO
              </div>
            </div>
            <div class="os-status-track">
              <div class="os-step done"><div class="os-connector"></div><div class="os-dot"></div><span class="os-label">Aberto</span></div>
              <div class="os-step active"><div class="os-connector"></div><div class="os-dot"></div><span class="os-label">Em andamento</span></div>
              <div class="os-step"><div class="os-connector"></div><div class="os-dot"></div><span class="os-label">Concluído</span></div>
              <div class="os-step"><div class="os-dot"></div><span class="os-label">Faturado</span></div>
            </div>
            <div class="os-body">
              <div class="os-row"><span>Cliente</span><span>Cliente cadastrado</span></div>
              <div class="os-row"><span>Responsável</span><span>Técnico designado</span></div>
              <div class="os-row"><span>Contrato vinculado</span><span class="tag-mono">CT-0001</span></div>
              <div class="os-row"><span>Valor previsto</span><span style="color:var(--tr-accent); font-weight:700">R$ 480,00</span></div>
            </div>
          </div>
          <p class="mockup-caption">Ilustração da interface do sistema — dados fictícios.</p>
        </div>
      </div>
    </section>

    <div class="wrap section-pad" style="padding-top:0; padding-bottom:2.5rem">
      <p class="tr-mono text-center text-[0.72rem] tracking-wider" style="color:var(--tr-text-dim)">
        PARA QUEM PRECISA CENTRALIZAR CLIENTES, PROPOSTAS, CONTRATOS, PROJETOS, FINANCEIRO E ORDENS DE SERVIÇO EM UM SÓ LUGAR
      </p>
    </div>

    <section id="solucao" class="section-pad paper">
      <div class="wrap">
        <div class="reveal max-w-xl">
          <span class="eyebrow eyebrow--dark">O problema</span>
          <h2 class="tr-display mt-4 text-[1.9rem] font-extrabold tracking-tight sm:text-[2.1rem]" style="color:#14151A">
            Sua operação não cabe mais em planilhas e conversas paralelas.
          </h2>
        </div>
        <div class="mt-12 grid gap-6 sm:grid-cols-3">
          <?php foreach ($problemPoints as $i => $point): ?>
            <div class="reveal reveal-delay-<?= View::e((string) ($i + 1)) ?>">
              <div class="tr-mono text-[0.8rem] font-bold" style="color:color-mix(in srgb, var(--tr-accent) 80%, black 15%)"><?= View::e(sprintf('%02d', $i + 1)) ?></div>
              <h3 class="mt-2.5 text-[1.02rem] font-bold" style="color:#14151A"><?= View::e((string) $point['title']) ?></h3>
              <p class="mt-2 text-[0.88rem] leading-6" style="color:var(--tr-text-dim-2)"><?= View::e((string) $point['description']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="modulos" class="section-pad">
      <div class="wrap">
        <span class="eyebrow">O que o sistema organiza</span>
        <h2 class="tr-display mt-4 max-w-xl text-[1.9rem] font-extrabold tracking-tight text-white sm:text-[2.1rem]">
          Oito módulos. Um sistema. Zero retrabalho.
        </h2>

        <div class="mt-11 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          <?php foreach ($modules as $card): ?>
            <div class="feature-card">
              <span class="feature-num">/ <?= View::e(mb_strtoupper((string) $card['title'])) ?></span>
              <div class="inline-flex h-9 w-9 items-center justify-center rounded-lg mb-3" style="background:color-mix(in srgb, var(--tr-accent) 12%, transparent); color:var(--tr-accent)">
                <?= UI::icon((string) $card['icon'], 'w-4.5 h-4.5') ?>
              </div>
              <h3 class="font-bold text-[1rem] text-white"><?= View::e((string) $card['title']) ?></h3>
              <p class="mt-2 text-[0.85rem] leading-6" style="color:var(--tr-text-dim)"><?= View::e((string) $card['description']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="como-funciona" class="section-pad paper">
      <div class="wrap">
        <span class="eyebrow eyebrow--dark">Como funciona</span>
        <h2 class="tr-display mt-4 max-w-xl text-[1.9rem] font-extrabold tracking-tight sm:text-[2.1rem]" style="color:#14151A">
          O caminho real de uma ordem de serviço no sistema.
        </h2>

        <div class="mt-12 grid gap-7 sm:grid-cols-2 lg:grid-cols-4">
          <?php foreach ($lifecycleStages as $stage): ?>
            <div class="stage">
              <div class="stage-num"><?= View::e((string) $stage['num']) ?></div>
              <h3 class="mt-2.5 text-[0.98rem] font-bold" style="color:#14151A"><?= View::e((string) $stage['title']) ?></h3>
              <p class="mt-2 text-[0.85rem] leading-6" style="color:var(--tr-text-dim-2)"><?= View::e((string) $stage['description']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section-pad" style="background:var(--tr-graphite-3)">
      <div class="wrap">
        <div class="reveal max-w-xl">
          <span class="eyebrow">Como o sistema é construído</span>
          <h2 class="tr-display mt-4 text-[1.9rem] font-extrabold tracking-tight text-white sm:text-[2.1rem]">
            Decisões concretas de arquitetura, não promessas genéricas.
          </h2>
        </div>

        <div class="mt-11 grid gap-5 sm:grid-cols-3">
          <?php foreach ($operationPoints as $i => $point): ?>
            <div class="reveal reveal-delay-<?= View::e((string) ($i + 1)) ?> rounded-xl border p-5" style="border-color:var(--tr-line); background:rgba(255,255,255,.03)">
              <h3 class="text-[0.98rem] font-bold text-white"><?= View::e((string) $point['title']) ?></h3>
              <p class="mt-2 text-[0.85rem] leading-6" style="color:var(--tr-text-dim)"><?= View::e((string) $point['description']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="faq" class="section-pad paper">
      <div class="wrap" style="max-width:48rem">
        <span class="eyebrow eyebrow--dark">Dúvidas frequentes</span>
        <h2 class="tr-display mt-4 text-[1.7rem] font-extrabold tracking-tight" style="color:#14151A">Antes de entrar</h2>

        <div class="mt-9 rounded-2xl px-6" style="background:var(--tr-paper)">
          <?php foreach ($faqItems as $i => $item): ?>
            <div class="faq-item<?= $i === 0 ? ' open' : '' ?>">
              <button type="button" class="faq-q">
                <?= View::e((string) $item['q']) ?>
                <svg class="faq-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#14151A" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
              </button>
              <div class="faq-a"><div class="faq-a-inner"><?= View::e((string) $item['a']) ?></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section id="cta-final" class="section-pad">
      <div class="wrap rounded-2xl border px-8 py-16 text-center sm:px-10" style="border-color:var(--tr-line); background:var(--tr-graphite-2)">
        <h2 class="tr-display mx-auto max-w-xl text-[1.9rem] font-extrabold tracking-tight text-white sm:text-[2.1rem]">
          Pronto para organizar sua operação?
        </h2>
        <p class="mx-auto mt-3 max-w-md text-[0.95rem] leading-7" style="color:var(--tr-text-dim)">
          Use as credenciais fornecidas pelo administrador do sistema.
        </p>
        <div class="mt-8 flex justify-center">
          <button type="button" class="btn-tr-primary" style="padding:.95rem 2.1rem" data-no-iconify="true" data-open-login="1">Entrar no sistema</button>
        </div>
      </div>
    </section>
  </main>

  <footer id="contato" class="border-t px-4 py-12 sm:px-6 lg:px-8" style="border-color:var(--tr-line)">
    <div class="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1.2fr_.8fr]">
      <div>
        <div class="flex items-center gap-2.5">
          <?php if ($logoDataUri !== ''): ?>
            <img src="<?= View::e($logoDataUri) ?>" alt="<?= View::e($companyName) ?>" class="h-7 w-auto object-contain">
          <?php else: ?>
            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg" style="background:var(--tr-accent)"><?= UI::icon('building', 'w-4 h-4') ?></span>
          <?php endif; ?>
          <div class="tr-display text-[0.98rem] font-extrabold text-white"><?= View::e($companyName) ?></div>
        </div>
        <p class="mt-3 max-w-md text-[0.85rem] leading-6" style="color:var(--tr-text-dim)"><?= View::e($tagline) ?></p>
        <?php if ((string) ($branding['company_cnpj'] ?? '') !== ''): ?>
          <div class="mt-2 text-[0.8rem]" style="color:var(--tr-text-dim)">CNPJ: <?= View::e((string) $branding['company_cnpj']) ?></div>
        <?php endif; ?>
        <p class="mt-4 text-[0.8rem] leading-6" style="color:var(--tr-text-dim)">Sem acesso ainda? Fale com o administrador do sistema<?= (string) ($branding['company_email'] ?? '') !== '' ? ' em ' . View::e((string) $branding['company_email']) : '' ?>.</p>
      </div>

      <div>
        <div class="tr-mono text-[0.7rem] font-semibold uppercase tracking-wider" style="color:var(--tr-text-dim)">Contato</div>
        <div class="mt-3 flex flex-col gap-2 text-[0.85rem]" style="color:var(--tr-text-dim)">
          <?php if ((string) ($branding['company_email'] ?? '') !== ''): ?><span><?= View::e((string) $branding['company_email']) ?></span><?php endif; ?>
          <?php if ((string) ($branding['company_whatsapp'] ?? '') !== ''): ?><span><?= View::e((string) $branding['company_whatsapp']) ?></span><?php endif; ?>
          <?php if ((string) ($branding['company_website'] ?? '') !== ''): ?><a class="hover:text-white" style="color:var(--tr-accent)" href="<?= View::e((string) $branding['company_website']) ?>" target="_blank" rel="noopener"><?= View::e((string) $branding['company_website']) ?></a><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="mx-auto mt-9 max-w-6xl border-t pt-6 text-[0.8rem]" style="border-color:var(--tr-line); color:var(--tr-text-dim)">
      © <?= date('Y') ?> <?= View::e($companyName) ?>
    </div>
  </footer>

  <div id="loginModal" class="<?= $openModal ? '' : 'modal-hidden' ?> fixed inset-0 z-50" <?= $openModal ? '' : 'hidden' ?> role="dialog" aria-modal="true" aria-labelledby="loginTitle" aria-hidden="<?= $openModal ? 'false' : 'true' ?>">
    <div class="modal-overlay absolute inset-0 bg-slate-950/60"></div>
    <div class="relative flex min-h-screen items-center justify-center p-4 sm:p-6">
      <div class="modal-panel relative w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl shadow-slate-950/25 sm:p-8">
        <button id="closeLoginModal" type="button" class="tr-icon-btn absolute right-4 top-4" aria-label="Fechar">
          <?= UI::icon('x') ?>
        </button>

        <div id="loginTitle" class="tr-display text-lg font-extrabold tracking-tight text-slate-900">Entrar</div>
        <div class="mt-1 text-[0.85rem] text-slate-500"><?= View::e($companyName) ?></div>

        <?php if ($errorMessage !== ''): ?>
          <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-[0.83rem] text-red-700" role="alert"><?= View::e($errorMessage) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= View::e($base . '/login') ?>" class="mt-6 space-y-4">
          <input type="hidden" name="_csrf" value="<?= View::e($csrf) ?>">
          <div>
            <label class="tr-label" for="loginEmail">E-mail</label>
            <input id="loginEmail" name="email" type="email" class="mt-1 tr-input" autocomplete="email" required>
          </div>
          <div>
            <label class="tr-label" for="loginPassword">Senha</label>
            <div class="relative mt-1">
              <input id="loginPassword" name="password" type="password" class="tr-input pr-11" autocomplete="current-password" required>
              <button type="button" id="togglePassword" class="pw-toggle absolute right-1 top-1/2 -translate-y-1/2" aria-label="Mostrar senha" aria-pressed="false">
                <?= UI::icon('eye', 'w-4 h-4') ?>
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between text-[0.83rem]">
            <label class="inline-flex items-center gap-2 text-slate-600">
              <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-traxterAccent focus:ring-traxterAccent">
              <span>Lembrar-me</span>
            </label>
            <a href="#contato" class="font-medium text-traxterAccent hover:underline">Esqueci minha senha</a>
          </div>

          <button class="w-full rounded-lg bg-traxterSidebar px-5 py-3 text-[0.9rem] font-semibold text-white transition hover:opacity-90" data-no-iconify="true" type="submit">Entrar</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    (function(){
      const modal = document.getElementById('loginModal');
      const closeButton = document.getElementById('closeLoginModal');
      const emailField = document.getElementById('loginEmail');
      const passwordField = document.getElementById('loginPassword');
      const togglePassword = document.getElementById('togglePassword');
      const mobileMenuButton = document.getElementById('mobileMenuButton');
      const mobileMenu = document.getElementById('mobileMenu');
      const siteHeader = document.getElementById('siteHeader');
      const headerSpacer = document.getElementById('headerSpacer');
      const openButtons = document.querySelectorAll('[data-open-login="1"]');
      const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      let lastTrigger = null;

      const EYE = '<path d="M1.5 12s4-7.5 10.5-7.5S22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12Z"></path><path d="M12 15.75A3.75 3.75 0 1 0 12 8.25a3.75 3.75 0 0 0 0 7.5Z"></path>';
      const EYE_OFF = '<path d="M3 3l18 18"></path><path d="M10.58 10.58a3 3 0 0 0 4.24 4.24"></path><path d="M9.88 5.09A9.77 9.77 0 0 1 12 4.5c6.5 0 10.5 7.5 10.5 7.5a17.6 17.6 0 0 1-3.38 4.35M6.6 6.6C4.2 8.1 2.3 10.6 1.5 12c0 0 4 7.5 10.5 7.5 1.6 0 3.02-.36 4.26-.96"></path>';

      function svgIcon(pathData){
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="w-4 h-4">' + pathData + '</svg>';
      }

      if (togglePassword && passwordField) {
        togglePassword.innerHTML = svgIcon(EYE);
        togglePassword.addEventListener('click', function(){
          const showing = passwordField.type === 'text';
          passwordField.type = showing ? 'password' : 'text';
          togglePassword.setAttribute('aria-pressed', showing ? 'false' : 'true');
          togglePassword.setAttribute('aria-label', showing ? 'Mostrar senha' : 'Ocultar senha');
          togglePassword.innerHTML = svgIcon(showing ? EYE : EYE_OFF);
        });
      }

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
        mobileMenuButton.addEventListener('click', function(){
          if (!mobileMenu.hidden) { closeMobileMenu(); } else { openMobileMenu(); }
        });
        mobileMenu.querySelectorAll('a, button').forEach(function(item){
          item.addEventListener('click', closeMobileMenu);
        });
      }

      function trapFocus(event) {
        if (event.key !== 'Tab' || !modal || modal.classList.contains('modal-hidden')) return;
        const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault(); last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault(); first.focus();
        }
      }

      function openModal(trigger) {
        if (!modal) return;
        lastTrigger = trigger instanceof HTMLElement ? trigger : document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.remove('modal-hidden');
        window.requestAnimationFrame(function(){ if (emailField) emailField.focus(); });
        document.body.classList.add('overflow-hidden');
      }

      function closeModal() {
        if (!modal) return;
        modal.classList.add('modal-hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        window.setTimeout(function(){
          if (modal.classList.contains('modal-hidden')) { modal.hidden = true; }
        }, prefersReducedMotion ? 0 : 220);
        if (lastTrigger instanceof HTMLElement) { lastTrigger.focus(); }
      }

      openButtons.forEach(function(button){ button.addEventListener('click', function(){ openModal(button); }); });
      if (closeButton) closeButton.addEventListener('click', closeModal);
      if (modal) {
        modal.addEventListener('click', function(event){
          if (event.target === modal || event.target.classList.contains('modal-overlay')) closeModal();
        });
      }
      document.addEventListener('keydown', function(event){
        if (!modal || modal.classList.contains('modal-hidden')) return;
        if (event.key === 'Escape') closeModal();
        trapFocus(event);
      });

      window.addEventListener('resize', function(){
        if (window.innerWidth >= 768) { closeMobileMenu(); } else { syncHeaderOffset(); }
      });

      if (!<?= $openModal ? 'true' : 'false' ?> && modal) {
        closeModal();
      } else if (<?= $openModal ? 'true' : 'false' ?>) {
        openModal();
      }

      closeMobileMenu();
      syncHeaderOffset();

      // FAQ accordion — um item aberto por vez.
      document.querySelectorAll('.faq-item').forEach(function(item){
        var trigger = item.querySelector('.faq-q');
        if (!trigger) return;
        trigger.addEventListener('click', function(){
          var isOpen = item.classList.contains('open');
          document.querySelectorAll('.faq-item').forEach(function(i){ i.classList.remove('open'); });
          if (!isOpen) item.classList.add('open');
        });
      });

      // Reveal discreto ao rolar; se não houver IntersectionObserver ou o usuário
      // preferir menos movimento, o conteúdo aparece imediatamente (nunca fica oculto).
      const revealEls = document.querySelectorAll('.reveal');
      if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        revealEls.forEach(function(el){ el.classList.add('is-visible'); });
      } else {
        const observer = new IntersectionObserver(function(entries){
          entries.forEach(function(entry){
            if (entry.isIntersecting) {
              entry.target.classList.add('is-visible');
              observer.unobserve(entry.target);
            }
          });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function(el){ observer.observe(el); });
      }
    })();
  </script>
</body>
</html>
