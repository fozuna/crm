# Deploy

## Premissa central

Nenhum ambiente pode ser liberado sem sincronização prévia do banco com a versão atual do código.

Essa regra é aplicada por:

- documentação oficial;
- scripts `tools/db_sync.php` e `tools/deploy_preflight.php`;
- validação estrutural executada no bootstrap da aplicação.

## Ambientes

Valores válidos para `APP_ENV`:

- `development`
- `homolog`
- `production`

## Fluxo oficial por ambiente

### Desenvolvimento

1. Atualizar o código.
2. Executar:

```bash
php tools/db_sync.php --env=development
php tests/run.php
```

3. Só então acessar o sistema.

### Homologação

1. Publicar o código.
2. Executar:

```bash
php tools/deploy_preflight.php --env=homolog
```

3. Validar resultado do preflight.
4. Liberar ambiente para testes funcionais apenas se não houver falhas.

### Produção

1. Publicar o código.
2. Executar:

```bash
php tools/deploy_preflight.php --env=production
```

3. Validar logs e saída do preflight.
4. Só então liberar acesso aos usuários.

## Scripts operacionais

### `tools/db_sync.php`

Responsável por:

- aplicar `database/schema.sql`;
- aplicar `database/upgrade.sql`;
- reinspecionar compatibilidade estrutural;
- falhar explicitamente se a estrutura final ainda estiver divergente.

### `tools/deploy_preflight.php`

Responsável por:

- executar sincronização;
- reinspecionar o banco;
- rodar validação estrutural;
- bloquear deploy se houver erro.

### `tools/db_upgrade_worker.php`

- worker interno para processos assíncronos de upgrade.

### `tools/db_reset_worker.php`

- worker interno para reset controlado, sujeito a travas de segurança.

## Configuração obrigatória

### Aplicação

- `APP_ENV`
- `APP_URL`
- `APP_BASE_PATH`
- `APP_KEY`
- `APP_DEBUG`
- `APP_TIMEZONE`

### Banco

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- `DB_REQUIRE_SYNC_BEFORE_RUN`

### Aprovação pública

- `APPROVAL_REQUIRE_HTTPS`
- `SERVICE_ORDER_APPROVAL_TTL_HOURS`

### E-mail

- `MAIL_FROM_EMAIL`
- `MAIL_FROM_NAME`

## Estrutura esperada em produção

Arquivos web principais:

- `index.php`
- `.htaccess`

Diretórios internos protegidos:

- `app/`
- `config/`
- `database/`
- `docs/`
- `resources/`
- `storage/`
- `tools/`

## Hospedagem compartilhada

O projeto foi preparado para operar sem Composer e sem comandos complexos de bootstrap do framework. O fluxo documentado em `README-HOSTINGER.md` indica compatibilidade com hospedagem compartilhada, desde que:

- o servidor execute PHP compatível;
- `.htaccess` esteja ativo;
- `storage/` tenha permissão de escrita;
- exista acesso ao banco MariaDB/MySQL.

## Arquivos e permissões

Permissões mínimas observadas:

- escrita em `storage/cache`;
- escrita em `storage/jobs`;
- escrita em `storage/logs`;
- escrita em `storage/pdfs/*`;
- escrita em `storage/sessions`;
- escrita em `storage/uploads/*`.

O próprio bootstrap tenta criar os diretórios de runtime ausentes.

## Verificações pós-deploy

Após publicar:

1. confirmar que `/login` carrega;
2. confirmar que não há erro 500;
3. verificar `storage/logs/app.log`;
4. verificar `storage/logs/db-lifecycle.log`;
5. quando aplicável, verificar `storage/logs/runtime-events.ndjson`.

## Rollback

Não existe rotina automatizada de rollback versionada no repositório. No estado atual, rollback depende de:

- recuperar código anterior do Git;
- restaurar banco por procedimento operacional externo, quando necessário;
- reexecutar validações estruturais compatíveis com a versão revertida.

## Riscos operacionais conhecidos

- liberar deploy sem `deploy_preflight`;
- divergência entre `schema.sql`, `upgrade.sql` e código;
- falta de permissão em `storage/`;
- configuração incorreta de `APP_URL`, `APP_BASE_PATH` ou `APP_KEY`;
- deploy parcial que omita arquivos internos ou scripts.
