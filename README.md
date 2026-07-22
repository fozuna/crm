# TRAXTER CRM

Sistema SaaS corporativo em PHP puro para gestão comercial, contratos, projetos, financeiro e ordens de serviço.

## Descrição

O diretório `gestor/` concentra a aplicação principal do TRAXTER CRM. O sistema opera como um monólito modular com renderização server-side, APIs HTTP internas e forte controle de integridade do banco de dados antes da liberação do ambiente.

Os módulos atualmente identificados no código são:

- autenticação e sessão;
- dashboard;
- branding legado e perfil empresarial;
- clientes e interações;
- leads e funil comercial;
- propostas comerciais e PDFs;
- contratos e templates;
- projetos, tarefas, marcos e eventos;
- financeiro de projetos;
- financeiro corporativo com recebíveis e recibos;
- auditoria;
- ordens de serviço, anexos e aprovação pública.

## Objetivo

Centralizar em um único sistema os fluxos de prospecção, cadastro, negociação, formalização contratual, execução de projetos, faturamento e rastreabilidade operacional.

## Tecnologias

- PHP 8.1+ inferido pelo código;
- MariaDB/MySQL;
- PDO orientado a objetos;
- Tailwind CSS via CDN;
- JavaScript vanilla;
- geração de PDF e XLSX por serviços internos;
- sem Composer, sem framework PHP e sem pipeline Node no estado atual.

## Regra oficial de banco de dados

Esta é uma regra permanente do projeto:

- nenhuma versão do sistema pode ser disponibilizada para uso sem que a estrutura do banco esteja sincronizada com a versão atual do código;
- toda alteração estrutural deve manter paridade entre `database/schema.sql` e `database/upgrade.sql`;
- a validação da estrutura é executada na inicialização da aplicação;
- se houver divergência estrutural, o sistema bloqueia o acesso até que o sincronizador oficial seja executado;
- o ambiente deve ser declarado em `APP_ENV` com um destes valores: `development`, `homolog` ou `production`.

## Instalação

### Pré-requisitos

- PHP 8.1+ com `pdo_mysql`, `openssl` e suporte a upload de arquivos;
- MariaDB/MySQL;
- servidor web compatível com `.htaccess` ou roteamento equivalente;
- permissão de escrita em `storage/`.

### Estrutura esperada

- publicar o conteúdo de `gestor/` como raiz da aplicação;
- manter `app/`, `config/`, `database/`, `docs/`, `resources/`, `storage/` e `tools/` inacessíveis diretamente via web, conforme `.htaccess`.

## Configuração

O sistema carrega configuração de:

1. `config/config.php` como fallback;
2. `.env` como sobrescrita opcional.

Variáveis essenciais observadas:

- `APP_NAME`
- `APP_ENV`
- `APP_URL`
- `APP_BASE_PATH`
- `APP_DEBUG`
- `APP_TIMEZONE`
- `APP_KEY`
- `APPROVAL_REQUIRE_HTTPS`
- `SERVICE_ORDER_APPROVAL_TTL_HOURS`
- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `DB_REQUIRE_SYNC_BEFORE_RUN`

Use `.env.example` como referência inicial.

## Como executar

### Sincronização manual do banco

```bash
php tools/db_sync.php --env=development
php tools/db_sync.php --env=homolog
php tools/db_sync.php --env=production
```

### Preflight obrigatório de deploy

```bash
php tools/deploy_preflight.php --env=development
php tools/deploy_preflight.php --env=homolog
php tools/deploy_preflight.php --env=production
```

### Suíte de testes

```bash
php tests/run.php
```

### Fluxo obrigatório por ambiente

#### Desenvolvimento

1. Atualize o código.
2. Execute `php tools/db_sync.php --env=development`.
3. Execute `php tests/run.php`.
4. Só então acesse `/login` ou qualquer rota do sistema.

#### Homologação

1. Publique o código.
2. Execute `php tools/deploy_preflight.php --env=homolog`.
3. Libere o ambiente somente após o preflight concluir sem falhas.

#### Produção

1. Publique o código.
2. Execute `php tools/deploy_preflight.php --env=production`.
3. Valide logs e resultado do preflight.
4. Só então disponibilize o sistema para os usuários.

## Estrutura do projeto

```text
gestor/
├── app/
│   ├── Contracts/
│   ├── Controllers/
│   ├── Core/
│   ├── DTOs/
│   ├── Middleware/
│   ├── Repositories/
│   └── Services/
├── config/
├── database/
├── docs/
├── resources/views/
├── storage/
├── tests/
├── tools/
├── .env.example
├── .htaccess
├── CLAUDE.md
├── README-HOSTINGER.md
└── README.md
```

## Documentação

Os arquivos centrais de documentação técnica ficam em:

- `CLAUDE.md`
- `CHANGELOG.md`
- `CONTRIBUTING.md`
- `docs/arquitetura.md`
- `docs/banco.md`
- `docs/regras-negocio.md`
- `docs/api.md`
- `docs/deploy.md`
- `docs/ambiente.md`
- `docs/padroes-codigo.md`
- `docs/padroes-ui.md`
- `docs/seguranca.md`
- `docs/roadmap.md`
- `docs/backlog.md`
- `docs/dependencias.md`
- `docs/estrutura-pastas.md`
- `docs/troubleshooting.md`

Há também documentação histórica e pontual em `docs/` para módulos específicos, como branding, contratos, clientes, serviços e reset de banco.

## Validação automática na inicialização

- a aplicação verifica automaticamente a integridade estrutural do banco no bootstrap;
- se houver tabelas, colunas ou enums obrigatórios ausentes, o acesso é bloqueado;
- o comportamento é controlado por `DB_REQUIRE_SYNC_BEFORE_RUN=true`.

## Logs

Os principais logs observados são:

- `storage/logs/db-lifecycle.log`
- `storage/logs/app.log`
- `storage/logs/runtime-events.ndjson`
- `storage/logs/finance.log`

Eles cobrem, conforme o fluxo:

- falhas de validação estrutural do banco;
- falhas de sincronização;
- falhas de preflight de deploy;
- exceções globais;
- eventos financeiros e diagnósticos pontuais.

## Como publicar

Para hospedagem compartilhada, o fluxo suportado está documentado em `README-HOSTINGER.md`. Em resumo:

1. publicar o conteúdo de `gestor/`;
2. configurar `config/config.php` ou `.env`;
3. garantir escrita em `storage/`;
4. executar `php tools/deploy_preflight.php --env=production`;
5. validar `/login`.

## Screenshots

Não há screenshots versionados no repositório no estado atual.

## Roadmap

O roadmap técnico gerado a partir da análise do código está em `docs/roadmap.md`.

## Licença

Este repositório não possui licença permissiva explícita versionada no estado atual. Consulte `LICENSE`.

## Autores

Histórico Git observado:

- Fabio Ozuna
