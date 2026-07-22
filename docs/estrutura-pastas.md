# Estrutura de Pastas

## Visão geral

Esta é a estrutura relevante observada no diretório `gestor/` no momento da análise.

```text
gestor/
├── app/
├── database/
├── docs/
├── resources/views/
├── tests/
├── tools/
├── storage/
├── .env.example
├── .gitignore
├── .htaccess
├── CLAUDE.md
├── CHANGELOG.md
├── CONTRIBUTING.md
├── LICENSE
├── README-HOSTINGER.md
├── README.md
├── dev-router.php
└── index.php
```

## Raiz do projeto

### `index.php`

- front controller da aplicação.

### `dev-router.php`

- roteador/local helper para execução em ambiente de desenvolvimento.

### `.htaccess`

- protege diretórios internos e direciona requisições ao front controller.

### `.env.example`

- modelo de configuração de ambiente.

### `README.md`

- documentação principal do projeto.

### `README-HOSTINGER.md`

- fluxo operacional específico para hospedagem compartilhada.

### `CLAUDE.md`

- contexto técnico principal para IA e manutenção futura.

### `CHANGELOG.md`

- histórico recente reconstruído do Git.

### `CONTRIBUTING.md`

- guia de contribuição e regras operacionais.

### `LICENSE`

- aviso de ausência de licença permissiva explícita no estado atual.

## Diretório `app/`

### `app/bootstrap.php`

- bootstrap global;
- autoload;
- configuração;
- sessão;
- logs;
- tratamento global de erros.

### `app/Core/`

- infraestrutura base da aplicação.

Arquivos centrais:

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

### `app/Controllers/`

- entrada HTTP dos módulos;
- separa controllers web e API.

### `app/Middleware/`

- middlewares de autenticação, admin, papéis e CSRF.

### `app/Services/`

- regras de negócio;
- validações;
- automações;
- sincronização de banco;
- geração de PDF/XLSX;
- criptografia;
- upload/processamento de ativos.

### `app/Repositories/`

- acesso a dados via PDO;
- SQL de leitura, escrita e relatórios.

### `app/Contracts/`

- contratos usados em desacoplamento e testes.

### `app/DTOs/`

- objetos simples usados pelo módulo financeiro.

## Diretório `config/`

### `config/config.php`

- fallback de configuração.

### `config/routes.php`

- mapa central de rotas e middlewares.

## Diretório `database/`

### `database/schema.sql`

- instalação base.

### `database/upgrade.sql`

- upgrades incrementais e idempotentes.

## Diretório `docs/`

Documentação histórica e complementar do projeto, incluindo:

- branding e company profile;
- contratos;
- clientes;
- reset de banco;
- iconografia;
- serviços;
- análises pontuais de auditoria.

Agora também concentra a documentação técnica sistêmica criada a partir do código atual.

## Diretório `resources/views/`

- views PHP server-side por módulo.

Subpastas observadas:

- `auth/`
- `audit/`
- `branding/`
- `clients/`
- `company_profile/`
- `contracts/`
- `dashboard/`
- `financial/`
- `install/`
- `leads/`
- `manual/`
- `partials/`
- `payment_methods/`
- `projects/`
- `proposals/`
- `reports/`
- `service_orders/`
- `services/`

Arquivos centrais:

- `layout.php`
- `partials/head.php`

## Diretório `tests/`

Suíte customizada em PHP puro.

Arquivos observados:

- `run.php`
- `database_structure.php`
- `leads_module.php`
- `pdfs_all.php`
- `production_error_handling.php`
- `service_order_approval_module.php`
- `service_orders_module.php`

## Diretório `tools/`

Scripts operacionais:

- `db_sync.php`
- `deploy_preflight.php`
- `db_upgrade_worker.php`
- `db_reset_worker.php`
- `decrypt_backup.php`
- `regenerate_proposal_pdf.php`
- `rebuild_legacy_proposal_pdf.php`

## Diretório `storage/`

Persistência local de runtime:

- logs;
- cache;
- jobs;
- sessões;
- uploads;
- PDFs gerados.

## Observação sobre artefatos temporários

No estado atual do diretório raiz existem também artefatos temporários de depuração:

- `debug-production-500.md`
- `debug-production-500-repro.php`

Eles não fazem parte do fluxo principal da aplicação, mas estavam presentes no momento da análise.
