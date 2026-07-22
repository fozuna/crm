# Banco de Dados

## Fontes oficiais

As fontes oficiais da estrutura são:

- `database/schema.sql`
- `database/upgrade.sql`

Regra permanente do projeto:

- qualquer alteração estrutural deve manter paridade entre os dois arquivos;
- o sistema não deve ser liberado sem sincronização prévia do banco;
- a aplicação pode bloquear o boot quando detectar estrutura incompatível.

## Tecnologia

- banco compatível com MariaDB/MySQL;
- acesso via `PDO`;
- prepared statements obrigatórios no código;
- `utf8mb4` como charset padrão.

## Governança estrutural

### Instalação e sincronização

- `schema.sql`: estrutura base completa;
- `upgrade.sql`: ajustes incrementais e idempotentes;
- `tools/db_sync.php`: sincronização sequencial;
- `tools/deploy_preflight.php`: sincronização e validação antes do deploy.

### Validação em runtime

O projeto possui verificação estrutural no bootstrap via:

- `App\Services\DbStartupGuard`
- `App\Services\DbUpgradeRunner`

Atualmente, a inspeção estrutural cobre:

- tabelas obrigatórias;
- colunas obrigatórias;
- enums críticos específicos.

Ela não cobre integralmente:

- todas as foreign keys;
- todos os índices;
- todas as triggers;
- todas as paridades finas de enum.

## Dicionário de dados

## Núcleo de usuários e clientes

### `users`

- finalidade: autenticação e controle de acesso;
- colunas-chave: `id`, `name`, `email`, `password_hash`, `is_admin`, `role`, `created_at`;
- observações: `role` convive com `is_admin`; admin força papel `admin` em runtime.

### `clients`

- finalidade: cadastro principal de clientes;
- colunas-chave: `id`, `name`, `document_number`, `email`, `phone`, `company_name`, `source_lead_id`, `created_at`, `updated_at`;
- observações: há vínculo lógico com leads convertidos.

### `client_interactions`

- finalidade: histórico de interações com cliente;
- chave relacional: `client_id -> clients.id`;
- colunas-chave: `type`, `description`, `created_by`, `created_at`.

## Funil comercial

### `leads`

- finalidade: cadastro de oportunidades comerciais;
- colunas-chave: `id`, `name`, `company_name`, `email`, `phone`, `stage`, `source`, `converted_client_id`, `created_at`, `updated_at`;
- observações: suporta conversão para cliente.

### `lead_interactions`

- finalidade: histórico de interações comerciais do lead;
- chave relacional: `lead_id -> leads.id`.

### `lead_stage_history`

- finalidade: trilha de movimentação do pipeline;
- chave relacional: `lead_id -> leads.id`;
- colunas-chave: `from_stage`, `to_stage`, `changed_by`, `created_at`.

## Comercial, propostas e contratos

### `payment_methods`

- finalidade: catálogo de formas de pagamento;
- colunas-chave: `id`, `name`, `created_at`, `updated_at`;
- observações: o estado atual do projeto ainda indica risco de seed duplicada se o schema base for reaplicado sem controle adicional.

### `services`

- finalidade: catálogo de serviços comercializáveis;
- colunas-chave: `id`, `name`, `description`, `base_price`, `active`, `created_at`, `updated_at`.

### `proposals`

- finalidade: proposta comercial principal;
- chaves relacionais: `client_id -> clients.id`, `payment_method_id -> payment_methods.id`;
- colunas-chave: `status`, `title`, `description`, `subtotal`, `discount_amount`, `total_amount`, `payment_snapshot`, `created_at`, `updated_at`.

### `proposal_items`

- finalidade: itens/linhas comerciais da proposta;
- chaves relacionais: `proposal_id -> proposals.id`, `service_id -> services.id`;
- colunas-chave: `description`, `quantity`, `unit_price`, `total_price`, `sort_order`.

### `proposal_milestones`

- finalidade: marcos/entregáveis da proposta;
- chave relacional: `proposal_id -> proposals.id`.

### `proposal_documents`

- finalidade: documentos anexos da proposta;
- chave relacional: `proposal_id -> proposals.id`.

### `proposal_branding`

- finalidade: branding visual usado nas propostas;
- colunas-chave: `company_name`, `primary_color`, `accent_color`, `font_name`, `logo_path`, `meta_title`, `meta_description`.

### `contract_templates`

- finalidade: templates de contrato;
- colunas-chave: `id`, `name`, `body`, `auto_criteria_json`, `active`, `updated_at`.

### `contracts`

- finalidade: contrato gerado a partir de proposta;
- chaves relacionais: `proposal_id -> proposals.id`, `client_id -> clients.id`, `template_id -> contract_templates.id`;
- colunas-chave: `status`, `number`, `signature_url`, `template_snapshot`, `proposal_snapshot`, `created_at`, `updated_at`.

### `contract_versions`

- finalidade: snapshots/versionamento do contrato;
- chave relacional: `contract_id -> contracts.id`;
- colunas-chave: `version_number`, `body`, `pdf_path`, `created_at`.

### `contract_notifications`

- finalidade: rastreamento de notificações do contrato;
- chave relacional: `contract_id -> contracts.id`;
- colunas-chave: `channel`, `status`, `metadata`, `sent_at`.

## Projetos e financeiro de projetos

### `projects`

- finalidade: projeto decorrente de proposta aprovada;
- chaves relacionais: `proposal_id -> proposals.id`, `client_id -> clients.id`;
- colunas-chave: `title`, `status`, `progress_percent`, `owner_user_id`, `start_date`, `end_date`, `created_at`, `updated_at`.

### `project_tasks`

- finalidade: tarefas do projeto;
- chave relacional: `project_id -> projects.id`;
- colunas-chave: `title`, `status`, `assignee_user_id`, `due_date`, `completed_at`, `sort_order`.

### `project_milestones`

- finalidade: marcos do projeto;
- chave relacional: `project_id -> projects.id`.

### `project_status_history`

- finalidade: histórico de status do projeto;
- chave relacional: `project_id -> projects.id`.

### `project_events`

- finalidade: eventos e log operacional do projeto;
- chave relacional: `project_id -> projects.id`;
- colunas-chave: `type`, `payload_json`, `created_at`.

### `finance_installments`

- finalidade: parcelas vinculadas a propostas/projetos;
- chaves relacionais: `proposal_id -> proposals.id`, `project_id -> projects.id`;
- colunas-chave: `amount`, `paid_amount`, `status`, `due_date`, `payment_date`, `description`;
- observações: o código usa o status `atrasado`, que exige atenção de paridade estrutural.

### `finance_payments`

- finalidade: pagamentos aplicados às parcelas;
- chave relacional: `installment_id -> finance_installments.id`;
- colunas-chave: `amount`, `paid_at`, `method`, `notes`, `created_by`.

### `finance_cancellation_requests`

- finalidade: solicitações de cancelamento de parcela;
- chave relacional: `installment_id -> finance_installments.id`;
- colunas-chave: `status`, `reason`, `requested_by`, `reviewed_by`, `reviewed_at`.

## Financeiro corporativo

### `company_profile`

- finalidade: perfil institucional usado em branding, documentos e dados corporativos;
- colunas-chave: identificação, contatos, identidade visual e caminhos de ativos.

### `company_profile_audit`

- finalidade: auditoria do perfil empresarial;
- chave relacional: `company_profile_id -> company_profile.id`.

### `financial_categories`

- finalidade: categorias financeiras;
- chave relacional: `company_profile_id -> company_profile.id`.

### `financial_cost_centers`

- finalidade: centros de custo;
- chave relacional: `company_profile_id -> company_profile.id`.

### `financial_bank_accounts`

- finalidade: contas bancárias;
- chave relacional: `company_profile_id -> company_profile.id`;
- colunas-chave: `bank_name`, `account_name`, `branch_number`, `account_number`, `pix_key`, `active`.

### `financial_accounts_receivable`

- finalidade: contas a receber corporativas;
- chaves relacionais:
  - `company_profile_id -> company_profile.id`
  - `project_id -> projects.id`
  - `client_id -> clients.id`
  - `contract_id -> contracts.id`
  - `installment_id -> finance_installments.id`
  - `bank_account_id -> financial_bank_accounts.id`
  - `category_id -> financial_categories.id`
  - `cost_center_id -> financial_cost_centers.id`
- colunas-chave: `status`, `issue_date`, `due_date`, `original_amount`, `received_amount`, `remaining_amount`, `payment_method`, `payment_channel`.

### `financial_receipts`

- finalidade: baixas/recebimentos das contas a receber;
- chave relacional: `receivable_id -> financial_accounts_receivable.id`;
- colunas-chave: `amount`, `received_at`, `proof_file_path`, `created_by`.

### `financial_audit_logs`

- finalidade: auditoria financeira corporativa;
- chaves relacionais: `company_profile_id`, `receivable_id`;
- colunas-chave: `action`, `before_data`, `after_data`, `metadata`, `created_at`.

## Ordens de serviço e aprovação pública

### `servicos_avulsos`

- finalidade: ordem de serviço/demanda operacional;
- chaves relacionais:
  - `client_id -> clients.id`
  - `service_id -> services.id`
  - `assigned_user_id -> users.id`
  - `financial_receivable_id -> financial_accounts_receivable.id`
- colunas-chave: `type`, `status`, `billable`, `request_description`, `execution_report`, `base_amount`, `final_amount`, `scheduled_to`, `completed_at`.

### `servicos_avulsos_anexos`

- finalidade: anexos da OS;
- chave relacional: `service_order_id -> servicos_avulsos.id`.

### `servicos_avulsos_historico`

- finalidade: histórico interno da OS;
- chave relacional: `service_order_id -> servicos_avulsos.id`.

### `servicos_avulsos_aprovacoes`

- finalidade: controle da aprovação externa;
- chave relacional: `service_order_id -> servicos_avulsos.id`;
- colunas-chave: `public_id`, `token_hash`, `status`, `expires_at`, `client_name`, `client_email`, `proof_pdf_path`, `used_at`.

### `servicos_avulsos_aprovacao_eventos`

- finalidade: eventos imutáveis da aprovação;
- chave relacional: `approval_id -> servicos_avulsos_aprovacoes.id`.

### `servicos_avulsos_aprovacao_notificacoes`

- finalidade: notificações e outbox da aprovação;
- chave relacional: `approval_id -> servicos_avulsos_aprovacoes.id`.

## Relacionamentos críticos

- lead -> cliente: via `leads.converted_client_id`;
- proposta -> projeto: via `projects.proposal_id`;
- proposta -> contrato: via `contracts.proposal_id`;
- projeto -> parcelas -> pagamentos: `projects -> finance_installments -> finance_payments`;
- conta a receber -> recibos: `financial_accounts_receivable -> financial_receipts`;
- OS -> aprovação pública -> eventos/notificações: cadeia completa com tabelas próprias.

## Índices e unicidade

Pontos observáveis do modelo:

- uso frequente de PK auto-incremento;
- índices e FKs em entidades centrais;
- unicidade lógica importante em identificadores públicos e alguns cadastros;
- a inspeção automatizada atual não documenta/valida todos os índices existentes.

## Triggers

Existem triggers para impedir alteração e exclusão de linhas em:

- `servicos_avulsos_aprovacao_eventos`
- `servicos_avulsos_aprovacao_notificacoes`

Essas triggers reforçam o caráter de trilha imutável do fluxo de aprovação externa.

## Tabelas com maior relevância operacional

- `users`
- `clients`
- `leads`
- `proposals`
- `contracts`
- `projects`
- `finance_installments`
- `financial_accounts_receivable`
- `servicos_avulsos`
- `servicos_avulsos_aprovacoes`
- `audit_log`
- `financial_audit_logs`

## Tabelas potencialmente órfãs ou com integridade incompleta

Pontos levantados a partir do código e do schema atual:

- `clients.source_lead_id` aparenta vínculo lógico sem FK formal;
- campos `created_by`, `updated_by`, `requested_by`, `reviewed_by` e semelhantes nem sempre possuem FK formal para `users`;
- `projects.owner_user_id` aparenta vínculo lógico relevante;
- alguns snapshots JSON substituem normalização por conveniência histórica e documental.

## Redundâncias observadas

- coexistência de `is_admin` e `role` em `users`;
- snapshots JSON em contratos e propostas coexistem com dados normalizados;
- coexistência de financeiro de projetos e financeiro corporativo, com ligações entre parcelas e contas a receber.

## Melhorias possíveis sem alterar o banco agora

- ampliar a inspeção estrutural para validar FKs, triggers e índices;
- alinhar totalmente enums usados no código e declarados no schema;
- revisar seeds do schema base para garantir idempotência mais forte;
- formalizar FKs em referências hoje apenas lógicas.
