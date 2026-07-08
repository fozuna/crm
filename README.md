# TRAXTER CRM

CRM Traxter para gestão de projetos, propostas, contratos, financeiro, leads e ordens de serviço.

## Regra oficial de banco de dados

Esta é uma regra permanente do projeto:

- Nenhuma versão do sistema pode ser disponibilizada para uso sem que a estrutura do banco esteja sincronizada com a versão atual do código.
- Toda alteração estrutural deve manter paridade entre `database/schema.sql` e `database/upgrade.sql`.
- A validação da estrutura é executada na inicialização da aplicação.
- Se houver divergência estrutural, o sistema bloqueia o acesso até que o sincronizador oficial seja executado.

## Comandos oficiais

Sincronização manual do banco:

```bash
php tools/db_sync.php --env=development
php tools/db_sync.php --env=homolog
php tools/db_sync.php --env=production
```

Preflight obrigatório de deploy:

```bash
php tools/deploy_preflight.php --env=development
php tools/deploy_preflight.php --env=homolog
php tools/deploy_preflight.php --env=production
```

Suíte de testes:

```bash
php tests/run.php
```

## Fluxo obrigatório por ambiente

### Desenvolvimento

1. Atualize o código.
2. Execute `php tools/db_sync.php --env=development`.
3. Execute `php tests/run.php`.
4. Somente depois acesse `/login` ou qualquer rota do sistema.

### Homologação

1. Publique o código.
2. Execute `php tools/deploy_preflight.php --env=homolog`.
3. Libere o ambiente para validação funcional somente se o preflight concluir sem falhas.

### Produção

1. Publique o código.
2. Execute `php tools/deploy_preflight.php --env=production`.
3. Valide logs e resultado do preflight.
4. Disponibilize o sistema para os usuários somente após sincronização e validação concluídas.

## Validação automática na inicialização

- A aplicação verifica automaticamente a integridade da estrutura do banco durante a inicialização.
- Se houver tabelas, colunas ou enums obrigatórios ausentes, a aplicação é bloqueada.
- O comportamento é controlado pela flag `DB_REQUIRE_SYNC_BEFORE_RUN=true`.

## Logs

Os eventos do ciclo de vida do banco são registrados em:

- `storage/logs/db-lifecycle.log`
- `storage/logs/app.log`

Esses logs cobrem:

- falhas de validação estrutural na inicialização;
- falhas de sincronização;
- falhas de preflight de deploy;
- erros de statements SQL durante schema ou upgrade.

## Estrutura de banco

- `database/schema.sql`: instalação base e estrutura completa de referência.
- `database/upgrade.sql`: ajustes incrementais e idempotentes para atualização.

## Testes estruturais

O arquivo `tests/database_structure.php` valida:

- leitura e parsing seguro de `schema.sql` e `upgrade.sql`;
- presença das tabelas obrigatórias esperadas pelo código;
- compatibilidade da estrutura do banco conectado com a versão atual da aplicação.
