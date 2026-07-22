# Segurança

## Visão geral

A segurança atual do projeto é baseada principalmente em:

- sessão autenticada;
- RBAC por middleware;
- CSRF em mutações;
- prepared statements;
- validação de upload;
- criptografia pontual com OpenSSL;
- logging de falhas e eventos críticos.

## Autenticação

- login por e-mail e senha;
- validação com `password_verify`;
- armazenamento de senha em `password_hash`;
- sessão regenerada após autenticação e logout.

## Sessões e cookies

O projeto usa sessão PHP com:

- nome do cookie `traxter_session`;
- `httponly=true`;
- `SameSite=Lax`;
- `secure` condicionado a HTTPS.

## Autorização

Papéis observados:

- `admin`
- `pm`
- `finance`
- `auditor`

Aplicação:

- `AuthMiddleware` protege recursos autenticados;
- `AdminMiddleware` protege rotas de administração;
- `RoleMiddleware` protege áreas por papel.

## CSRF

- mutações `POST`, `PUT`, `PATCH` e `DELETE` exigem token CSRF;
- o token pode ser enviado por:
  - campo `_csrf`;
  - header `X-CSRF-Token`.

Se o token for inválido:

- o sistema responde com `419`;
- a mensagem padrão é `CSRF inválido.`

## SQL Injection

Mitigações observadas:

- `PDO` com prepared statements;
- `ATTR_EMULATE_PREPARES=false`;
- consultas encapsuladas em repositories.

Não foi encontrado ORM nem concatenação generalizada como padrão aceito.

## XSS

Mitigações observadas:

- escape HTML por `View::e()`;
- uso de renderização server-side controlada;
- sanitização em partes de rich text e upload SVG.

Limitações:

- como o sistema renderiza HTML em views PHP, qualquer saída sem `View::e()` é potencialmente sensível;
- não há camada dedicada de sanitização global para todas as entradas HTML.

## Uploads

### Company profile e branding

`BrandAssetProcessor` valida:

- origem do upload;
- tamanho máximo;
- MIME/extensão;
- SVG com bloqueio básico de `<script>`, `onload=` e `onerror=`.

### Anexos de OS e comprovantes

Há validação por:

- `is_uploaded_file`;
- `finfo` ou `mime_content_type`;
- geração de nomes aleatórios com `random_bytes`.

## Tokens e links públicos

O fluxo público de aprovação de OS usa:

- `public_id`;
- token assinado via HMAC;
- persistência do hash do token, não do token puro;
- TTL configurável;
- exigência opcional de HTTPS (`APPROVAL_REQUIRE_HTTPS`).

## Criptografia

### `App\Services\Crypto`

- usa `aes-256-gcm`;
- deriva chave de `APP_KEY`;
- gera IV aleatório com `random_bytes`;
- suporta criptografia de texto e JSON.

### Reset de banco e rotinas sensíveis

- há uso adicional de OpenSSL em fluxos de reset e proteção operacional.

## Logs e resposta a erro

### Logs

- `storage/logs/app.log`
- `storage/logs/db-lifecycle.log`
- `storage/logs/runtime-events.ndjson`

### Tratamento global

O bootstrap:

- captura exceções;
- registra contexto mínimo;
- tenta retornar mensagens operacionais mais úteis em alguns cenários de banco e estrutura.

## Banco e segurança operacional

O banco é tratado como parte da segurança operacional:

- a aplicação valida estrutura mínima antes de subir;
- deploy sem sincronização é bloqueado;
- `deploy_preflight` é obrigatório em homologação e produção.

## APIs

As APIs `/api/*` não são stateless:

- dependem de sessão autenticada;
- dependem de CSRF;
- não usam JWT nem bearer token no estado atual.

Isso reduz superfície pública, mas vincula o consumo ao mesmo contexto da aplicação web.

## Superfícies sensíveis do sistema

- `/maintenance/*`
- `/api/company-profile*`
- `/api/financial/*`
- `/api/finance/installments/*`
- `/os/aprovacao/{publicId}`
- uploads em módulos de branding, cliente, financeiro e OS

## Controles já existentes

- RBAC por middleware;
- CSRF;
- prepared statements;
- sessão segura;
- triggers de imutabilidade em eventos/notificações da aprovação de OS;
- auditoria por domínio;
- logs estruturados em falhas críticas.

## Lacunas e riscos do estado atual

- ausência de proteção centralizada contra força bruta no login;
- ausência de camada global de sanitização rica para todo HTML;
- APIs internas dependentes de sessão, o que exige atenção em chamadas assíncronas;
- inspeção estrutural do banco não cobre tudo o que o banco realmente possui.

## Regras obrigatórias para mudanças futuras

- não expor endpoint novo sem revisar autenticação e autorização;
- não criar mutação sem CSRF;
- não aceitar upload novo sem validar origem, tipo e tamanho;
- não persistir segredo ou token sensível em texto puro quando houver alternativa viável;
- não remover logs ou auditoria de fluxos críticos.
