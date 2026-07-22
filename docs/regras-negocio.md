# Regras de Negócio

## Visão geral

Este documento resume regras observáveis no código atual. Ele não descreve requisitos hipotéticos nem comportamento desejado fora do que foi encontrado em controllers, services, repositories, rotas e testes.

## Autenticação e sessão

- login exige e-mail válido e senha não vazia;
- a senha é validada com `password_verify`;
- o usuário autenticado recebe sessão com `user_id`, `user_name`, `is_admin` e `user_role`;
- se `is_admin=1`, o papel efetivo passa a ser `admin`;
- se não houver papel definido, o fallback é `pm`.

## Papéis e permissões

Papéis observados:

- `admin`
- `pm`
- `finance`
- `auditor`

Combinações aplicadas nas rotas:

- `pm`: `admin` ou `pm`;
- `finance`: `admin` ou `finance`;
- `auditor`: `admin`, `auditor`, `finance` ou `pm`;
- `financeView`: `admin`, `finance` ou `auditor`.

## Instalação e liberação do sistema

- o sistema só é considerado instalado quando existe configuração mínima e a tabela `users` contém ao menos um registro;
- se faltar configuração mínima e a rota não for `/install`, a aplicação redireciona para o instalador;
- se o banco não estiver sincronizado e `DB_REQUIRE_SYNC_BEFORE_RUN=true`, a aplicação bloqueia o acesso.

## Leads

- o lead possui pipeline com histórico de movimentação;
- a conversão de lead em cliente depende do estágio/regra validada pelo `LeadService`;
- a conversão migra e complementa dados relevantes do funil;
- há prefill de proposta a partir do lead, reaproveitando dados cadastrais e contexto comercial.

## Clientes

- clientes mantêm interações registradas;
- o módulo também suporta logo/identidade específica por cliente;
- clientes podem ter origem em leads convertidos.

## Propostas comerciais

- propostas possuem itens, marcos, documentos e snapshots de pagamento;
- o sistema gera PDF de proposta;
- há fluxo de atualização de status;
- a proposta pode ser convertida em projeto;
- há geração de contrato a partir de proposta.

## Contratos

- contratos usam templates;
- contratos preservam snapshots da proposta/template;
- há versionamento de contrato em PDF;
- o fluxo contempla geração, envio para assinatura, marcação como assinado e vigência;
- notificações de contrato são persistidas.

## Projetos

- projetos podem ser gerados automaticamente a partir de proposta aprovada;
- o fluxo automático cria marcos, tarefas, parcelas e eventos;
- o projeto mantém progresso percentual e histórico de status;
- tarefas e marcos são manipulados por rotas específicas.

## Financeiro de projetos

- parcelas possuem fluxo de pagamento, cancelamento, reabertura e aprovação/rejeição de cancelamento;
- pagamentos e cancelamentos críticos usam transação e auditoria;
- o código trabalha com status de parcela que incluem casos como atraso.

## Financeiro corporativo

- contas a receber têm status, valor original, valor recebido e saldo restante;
- o módulo suporta:
  - criação;
  - edição;
  - exclusão;
  - duplicação;
  - renegociação;
  - baixa;
  - estorno;
  - emissão de recibo;
  - relatórios e dashboard.

## Serviços

- existe catálogo de serviços utilizado por propostas e ordens de serviço;
- o acesso ao catálogo é restrito aos perfis de operação/comercial (`pm` e `admin`, conforme rota).

## Ordens de serviço

- a OS pode ser faturável ou não faturável;
- há histórico interno, anexos e PDF próprio;
- a OS suporta atualização de status, cancelamento e exclusão;
- existe fluxo de geração de aprovação digital externa;
- o módulo calcula e registra dados financeiros relacionados à execução.

## Aprovação pública de ordem de serviço

- o link público usa `public_id` e token assinado;
- a persistência guarda hash do token, não o token em texto puro;
- o link possui prazo de expiração;
- a decisão do cliente pode resultar em aprovação ou solicitação de ajustes;
- a decisão gera histórico, auditoria, eventos e comprovante PDF;
- eventos e notificações da aprovação possuem trilha imutável reforçada por trigger.

## Branding e identidade institucional

- o sistema possui dois eixos relacionados:
  - branding legado de propostas;
  - company profile mais amplo, usado para identidade empresarial e ativos públicos.
- logos e ativos são usados em layout, landing page e documentos.

## Manual interno

- o sistema expõe um manual interno autenticado em `/manual`;
- isso indica uma preocupação do projeto com adoção e operação assistida pelos usuários finais.

## Auditoria

- há módulo específico de auditoria;
- alterações críticas também geram registros em tabelas especializadas por domínio;
- financeiro e company profile têm trilhas de auditoria próprias.

## Banco de dados como regra de negócio operacional

Esta é uma regra funcional do sistema, não apenas técnica:

- nenhuma alteração dependente de banco pode ser disponibilizada sem sincronização prévia;
- o deploy só é considerado válido após `db_sync` ou `deploy_preflight`;
- a aplicação pode bloquear a liberação do ambiente se detectar estrutura incompatível.

## Restrições funcionais relevantes

- APIs internas dependem de sessão autenticada e CSRF;
- rotas de manutenção de banco são restritas a admin;
- o fluxo público de aprovação de OS é a exceção principal de acesso sem autenticação de sessão.
