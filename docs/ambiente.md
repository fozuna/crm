# Ambiente

## Requisitos de runtime

## PHP

Versão mínima inferida pelo código:

- PHP 8.1+

Indícios:

- `readonly`;
- `match`;
- promoted properties;
- `str_starts_with`;
- tipagem estrita ampla.

## Extensões e recursos nativos observados

- `pdo_mysql`
- `openssl`
- `fileinfo` ou `mime_content_type`
- `gd` para parte do tratamento de imagens/PDF
- `imagick` opcional para algumas conversões de imagem

## Banco

- MariaDB/MySQL;
- charset padrão `utf8mb4`.

## Servidor web

O projeto foi estruturado para operar com:

- Apache ou ambiente compatível com `.htaccess`;
- fallback local via `dev-router.php`.

## Variáveis de ambiente

## Aplicação

| Variável | Finalidade |
| --- | --- |
| `APP_NAME` | Nome institucional da aplicação |
| `APP_ENV` | Ambiente (`development`, `homolog`, `production`) |
| `APP_URL` | URL base da aplicação |
| `APP_BASE_PATH` | Subpasta, quando aplicável |
| `APP_DEBUG` | Exibição detalhada de erro |
| `APP_TIMEZONE` | Timezone da aplicação |
| `APP_KEY` | Chave base para criptografia/tokenização |

## Aprovação pública

| Variável | Finalidade |
| --- | --- |
| `APPROVAL_REQUIRE_HTTPS` | Exige HTTPS no fluxo público de OS |
| `SERVICE_ORDER_APPROVAL_TTL_HOURS` | Prazo de validade do link |

## E-mail

| Variável | Finalidade |
| --- | --- |
| `MAIL_FROM_EMAIL` | Remetente padrão |
| `MAIL_FROM_NAME` | Nome do remetente |

## Banco

| Variável | Finalidade |
| --- | --- |
| `DB_HOST` | Host do banco |
| `DB_PORT` | Porta |
| `DB_NAME` | Nome do banco |
| `DB_USER` | Usuário |
| `DB_PASS` | Senha |
| `DB_CHARSET` | Charset |
| `DB_REQUIRE_SYNC_BEFORE_RUN` | Ativa guard estrutural |

## Reset de banco

| Variável | Finalidade |
| --- | --- |
| `DB_RESET_ENABLED` | Habilita reset controlado |
| `DB_RESET_TARGET` | Ambiente-alvo permitido |
| `DB_RESET_ALLOWED_HOST` | Host permitido |
| `DB_RESET_ALLOWED_DB` | Banco permitido |
| `DB_RESET_CONFIRM_PHRASE` | Frase de confirmação |
| `DB_RESET_PRESERVE_TABLES` | Tabelas preservadas |
| `DB_RESET_SEED_MINIMUM` | Semeadura mínima após reset |

## Arquivos de configuração

- `config/config.php`: fallback principal;
- `.env`: sobrescrita opcional;
- `.env.example`: referência de variáveis.

## Diretórios de runtime

Criados automaticamente no bootstrap quando ausentes:

- `storage/cache`
- `storage/jobs`
- `storage/logs`
- `storage/pdfs/contracts`
- `storage/pdfs/proposals`
- `storage/pdfs/service_orders/approvals`
- `storage/sessions`
- `storage/uploads/clients`
- `storage/uploads/company_profile`
- `storage/uploads/company_profile/branding`

## Logs de runtime

- `storage/logs/app.log`
- `storage/logs/db-lifecycle.log`
- `storage/logs/runtime-events.ndjson`
- `storage/logs/finance.log`

## Perfis de uso

### Desenvolvimento local

O repositório atual mostra uso local em ambiente Windows/Laragon, mas o sistema foi escrito para ser compatível com hospedagem compartilhada Linux/Apache também.

### Homologação e produção

O modelo operacional esperado é:

- upload/publicação do código;
- configuração via `.env` ou `config/config.php`;
- execução de `deploy_preflight`;
- validação de logs e acesso.

## Sensibilidades do ambiente

- `APP_URL` e `APP_BASE_PATH` impactam redirects, links e assets;
- `APP_KEY` afeta criptografia e assinatura;
- permissões em `storage/` impactam sessão, logs, PDFs e uploads;
- divergência de banco bloqueia a aplicação quando o guard está ativo.
