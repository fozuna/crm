# Troubleshooting

## Erro de estrutura do banco desatualizada

### Sintoma

- mensagem informando que a estrutura do banco está desatualizada;
- bloqueio do acesso logo na inicialização.

### Causa provável

- `schema.sql`, `upgrade.sql` e banco atual estão divergentes;
- ambiente foi publicado sem sincronização prévia.

### Ação

```bash
php tools/db_sync.php --env=development
php tools/deploy_preflight.php --env=homolog
php tools/deploy_preflight.php --env=production
```

### Onde olhar

- `storage/logs/db-lifecycle.log`
- `storage/logs/app.log`

## Erro 500 genérico

### Onde olhar

- `storage/logs/app.log`
- `storage/logs/runtime-events.ndjson`

### Pontos comuns

- configuração ausente;
- permissão insuficiente em `storage/`;
- falha de conexão com banco;
- divergência estrutural do banco;
- deploy parcial de arquivos.

## Falha de conexão com banco

### Sintoma

- mensagens como `Falha ao conectar no banco.`

### Verificar

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- existência do banco

## Rotas redirecionando para `/install`

### Causa provável

- falta de configuração mínima;
- tabela `users` inexistente;
- banco vazio ou instalação incompleta.

### Verificar

- `APP_URL`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- existência da tabela `users`
- existência de ao menos um usuário

## CSRF inválido

### Sintoma

- resposta `419` com texto `CSRF inválido.`

### Verificar

- presença do campo `_csrf` em formulários;
- envio do header `X-CSRF-Token` em AJAX;
- sessão ativa;
- domínio/base path corretos.

## Sem permissão

### Sintoma

- `403` em rotas web/API.

### Verificar

- papel do usuário em sessão;
- middleware aplicado à rota;
- fallback de papel (`pm`) versus `is_admin`.

## Não autenticado

### Sintoma

- `401` nas APIs internas;
- redirecionamento para login em rotas web.

### Verificar

- sessão ativa;
- cookie de sessão;
- se a chamada AJAX está no mesmo contexto autenticado do navegador.

## Upload inválido

### Verificar

- tamanho do arquivo;
- MIME/extensão;
- origem do upload;
- permissões em `storage/uploads`.

### Casos específicos

- SVG com `<script>`, `onload=` ou `onerror=` é rejeitado;
- favicon e branding têm limites e formatos próprios.

## PDF não gerado

### Verificar

- permissão de escrita em `storage/pdfs/*`;
- disponibilidade de arquivos de logo/imagem;
- integridade dos dados usados para geração;
- presença opcional de `Imagick` quando houver fluxo que se beneficie disso.

## Logs vazios ou ausentes

### Verificar

- permissão de escrita em `storage/logs`;
- existência do diretório;
- se o servidor está usando o diretório correto da aplicação.

## Problemas em deploy compartilhado

### Checklist

- `index.php` publicado;
- `.htaccess` ativo;
- `storage/` gravável;
- `config/config.php` ou `.env` corretos;
- `deploy_preflight` executado;
- `/login` abre sem erro.

## Testes falhando localmente por banco

### Observação

A suíte principal inclui validação estrutural runtime. Em ambiente local sem banco configurado ou acessível, esse ponto pode falhar mesmo se o restante da suíte estiver íntegro.

### Verificar

- conexão local com banco;
- se o banco foi sincronizado;
- se `APP_ENV` e variáveis DB estão corretos.
