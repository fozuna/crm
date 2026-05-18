# Reset do banco (preservando usuários)

## O que faz
- Gera backup completo do banco em `storage/backups/` (SQL gzip) e criptografa o arquivo.
- Limpa todas as tabelas, exceto `users` e as tabelas preservadas automaticamente por dependência (FK) + tabelas de auditoria.
- Re-seeda o mínimo técnico (ex.: `company_profile` id=1 e catálogos financeiros) para não quebrar FKs e permitir uso do sistema após o reset.
- Grava logs detalhados por tabela (contagem before/deleted/after) em arquivo e registra auditoria em `audit_log`.

## Pré-requisitos obrigatórios
- Ter um usuário admin logado para executar as rotas de manutenção.
- Definir `APP_KEY` forte.
- Habilitar explicitamente o reset no ambiente correto:
  - `DB_RESET_ENABLED=true`
  - `DB_RESET_TARGET=production` (em produção) ou `staging` (em homologação)
  - `DB_RESET_ALLOWED_HOST=crm.seudominio.com.br` (recomendado)
  - `DB_RESET_ALLOWED_DB=nome_exato_do_banco` (recomendado)
- Definir a frase de confirmação:
  - `DB_RESET_CONFIRM_PHRASE=RESETAR-BANCO-PRODUCAO` (ou a frase que você escolher)

## Rotas
- Plano/inspeção (não altera nada):
  - `GET /maintenance/db-reset/plan`
- Execução:
  - `POST /maintenance/db-reset/start`
- Status:
  - `GET /maintenance/db-reset/status/{jobId}`

## Execução em homologação (obrigatório antes de produção)
1. Replicar o banco de produção em homologação (mesma estrutura e dados).
2. Ajustar `DB_RESET_TARGET=staging`, `DB_RESET_ALLOWED_HOST` e `DB_RESET_ALLOWED_DB` para homologação.
3. Chamar `GET /maintenance/db-reset/plan` e anotar:
   - `users_count`
   - `tables_preserve` e `tables_purge`
4. Executar o reset via `POST /maintenance/db-reset/start` com:
   - `confirm` igual a `DB_RESET_CONFIRM_PHRASE`
   - `target` igual a `DB_RESET_TARGET`
   - `users_count` igual ao retornado no plan
   - `passphrase` (mín. 12 chars) para criptografar o backup
5. Acompanhar `GET /maintenance/db-reset/status/{jobId}` até `status=done`.
6. Validar:
   - `users` intacta (contagem e autenticação funcionando).
   - tabelas limpas (verify ok).
   - navegação principal (dashboard, clientes, projetos, financeiro) sem erros.

## Execução em produção
1. Confirmar que `DB_RESET_TARGET=production` e `DB_RESET_ALLOWED_HOST/DB_RESET_ALLOWED_DB` estão corretos.
2. Rodar `GET /maintenance/db-reset/plan` e guardar o `users_count`.
3. Rodar `POST /maintenance/db-reset/start` com os mesmos campos da homologação.
4. Validar `status=done`.
5. Fazer validação manual pós-execução:
   - Login com usuários existentes.
   - Conferir permissões/role e acesso aos módulos.
   - Executar uma ação básica (ex.: cadastrar cliente) para confirmar integridade.

## Payload sugerido (JSON)
Enviar com header `Content-Type: application/json` e CSRF (header `X-CSRF-Token` ou `_csrf`).

```json
{
  "confirm": "RESETAR-BANCO-PRODUCAO",
  "target": "production",
  "users_count": 3,
  "passphrase": "uma-frase-grande-e-forte-aqui",
  "dry_run": false
}
```

## Logs e backup gerados
- Job: `storage/jobs/db-reset-{jobId}.json`
- Log detalhado: `storage/logs/db_reset_{jobId}.log`
- Backup criptografado:
  - `storage/backups/backup_{db}_{timestamp}.sql.gz.enc`
  - `storage/backups/backup_{db}_{timestamp}.sql.gz.enc.json`

## Decriptação/restauração (offline)
1. Baixar o `.enc` e o `.enc.json` do servidor (FTP/SFTP).
2. Descriptografar localmente com o script:
   - `php tools/decrypt_backup.php backup.sql.gz.enc.json backup.sql.gz.enc backup.sql.gz "sua-passphrase"`
3. Descompactar:
   - `gzip -d backup.sql.gz` (Linux/macOS) ou usar 7-Zip no Windows
4. Restaurar com o método padrão do seu ambiente (phpMyAdmin/import ou CLI).
