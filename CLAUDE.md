# CLAUDE.md

## Visão geral

O `gestor/` é o núcleo do TRAXTER CRM, um sistema SaaS corporativo em PHP puro voltado à gestão comercial, operacional e financeira. O sistema atende principalmente operações internas que precisam centralizar:

- autenticação e controle de acesso por papéis;
- gestão de clientes e interações;
- funil de leads;
- propostas comerciais e geração de PDF;
- contratos e templates;
- projetos, tarefas, marcos e eventos;
- financeiro de projetos e financeiro corporativo;
- ordens de serviço, anexos e aprovação pública externa;
- auditoria e rastreabilidade operacional.

### Objetivo

Fornecer uma aplicação monolítica modular, sem dependência de framework externo, capaz de operar em hospedagem compartilhada ou ambiente PHP tradicional com MariaDB/MySQL.

### Público-alvo

- times administrativos;
- equipes comerciais;
- gestão de projetos;
- financeiro;
- auditoria interna;
- operação que precisa de controle documental e histórico.

### Problema que resolve

O sistema reduz dispersão operacional entre planilhas, documentos avulsos e controles paralelos. Ele centraliza dados de clientes, propostas, contratos, projetos, financeiro e ordens de serviço em um único fluxo autenticado com auditoria.

## Stack utilizada

### Linguagens

- PHP 8.1+ inferido pelo uso de `readonly`, `match`, `str_starts_with`, tipagem estrita e promoted properties;
- SQL compatível com MariaDB/MySQL;
- JavaScript vanilla;
- HTML renderizado no servidor;
- CSS utilitário via Tailwind CDN.

### Frameworks

- Não há framework PHP.
- Não há ORM.
- Não há Composer.
- Não há build frontend com Node no estado atual.

### Bibliotecas e componentes

- Tailwind CSS via CDN em `resources/views/partials/head.php`;
- camada própria de bootstrap, router, request, response, session, csrf e renderização;
- geradores internos de PDF e XLSX em `app/Services`.

### Banco de dados

- MariaDB/MySQL com fonte oficial em `database/schema.sql` e `database/upgrade.sql`.

### Serviços externos

- CDN do Tailwind;
- envio de e-mail via infraestrutura configurada pela aplicação;
- nenhum provedor SaaS externo obrigatório foi encontrado no código principal.

### APIs

- APIs HTTP internas em `/api/*`, protegidas por sessão e CSRF;
- um fluxo público externo para aprovação de ordem de serviço em `/os/aprovacao/{publicId}`.

### Dependências técnicas observadas

- `PDO` com `pdo_mysql`;
- `openssl` para criptografia e rotinas de reset;
- `fileinfo` ou `mime_content_type` para uploads;
- `GD` para algumas operações de imagem em PDF/logo;
- `Imagick` opcional, usado quando disponível para conversão de imagem em PDF.

## Arquitetura

### Estilo arquitetural

- monólito modular;
- MVC leve com roteador próprio;
- controllers finos;
- services com regras de negócio;
- repositories com SQL e `PDO`;
- views PHP server-side.

### Fluxo de requisição

1. `index.php` carrega `app/bootstrap.php`.
2. O bootstrap registra autoload simples, configurações, sessão, headers e tratamento global de erros.
3. `Application::run()` cria o `Request`.
4. Antes do roteamento, o sistema executa o guard estrutural do banco, quando `DB_REQUIRE_SYNC_BEFORE_RUN=true`.
5. As rotas são carregadas de `config/routes.php`.
6. O `Router` resolve a rota, executa middlewares e chama o controller.
7. O controller orquestra services/repositories e responde com HTML, redirect, arquivo ou JSON.

### Estrutura MVC

- `app/Controllers`: coordenação HTTP;
- `app/Services`: regra de negócio, automações, validações, PDFs e utilitários transversais;
- `app/Repositories`: SQL, consultas e persistência via `PDO`;
- `resources/views`: renderização server-side;
- `config/routes.php`: mapa central de rotas e middlewares.

### Organização das pastas

- `app/Core`: infraestrutura mínima da aplicação;
- `app/Middleware`: autenticação, admin, roles e CSRF;
- `app/Contracts`: contratos usados para desacoplamento e testes;
- `app/DTOs`: objetos simples para módulo financeiro;
- `database`: estrutura e upgrade do banco;
- `docs`: documentação funcional/técnica complementar;
- `resources/views`: interface;
- `tests`: suíte de regressão em PHP puro;
- `tools`: scripts operacionais de sincronização, preflight e manutenção;
- `storage`: logs, uploads, PDFs, jobs e cache.

## Padrões obrigatórios

### Como o código deve ser escrito

- declarar `strict_types=1`;
- preferir classes `final` quando não há herança planejada;
- manter controllers enxutos;
- concentrar regra de negócio em services;
- concentrar SQL em repositories;
- usar `PDO` com `ATTR_ERRMODE => EXCEPTION` e `ATTR_EMULATE_PREPARES => false`;
- escapar saída HTML com `View::e()`;
- responder JSON com `Response::json()`.

### Convenções

- nomes de classes em PascalCase;
- arquivos PHP com um símbolo principal por arquivo;
- rotas agrupadas por módulo em `config/routes.php`;
- views agrupadas por domínio em `resources/views/<modulo>`;
- logs em `storage/logs`.

### Organização das classes

- controllers recebem `Request` e delegam;
- services validam regras, executam transações e chamam repositories;
- repositories encapsulam SQL;
- utilitários transversais ficam em `app/Services` ou `app/Core`.

### Tratamento de erros

- o bootstrap registra `set_error_handler`, `register_shutdown_function` e `set_exception_handler`;
- erros críticos vão para `storage/logs/app.log`;
- eventos de runtime relevantes também podem ir para `storage/logs/runtime-events.ndjson`;
- falhas estruturais de banco geram bloqueio explícito da aplicação.

### Validações

- entradas HTTP são validadas por controllers/services/validators;
- uploads são validados por tipo, tamanho e origem (`is_uploaded_file`);
- CSRF é obrigatório em mutações;
- algumas regras estruturais do banco são validadas em runtime.

### Logs e auditoria

- logs de aplicação: `storage/logs/app.log`;
- logs do ciclo de vida do banco: `storage/logs/db-lifecycle.log`;
- logs específicos de runtime: `storage/logs/runtime-events.ndjson`;
- vários módulos registram histórico ou auditoria em tabelas dedicadas.

### Boas práticas observadas

- prepared statements;
- transações em fluxos críticos;
- RBAC por middleware;
- sanitização básica de uploads e rich text;
- geração de PDFs e documentos dentro do backend;
- paridade obrigatória entre `schema.sql` e `upgrade.sql`.

## Regras que NUNCA devem ser quebradas

- Nunca disponibilizar o sistema sem sincronizar o banco antes.
- Nunca alterar `database/schema.sql` sem refletir a mudança em `database/upgrade.sql`.
- Nunca contornar o guard estrutural do banco em produção.
- Nunca introduzir dependência de framework sem reavaliar a arquitetura inteira.
- Nunca mover regra de negócio relevante para views.
- Nunca substituir prepared statements por concatenação SQL.
- Nunca expor rotas de manutenção sem autenticação de admin.
- Nunca transformar as APIs internas em endpoints públicos sem revisar autenticação, autorização e CSRF.

### Por que essas regras existem

- o projeto depende de deploy manual controlado;
- a aplicação bloqueia quando o banco diverge do código;
- a arquitetura atual privilegia previsibilidade operacional e compatibilidade com hospedagem simples;
- vários fluxos críticos dependem de auditoria e integridade transacional.

## Fluxos críticos

### Login e autenticação

- formulário em `/login`;
- autenticação por e-mail e senha com `password_verify`;
- sessão grava `user_id`, `user_name`, `is_admin` e `user_role`;
- logout exige CSRF.

### Permissões

- `admin`, `pm`, `finance` e `auditor`;
- papéis são aplicados por middleware, não por checagem ad hoc nas views.

### Cadastros

- clientes e interações;
- leads e histórico de estágio;
- propostas, documentos e itens;
- contratos e templates;
- serviços e ordens de serviço;
- contas a receber, pagamentos e recibos.

### Rotinas e automações

- conversão de lead em cliente;
- conversão de proposta em projeto;
- geração de contrato a partir de proposta;
- geração de PDF para propostas, contratos, ordens de serviço e recibos;
- aprovação externa de ordem de serviço por link assinado.

### Importações e exportações

- exportações PDF/Excel/CSV em relatórios e documentos;
- não há rotina geral de importação em lote documentada no código atual.

### Integrações

- envio de e-mail;
- aprovação pública de OS por token;
- não há integração pública REST com autenticação por JWT no estado atual.

## Banco de dados

### Estrutura geral

O banco cobre:

- usuários;
- clientes e interações;
- leads e histórico de pipeline;
- propostas, itens, marcos, documentos e branding;
- contratos, versões e notificações;
- projetos, tarefas, marcos, eventos e histórico de status;
- financeiro de projetos;
- financeiro corporativo;
- company profile;
- ordens de serviço e aprovação externa.

### Integridade

- FKs amplamente usadas, especialmente em propostas, contratos, projetos, financeiro e OS;
- triggers de imutabilidade em eventos/notificações de aprovação de OS;
- inspeção estrutural em runtime para tabelas, colunas e enums críticos.

### Riscos conhecidos do estado atual

- há pontos de paridade estrutural ainda sensíveis entre `schema.sql`, `upgrade.sql` e o que o código espera;
- a inspeção estrutural atual não cobre todas as FKs, triggers e índices;
- existem colunas de referência sem FK em alguns módulos.

## Deploy

### Desenvolvimento

1. Atualizar o código.
2. Executar `php tools/db_sync.php --env=development`.
3. Executar `php tests/run.php`.
4. Só então acessar o sistema.

### Homologação e produção

1. Publicar código.
2. Executar `php tools/deploy_preflight.php --env=<ambiente>`.
3. Validar logs.
4. Liberar acesso somente após banco e testes estruturais concluídos.

### Variáveis essenciais

- `APP_ENV`
- `APP_URL`
- `APP_BASE_PATH`
- `APP_KEY`
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_REQUIRE_SYNC_BEFORE_RUN`

## Como desenvolver novas funcionalidades

1. Ler `config/routes.php`, o controller do módulo, o service principal e o repository correspondente.
2. Identificar impacto em banco antes de tocar no código.
3. Se houver alteração estrutural:
   - atualizar `schema.sql`;
   - atualizar `upgrade.sql`;
   - revisar `DbUpgradeRunner`, se necessário.
4. Seguir o padrão controller -> service -> repository -> view.
5. Manter CSRF, RBAC e auditoria.
6. Atualizar a documentação afetada.
7. Rodar `php tests/run.php`.

## Checklist para qualquer alteração

Antes de alterar qualquer código, verificar obrigatoriamente:

- impacto em segurança;
- impacto em performance;
- impacto em compatibilidade;
- impacto em banco;
- impacto em APIs;
- impacto em permissões;
- impacto em auditoria.

### Perguntas mínimas

- A rota nova exige autenticação?
- A mutação nova exige CSRF?
- O papel necessário já existe?
- Há logging/auditoria suficiente?
- A alteração exige tabela/coluna/índice novo?
- O deploy continua compatível com hospedagem compartilhada?
- O fluxo quebra algo em PDF, relatórios ou exportações?

## Auditoria técnica do CRM

O diagnóstico técnico e funcional vigente está documentado em `CRM_AUDIT.md`.

Antes de implementar correções nos módulos Financeiro, Relatórios, Serviços Avulsos ou Ordens de Serviço, consulte esse documento e preserve a rastreabilidade entre problema, evidência, alteração e teste de regressão.
