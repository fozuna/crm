# Dependências

## Visão geral

O projeto tem uma base deliberadamente enxuta. No estado atual não há `composer.json`, `package.json`, Dockerfile ou pipeline CI versionados. A maioria das dependências é:

- nativa do PHP;
- interna ao próprio repositório;
- carregada via CDN apenas no frontend.

## Dependências externas observadas

### Frontend

- Tailwind CSS via CDN em `resources/views/partials/head.php`.

Não foram encontrados:

- React;
- Vue;
- Alpine;
- jQuery;
- build pipeline frontend.

### Serviços externos

- infraestrutura de e-mail, dependente da configuração do ambiente;
- nenhuma integração obrigatória com provedor externo foi encontrada como requisito central do runtime web.

## Dependências nativas do PHP

### Obrigatórias ou fortemente inferidas

- `PDO`
- `pdo_mysql`
- `openssl`
- `json`
- `session`
- `mbstring` recomendável para conteúdo em português
- `fileinfo` ou `mime_content_type`

### Usadas em tratamento de imagem/documentos

- `gd` para rotinas com `imagecreatefromstring` e leitura de dimensões;
- `getimagesize`.

### Opcionais

- `Imagick` para algumas conversões de imagem em PDF, com fallback quando ausente.

## Dependências internas por camada

### Core

- `Application`
- `Router`
- `Request`
- `Response`
- `Config`
- `DB`
- `Session`
- `Csrf`
- `View`
- `UI`

### Services transversais

- `ProfessionalPdf`
- `SimplePdf`
- `XlsxBuilder`
- `Money`
- `Crypto`
- `SqlScriptParser`
- `DbSyncRunner`
- `DbUpgradeRunner`
- `DbStartupGuard`

### Services de domínio

- `LeadService`
- `ProposalService`
- `ContractService`
- `ProjectAutomationService`
- `FinanceService`
- `FinancialReceivableService`
- `ServiceOrderService`
- `ServiceOrderApprovalService`

### Repositories de domínio

O sistema depende fortemente de repositories próprios para encapsular SQL. Exemplos centrais:

- `UserRepository`
- `ClientRepository`
- `LeadRepository`
- `ProposalRepository`
- `ContractRepository`
- `ProjectRepository`
- `FinanceInstallmentRepository`
- `FinancialReceivableRepository`
- `ServiceOrderRepository`

## Dependências de banco

- `database/schema.sql`
- `database/upgrade.sql`

Esses dois arquivos são dependências operacionais obrigatórias do deploy. O código assume a presença e consistência deles.

## Dependências de documentação operacional

Para operação segura, o repositório depende de leitura dos arquivos:

- `README.md`
- `README-HOSTINGER.md`
- `CLAUDE.md`
- `docs/deploy.md`
- `docs/banco.md`

## Dependências ausentes por escolha arquitetural

No estado atual, o projeto explicitamente não depende de:

- framework PHP;
- ORM;
- Composer;
- PHPUnit;
- Node/NPM;
- bundler frontend;
- Docker obrigatório.

## Impacto dessa escolha

### Vantagens

- deploy simples;
- compatibilidade com hospedagem compartilhada;
- menor superfície de dependências externas;
- controle total sobre bootstrap e infraestrutura.

### Custos

- manutenção manual de autoload e infraestrutura;
- ausência de ecossistema de pacotes padronizado;
- mais responsabilidade sobre testes, build e observabilidade.
