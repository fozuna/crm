# Arquitetura

## Visão geral

O TRAXTER CRM é um monólito modular em PHP puro, sem framework externo, com MVC leve, roteador próprio, renderização server-side e APIs HTTP internas em JSON.

## Estilo arquitetural

- monólito modular;
- MVC leve;
- services e repositories como camadas principais de negócio e persistência;
- views PHP organizadas por módulo;
- bootstrap próprio com controle de sessão, configuração, logs e integridade do banco.

## Fluxo de requisição

1. `index.php` carrega `app/bootstrap.php`.
2. O bootstrap registra autoload simples, carrega configuração e cria diretórios de runtime.
3. A sessão é iniciada por `App\Core\Session`.
4. `App\Core\Application::run()` cria o `Request`.
5. Se `DB_REQUIRE_SYNC_BEFORE_RUN=true`, `App\Services\DbStartupGuard` valida a estrutura do banco.
6. As rotas são carregadas de `config/routes.php`.
7. O `Router` executa middlewares e invoca o controller correspondente.
8. O controller responde com:
   - view HTML;
   - redirect;
   - JSON;
   - download/stream de arquivo.

## Camadas

### Core

Responsável por infraestrutura mínima:

- `Application.php`
- `Router.php`
- `Request.php`
- `Response.php`
- `Config.php`
- `DB.php`
- `Session.php`
- `Csrf.php`
- `View.php`
- `UI.php`

### Middleware

Responsável por políticas transversais:

- `AuthMiddleware`: exige sessão;
- `AdminMiddleware`: exige admin;
- `RoleMiddleware`: aplica RBAC por papel;
- `CsrfMiddleware`: protege mutações HTTP.

### Controllers

Controllers orquestram fluxo HTTP e delegam regra de negócio. O padrão observado é:

- ler dados do request;
- chamar service e/ou repository;
- montar resposta;
- evitar SQL ou regra complexa no controller.

### Services

Services concentram:

- regras de negócio;
- validações;
- transações;
- geração de PDFs/XLSX;
- criptografia;
- sincronização/inspeção de banco;
- automações entre módulos.

### Repositories

Repositories concentram:

- consultas SQL;
- inserts e updates via PDO;
- agregações e consultas analíticas;
- persistência desacoplada de controllers.

### Views

As views ficam em `resources/views` e seguem renderização server-side. O layout principal é `resources/views/layout.php`, e os assets de estilo/base ficam em `resources/views/partials/head.php`.

## Módulos de negócio

### Plataforma

- autenticação;
- sessão;
- branding;
- company profile;
- manutenção e diagnóstico.

### Comercial

- clientes;
- leads;
- propostas;
- contratos.

### Operação

- serviços;
- ordens de serviço;
- aprovação pública de OS;
- projetos.

### Financeiro

- métodos de pagamento;
- financeiro de projetos;
- contas a receber corporativas;
- recibos;
- relatórios e dashboard.

### Governança

- auditoria;
- histórico de alterações;
- logs de runtime;
- validação estrutural do banco.

## Organização do código

```text
app/
├── Contracts/      # contratos usados em desacoplamento e testes
├── Controllers/    # entrada HTTP
├── Core/           # infraestrutura mínima da aplicação
├── DTOs/           # objetos simples do domínio financeiro
├── Middleware/     # autenticação, RBAC e CSRF
├── Repositories/   # SQL e persistência
└── Services/       # regras, automações, documentos e utilitários
```

## Padrões de acoplamento

- controllers conhecem services e repositories;
- services podem conhecer repositories, contracts e utilitários;
- repositories conhecem `DB::pdo()`;
- views não devem conter regra de negócio;
- middlewares são acoplados ao roteamento.

## Mapa de entrada e saída

### Entradas

- formulários HTML;
- chamadas AJAX para `/api/*`;
- uploads de arquivos;
- link público de aprovação de OS.

### Saídas

- HTML server-side;
- JSON;
- PDF;
- Excel/CSV;
- arquivos anexos e comprovantes.

## Componentes críticos

### Guard estrutural do banco

`DbStartupGuard` bloqueia a aplicação quando a estrutura mínima esperada diverge do código.

### Sincronização de banco

`DbSyncRunner` executa `schema.sql` e `upgrade.sql` em sequência, e `DbUpgradeRunner` inspeciona compatibilidade estrutural.

### Aprovação pública de OS

O fluxo usa token assinado, persistência apenas do hash do token, auditoria e geração de comprovante PDF.

### Geração documental

O sistema gera documentos de negócio sem dependência externa:

- propostas;
- contratos;
- ordens de serviço;
- recibos financeiros.

## Decisões arquiteturais observadas

- sem framework para manter simplicidade operacional em hospedagem compartilhada;
- sem Composer no estado atual;
- banco como componente central e validado antes do boot;
- APIs internas protegidas por sessão e CSRF;
- renderização híbrida SSR + endpoints JSON;
- forte dependência de documentação operacional para deploy seguro.
