# Deploy Hostinger

## Estrutura

Este projeto foi preparado para hospedagem compartilhada com PHP puro, sem Composer, sem instalador obrigatorio e sem comandos de shell no servidor.

Arquivos web:

- `index.php`
- `.htaccess`
- `dev-router.php` (somente local)

Diretorios internos protegidos por `.htaccess`:

- `app/`
- `config/`
- `database/`
- `docs/`
- `resources/`
- `storage/`
- `tools/` (somente worker interno de manutencao)

## Publicacao

1. Envie o conteudo da pasta `gestor/` para o diretorio desejado da Hostinger.
2. Edite somente `config/config.php`.
3. Ajuste:
   - `APP_URL`
   - `APP_BASE_PATH`
   - `APP_KEY`
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
4. Execute obrigatoriamente o preflight oficial antes de liberar o sistema:
   - `php tools/deploy_preflight.php --env=production`
5. Garanta permissao de escrita em `storage/`.

## Valores recomendados

- Deploy na raiz do dominio:
  - `APP_URL = https://seudominio.com`
  - `APP_BASE_PATH = ''`

- Deploy em subpasta, ex.: `https://seudominio.com/gestor`:
  - `APP_URL = https://seudominio.com/gestor`
  - `APP_BASE_PATH = /gestor`

## Comportamento

- A aplicacao cria automaticamente diretorios de runtime em `storage/`.
- O arquivo `.env` e opcional. Em producao, `config/config.php` ja e suficiente.
- O instalador permanece disponivel apenas como contingencia, mas o fluxo normal de deploy nao depende dele.
- O sistema valida a estrutura do banco na inicializacao e bloqueia o uso se houver divergencia estrutural.
- Scripts de diagnostico/teste nao acompanham o pacote operacional de producao.

## Validacao rapida

Depois do upload e da edicao do `config/config.php`:

1. Execute `php tools/deploy_preflight.php --env=production`.
2. Abra `/login`.
3. Verifique se a aplicacao responde sem erro 500.
4. Confirme acesso ao banco e renderizacao da tela inicial.
