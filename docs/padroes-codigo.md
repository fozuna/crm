# Padrões de Código

## Base do projeto

O projeto segue uma base artesanal em PHP puro. Não há framework nem Composer no estado atual, então os padrões internos são parte essencial da manutenção.

## Convenções observadas

- `declare(strict_types=1);` nos arquivos PHP principais;
- classes em PascalCase;
- arquivos com uma classe principal por arquivo;
- uso frequente de classes `final`;
- tipagem explícita em métodos e propriedades.

## Padrão arquitetural

### Controllers

- recebem `Request`;
- validam fluxo HTTP básico;
- delegam para services/repositories;
- retornam views, redirects, arquivos ou JSON.

### Services

- concentram regras de negócio;
- executam transações;
- compõem repositórios;
- validam invariantes do domínio;
- geram artefatos como PDF e XLSX.

### Repositories

- concentram SQL;
- usam `DB::pdo()`;
- trabalham com `PDO::prepare`, `bindValue` e `execute`;
- retornam arrays associativos.

### Views

- ficam em `resources/views`;
- são PHP server-side;
- devem usar `View::e()` para escape de saída;
- não devem concentrar regra de negócio complexa.

## Banco e SQL

- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`;
- `PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC`;
- `PDO::ATTR_EMULATE_PREPARES => false`;
- prepared statements são o padrão esperado;
- alterações estruturais exigem atualização de `schema.sql` e `upgrade.sql`.

## Validações

Padrões observados:

- validação explícita de entradas em controllers e validators;
- validação de domínio em services;
- validação de upload com checagem de origem, tamanho e MIME;
- validação de CSRF em mutações.

## Tratamento de erros

- bootstrap centraliza o tratamento global;
- warnings/notices relevantes podem virar exceção;
- fatal errors são registrados no shutdown handler;
- exceções de banco e estrutura têm mensagens operacionais específicas;
- APIs retornam JSON com `ok`, `error` ou `message`.

## Logs

Padrões atuais:

- `app.log` para exceções globais;
- `db-lifecycle.log` para ciclo de vida do banco;
- `runtime-events.ndjson` para eventos estruturados de runtime;
- logs específicos por domínio, como financeiro.

## Auditoria

O sistema usa duas estratégias:

- tabelas de auditoria/histórico por domínio;
- logs de runtime/infraestrutura em `storage/logs`.

Ao criar funcionalidade nova, verificar se o evento exige:

- trilha em banco;
- log técnico;
- ambos.

## Segurança no código

- sessão com cookie `httponly`;
- CSRF obrigatório em mutações;
- RBAC por middleware;
- tokens públicos assinados para aprovação externa;
- criptografia com OpenSSL para dados específicos;
- sanitização e bloqueios básicos de SVG e upload.

## Padrões de resposta

### HTML

- `View::render()` com layout padrão;
- `Response::redirect()` para fluxo pós-ação.

### JSON

- `Response::json()` com payloads simples;
- convenção de sucesso `ok: true`;
- convenção de erro `ok: false`.

## Padrões de testes

- suíte própria em PHP puro;
- assertions simples acumulando falhas;
- módulos cobertos por arquivos específicos em `tests/`;
- fakes e doubles manuais em vez de framework de mocking.

## Padrões de nomenclatura

- controllers: `XController.php`, `XApiController.php`;
- services: `XService.php`, `XValidator.php`, `XPdfGenerator.php`;
- repositories: `XRepository.php`;
- views: por módulo e caso de uso;
- scripts operacionais: `tools/<acao>.php`.

## O que preservar em novas implementações

- separar HTTP, regra e persistência;
- não espalhar SQL em controllers;
- não introduzir helpers globais descontrolados;
- não depender de assets/build externos obrigatórios;
- não criar mutação sem CSRF;
- não criar rota sensível sem middleware apropriado;
- não quebrar o fluxo de sincronização obrigatória do banco.
