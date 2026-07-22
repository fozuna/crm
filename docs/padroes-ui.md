# Padrões de UI

## Base visual

O frontend usa Tailwind CSS via CDN com complementos CSS e JavaScript próprios em `resources/views/partials/head.php`.

## Paleta observada

Cores base carregadas do branding:

- primária: `#293241`
- acento: `#ee6c4d`
- texto claro institucional: `#fefefe`

Essas cores são expostas como:

- `traxterSidebar`
- `traxterAccent`
- `traxterText`
- `traxterDark`

Também são refletidas em CSS customizado via:

- `--tr-primary`
- `--tr-accent`
- `--tr-font`

## Tipografia

- fonte principal dinâmica via branding, com fallback para `Helvetica`, `Arial`, `sans-serif`;
- hierarquia baseada em Tailwind e classes próprias;
- ênfase em leitura limpa e contraste alto.

## Layout principal

O layout autenticado usa:

- sidebar fixa à esquerda;
- conteúdo principal com `ml-64`;
- navegação por ícones;
- badge de papel do usuário no topo direito.

Arquivo central:

- `resources/views/layout.php`

## Landing page de login

A tela `/login` foi convertida em landing page institucional com:

- header fixo;
- hero section;
- cards de recursos e benefícios;
- modal de login;
- menu mobile;
- branding carregado do perfil empresarial quando disponível.

Arquivo central:

- `resources/views/auth/login.php`

## Componentes visuais reutilizados

### Campos

- `.tr-label`
- `.tr-hint`
- `.tr-input`

### Contêineres

- `.tr-card`
- `.tr-badge`

### Ações

- `.tr-btn`
- `.tr-btn--icon-only`
- `.tr-icon-btn`
- `.tr-icon-btn--accent`

### Feedback

- `.tr-toast`
- variantes `success`, `error`, `info`, `warning`

## Ícones

O projeto usa iconografia SVG inline gerada internamente.

Características:

- sem biblioteca externa de ícones no estado atual;
- ícones mapeados em `head.php`;
- heurística automática converte textos de botões `.tr-btn` em botões iconográficos;
- `aria-label` e `title` são reforçados automaticamente.

Ícones mapeados incluem:

- `eye`
- `edit`
- `trash`
- `plus`
- `check`
- `x`
- `arrow-left`
- `dollar`
- `pdf`
- `excel`
- `print`
- `download`
- `filter`
- `search`
- `chart`
- `list`
- `save`
- `refresh`

## Responsividade

Padrões observados:

- uso amplo de utilitários Tailwind responsivos;
- menu mobile na landing page;
- sidebar fixa no ambiente autenticado;
- cards e grids com colunas variáveis conforme breakpoint.

## Espaçamentos e densidade

- arredondamentos frequentes entre `.625rem` e `.75rem`;
- botões compactos e orientados a ação;
- cards com sombra discreta;
- cabeçalhos e métricas com contraste institucional.

## Metadados e SEO

`partials/head.php` também centraliza:

- `title`;
- `description`;
- `keywords`;
- `robots`;
- `canonical`;
- Open Graph;
- Twitter card;
- JSON-LD.

Isso é especialmente relevante na landing page de login.

## Branding dinâmico

O sistema carrega branding de repositórios/serviços:

- `ProposalBrandingRepository` para branding legado;
- `CompanyProfileService` para branding institucional mais amplo.

Esse branding afeta:

- cores;
- nome da empresa;
- logos;
- favicon;
- meta image;
- documentos PDF.

## Padrões de acessibilidade observados

- `aria-label` em ações iconográficas;
- `title` em links e botões;
- `sr-only` em ações essenciais;
- feedback visual de foco com `box-shadow`;
- contraste alto nas ações primárias.

## O que preservar em futuras telas

- paleta institucional azul + laranja;
- botões iconográficos com acessibilidade;
- layout sóbrio, corporativo e sem excesso visual;
- responsividade mínima entre desktop e mobile;
- consistência entre interface web e documentos PDF quando aplicável.
