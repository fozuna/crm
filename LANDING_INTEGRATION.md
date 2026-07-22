# LANDING_INTEGRATION.md

## Contexto

Este documento registra a sprint "Substituição da tela de login pela nova landing TRAXTER".

Antes de qualquer alteração de código, foi constatado que a transformação do login em
landing page **já existia** no projeto, em duas camadas:

1. Commitada em `d58fd23` ("Transforma login em landing page institucional", 2026-07-03).
2. Uma refinada, já presente no working tree (não commitada) no início desta sprint,
   sem preços/depoimentos fictícios e já com módulos reais.

O material de referência anexado a esta sprint (`traxter-landing.html`) trazia uma
direção visual diferente (tema escuro grafite/laranja) e conteúdo genérico de mockup
(planos de preço fictícios, depoimentos, bloco `[ ESPAÇO RESERVADO PARA CASES... ]`).
Decisão tomada em conjunto com o solicitante: **manter o fluxo, o conteúdo real e a
arquitetura já existentes**, e **reestilizar** a view para a estética do anexo — sem
reintroduzir preço, depoimento ou qualquer dado fictício apresentado como real.

## Fluxo resultante

```
Visitante
  -> GET /            (AuthMiddleware; sem sessão -> redirect 302 para /login)
  -> GET /login        (landing institucional + modal de login)
  -> POST /login        (AuthController::login, CSRF)
  -> GET /dashboard      (DashboardController::index, autenticado)
```

Nenhuma rota nova foi criada. `/` já é protegida por `$auth` (`AuthMiddleware`) em
`config/routes.php`; para quem não está autenticado, o middleware redireciona para
`/login`, que é a landing. O fluxo "visitante -> landing -> login -> dashboard" do
sprint já era satisfeito por essa combinação e continua sendo, sem mexer em
`config/routes.php`.

## Arquivos alterados

| Arquivo | Natureza da mudança |
|---|---|
| `resources/views/auth/login.php` | Reescrita visual (tema grafite/laranja, tipografia Archivo/Inter/IBM Plex Mono, novo hero com cartão de OS, seção de problema, grid de módulos, ciclo de vida da OS, seção de arquitetura, FAQ, CTA final). Mantido: PHP de branding/SEO, `AuthController` como único responsável pela autenticação, modal de login, acessibilidade. |
| `tests/landing_login_page.php` | Novo. Teste de regressão que dispara `/login` via `Router::dispatch()` real e valida conteúdo, SEO, acessibilidade e ausência de conteúdo fictício. |
| `tests/run.php` | Registra a nova suíte `landing_login_page.php`. |

Nenhuma rota, controller, middleware, tabela ou coluna foi alterada.

## Componentes e infraestrutura reaproveitados

- `App\Controllers\AuthController` — `showLogin`/`login`/`logout` inalterados; a
  view apenas consome `csrf`, `base` e `error` como já fazia antes desta sprint.
- `resources/views/partials/head.php` — meta title/description/keywords, robots,
  favicon, canonical, Open Graph, Twitter Card e schema.org (JSON-LD), todos já
  orientados por `ProposalBrandingRepository`/`CompanyProfileService`. Nenhuma tag
  de SEO foi duplicada ou recriada na view.
- `App\Services\CompanyProfileService::branding()` — nome da empresa, tagline,
  cores, CNPJ, e-mail, WhatsApp e site usados no hero, footer e schema.org.
- `App\Core\UI::icon()` / `App\Core\View::e()` — ícones e escaping de saída, sem
  introduzir uma segunda biblioteca de ícones.
- Tokens de cor de marca (`--tr-primary`/`--tr-accent`, definidos em `head.php` a
  partir de `primary_color`/`accent_color` da empresa) — a nova paleta escura é
  **derivada** desses tokens (`color-mix()`), não uma paleta nova hardcoded. Se o
  administrador alterar a cor da marca em Configurações, a landing acompanha.
- CSRF (`Csrf::token()`) e o próprio formulário de login — reaproveitados sem
  qualquer alteração de fluxo de autenticação.

## Decisões arquiteturais

- **Login como modal na própria landing, não uma segunda página.** O sprint permitia
  as duas opções; o modal evita introduzir uma segunda superfície de SEO para a
  mesma intenção de conversão e mantém toda a lógica de autenticação dentro do
  `AuthController` já existente.
- **Nenhuma rota nova para `/`.** Reaproveitar o redirecionamento que o
  `AuthMiddleware` já faz evita duplicar a landing em duas rotas/views diferentes
  (ver "Pendências" abaixo para o trade-off dessa escolha).
- **Sem seção de preços.** Não existe modelo de plano/assinatura no schema do banco
  nem em `config/routes.php`; qualquer valor exibido seria inventado. A seção foi
  omitida por completo, como o sprint exige para dados fictícios.
- **Bloco de cases/depoimentos removido, não adiado.** Não há depoimento real
  disponível; em vez de manter um placeholder visível em produção, a seção foi
  eliminada inteiramente.
- **Mockup do cartão de OS rotulado como ilustração.** O hero mostra um cartão de
  Ordem de Serviço com o ciclo de status real (`aberto` -> `em_andamento` ->
  `concluido` -> `faturado`, conforme `database/schema.sql`), mas com dado de
  cliente genérico e a legenda explícita "Ilustração da interface do sistema —
  dados fictícios", para não ser lido como um case real.
- **FAQ restrita a afirmações verificáveis em código.** Perguntas sobre
  necessidade de instalação, controle de acesso por papel, proteção de dados
  (hash de senha, CSRF, auditoria) e aprovação externa de OS por link assinado —
  todas rastreáveis em `AuthMiddleware`/`RoleMiddleware`/`ServiceOrderApprovalController`.
  Não foram incluídas afirmações de modelo comercial (cancelamento, importação em
  lote) presentes no material de referência, por não serem verificáveis no
  produto atual.

## Testes

- `php tests/run.php` — 177/177 testes passando (153 pré-existentes + 24 novos),
  incluindo o novo `tests/landing_login_page.php`.
- Validação manual em navegador (servidor de desenvolvimento local): renderização
  do hero/módulos/FAQ/CTA, abertura e foco do modal de login, menu mobile,
  acordeão do FAQ (um item aberto por vez), ausência de overflow horizontal em
  375px, e confirmação de que `/` leva a `/login` para visitante não autenticado.
- Tags de SEO conferidas via inspeção do DOM renderizado: title, description,
  canonical, Open Graph, Twitter Card, schema.org (Organization) e favicon, todas
  preenchidas com dado real da empresa configurada.

## Pendências / próximos passos

- **SEO da URL raiz.** Hoje `/` chega à landing apenas via redirect 302 do
  `AuthMiddleware`; o canonical aponta para `/login`. Se no futuro for importante
  que `https://crm.traxter.com.br/` seja indexado diretamente (sem redirect),
  será necessário criar uma ação pública dedicada para `/` em `config/routes.php`
  — hoje fora do escopo desta sprint por exigir decidir se `/dashboard` continua
  sendo a home autenticada.
- **Fontes via CDN externo.** Archivo/Inter/IBM Plex Mono são carregadas do Google
  Fonts, no mesmo padrão do Tailwind CDN já usado no projeto. Se o ambiente de
  deploy precisar operar sem dependências externas, essas fontes precisariam ser
  auto-hospedadas.
- **Auditoria de contraste/acessibilidade com ferramenta dedicada.** A revisão
  desta sprint foi manual (estrutura semântica, skip-link, foco do modal,
  `prefers-reduced-motion`, `aria-*`); uma auditoria com leitor de tela real e
  verificador de contraste automatizado ainda não foi feita.
- **Cases/depoimentos reais.** Quando existirem clientes dispostos a ceder
  depoimento, a seção pode ser reintroduzida com dado real — não antes disso.
