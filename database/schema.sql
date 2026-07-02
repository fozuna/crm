CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  role ENUM('admin','pm','finance','auditor') NOT NULL DEFAULT 'pm',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  company VARCHAR(190) NULL,
  contact_person VARCHAR(190) NULL,
  person_type ENUM('pf','pj') NOT NULL DEFAULT 'pj',
  document_number VARCHAR(18) NULL,
  secondary_phone VARCHAR(60) NULL,
  postal_code VARCHAR(12) NULL,
  street VARCHAR(190) NULL,
  street_number VARCHAR(30) NULL,
  address_complement VARCHAR(190) NULL,
  neighborhood VARCHAR(120) NULL,
  city VARCHAR(120) NULL,
  state VARCHAR(2) NULL,
  birth_or_opening_date DATE NULL,
  market_segment VARCHAR(120) NULL,
  acquisition_source VARCHAR(120) NULL,
  billing_email VARCHAR(190) NULL,
  billing_phone VARCHAR(60) NULL,
  billing_notes TEXT NULL,
  contract_notes TEXT NULL,
  source_lead_id INT UNSIGNED NULL,
  status ENUM('lead','ativo') NOT NULL DEFAULT 'lead',
  project_reference VARCHAR(190) NULL,
  has_hosting_contract TINYINT(1) NOT NULL DEFAULT 0,
  hosting_contract_amount DECIMAL(12,2) NULL,
  hosting_due_date DATE NULL,
  hosting_renewal_days TINYINT UNSIGNED NULL,
  manages_domain TINYINT(1) NOT NULL DEFAULT 0,
  domain_due_date DATE NULL,
  domain_amount DECIMAL(12,2) NULL,
  logo_path VARCHAR(255) NULL,
  logo_mime VARCHAR(120) NULL,
  logo_original_name VARCHAR(255) NULL,
  INDEX idx_clients_source_lead (source_lead_id),
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_interactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  kind ENUM('nota','email','call','meeting') NOT NULL DEFAULT 'nota',
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_interactions_client (client_id),
  CONSTRAINT fk_interactions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  company VARCHAR(190) NULL,
  contact_person VARCHAR(190) NULL,
  person_type ENUM('pf','pj') NOT NULL DEFAULT 'pj',
  document_number VARCHAR(18) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(60) NOT NULL,
  secondary_phone VARCHAR(60) NULL,
  postal_code VARCHAR(12) NOT NULL,
  street VARCHAR(190) NOT NULL,
  street_number VARCHAR(30) NOT NULL,
  address_complement VARCHAR(190) NULL,
  neighborhood VARCHAR(120) NOT NULL,
  city VARCHAR(120) NOT NULL,
  state VARCHAR(2) NOT NULL,
  birth_or_opening_date DATE NOT NULL,
  market_segment VARCHAR(120) NOT NULL,
  acquisition_source VARCHAR(120) NOT NULL,
  stage ENUM('cadastro_realizado','em_contato','proposta_enviada','negociacao_em_andamento','pronto_para_aprovacao','aprovado') NOT NULL DEFAULT 'cadastro_realizado',
  notes TEXT NULL,
  converted_client_id INT UNSIGNED NULL,
  converted_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_leads_stage (stage, updated_at),
  INDEX idx_leads_email (email),
  INDEX idx_leads_document (document_number),
  INDEX idx_leads_converted (converted_at),
  CONSTRAINT fk_leads_converted_client FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lead_interactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  kind ENUM('nota','email','call','meeting') NOT NULL DEFAULT 'nota',
  note TEXT NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_lead_interactions_lead (lead_id, created_at),
  CONSTRAINT fk_lead_interactions_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lead_stage_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id INT UNSIGNED NOT NULL,
  from_stage ENUM('cadastro_realizado','em_contato','proposta_enviada','negociacao_em_andamento','pronto_para_aprovacao','aprovado') NULL,
  to_stage ENUM('cadastro_realizado','em_contato','proposta_enviada','negociacao_em_andamento','pronto_para_aprovacao','aprovado') NOT NULL,
  action ENUM('create','update','move','convert') NOT NULL DEFAULT 'move',
  note TEXT NULL,
  actor_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_lead_stage_history_lead (lead_id, created_at),
  CONSTRAINT fk_lead_stage_history_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS servicos_avulsos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero_sequencial INT UNSIGNED NOT NULL,
  numero_os VARCHAR(30) NOT NULL,
  service_name VARCHAR(190) NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  contact_name VARCHAR(190) NULL,
  assigned_user_id INT UNSIGNED NULL,
  type ENUM('correcao','melhoria','suporte','consultoria','implantacao','treinamento','outro') NOT NULL DEFAULT 'suporte',
  type_other_description VARCHAR(190) NULL,
  status ENUM('aberto','em_andamento','aguardando_cliente','aguardando_terceiros','concluido','cancelado','faturado') NOT NULL DEFAULT 'aberto',
  request_description MEDIUMTEXT NULL,
  executed_activities MEDIUMTEXT NULL,
  technical_notes MEDIUMTEXT NULL,
  internal_notes TEXT NULL,
  opened_at DATETIME NOT NULL,
  due_at DATETIME NULL,
  completed_at DATETIME NULL,
  estimated_hours DECIMAL(10,2) NULL,
  executed_hours DECIMAL(10,2) NULL,
  billable TINYINT(1) NOT NULL DEFAULT 0,
  base_service_id INT UNSIGNED NULL,
  base_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  surcharge_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  final_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  financial_receivable_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_servicos_avulsos_numero_sequencial (numero_sequencial),
  UNIQUE KEY uq_servicos_avulsos_numero_os (numero_os),
  INDEX idx_servicos_avulsos_status (status, opened_at),
  INDEX idx_servicos_avulsos_client (client_id, status),
  INDEX idx_servicos_avulsos_assigned (assigned_user_id, status),
  INDEX idx_servicos_avulsos_billable (billable, status),
  INDEX idx_servicos_avulsos_receivable (financial_receivable_id),
  CONSTRAINT fk_servicos_avulsos_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_servicos_avulsos_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_servicos_avulsos_service FOREIGN KEY (base_service_id) REFERENCES services(id) ON DELETE SET NULL,
  CONSTRAINT fk_servicos_avulsos_receivable FOREIGN KEY (financial_receivable_id) REFERENCES financial_accounts_receivable(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS servicos_avulsos_anexos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  servico_avulso_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_extension VARCHAR(20) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_servicos_avulsos_anexos_os (servico_avulso_id, created_at),
  CONSTRAINT fk_servicos_avulsos_anexos_os FOREIGN KEY (servico_avulso_id) REFERENCES servicos_avulsos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS servicos_avulsos_historico (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  servico_avulso_id INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  field_name VARCHAR(60) NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  message TEXT NULL,
  metadata MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_servicos_avulsos_historico_os (servico_avulso_id, created_at),
  CONSTRAINT fk_servicos_avulsos_historico_os FOREIGN KEY (servico_avulso_id) REFERENCES servicos_avulsos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proposals (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description MEDIUMTEXT NULL,
  notes TEXT NULL,
  status ENUM('rascunho','enviada','aprovada','recusada') NOT NULL DEFAULT 'rascunho',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method_id INT UNSIGNED NULL,
  payment_snapshot MEDIUMTEXT NULL,
  payment_options MEDIUMTEXT NULL,
  payment_selected_index INT UNSIGNED NOT NULL DEFAULT 0,
  delivery_start DATE NULL,
  delivery_end DATE NULL,
  penalty_terms TEXT NULL,
  terms MEDIUMTEXT NULL,
  requires_contract TINYINT(1) NOT NULL DEFAULT 0,
  contract_template_id INT UNSIGNED NULL,
  contract_policy_reason VARCHAR(255) NULL,
  converted_project TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_client_id (client_id),
  INDEX idx_payment_method (payment_method_id),
  CONSTRAINT fk_proposals_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payment_methods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  type ENUM('avista','parcelado') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  installments_count INT UNSIGNED NOT NULL DEFAULT 1,
  interval_days INT UNSIGNED NOT NULL DEFAULT 30,
  has_down_payment TINYINT(1) NOT NULL DEFAULT 0,
  down_payment_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  special_terms TEXT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  default_price DECIMAL(12,2) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  description TEXT NOT NULL,
  is_bonus TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_services_name (name),
  INDEX idx_services_active (active),
  INDEX idx_services_bonus (is_bonus)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE proposals
  ADD CONSTRAINT fk_proposals_payment_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS proposal_milestones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  due_date DATE NULL,
  notes TEXT NULL,
  penalty_terms TEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_milestones_proposal (proposal_id),
  CONSTRAINT fk_milestones_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS proposal_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  file_path VARCHAR(255) NOT NULL,
  branding_snapshot MEDIUMTEXT NULL,
  totals_snapshot MEDIUMTEXT NULL,
  generated_at DATETIME NOT NULL,
  INDEX idx_docs_proposal (proposal_id),
  CONSTRAINT fk_docs_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contract_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  auto_criteria_json MEDIUMTEXT NULL,
  signature_mode_default ENUM('digital','print') NOT NULL DEFAULT 'print',
  require_signature_default TINYINT(1) NOT NULL DEFAULT 1,
  header_title VARCHAR(190) NOT NULL DEFAULT 'Contrato de Prestacao de Servicos',
  body_template MEDIUMTEXT NOT NULL,
  footer_notes TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_contract_templates_slug (slug),
  INDEX idx_contract_templates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contracts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  template_id INT UNSIGNED NOT NULL,
  status ENUM('rascunho','pendente_assinatura','assinado','vigente') NOT NULL DEFAULT 'rascunho',
  signature_mode ENUM('digital','print') NOT NULL DEFAULT 'print',
  needs_signature TINYINT(1) NOT NULL DEFAULT 1,
  contract_number VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  current_version INT UNSIGNED NOT NULL DEFAULT 1,
  current_file_path VARCHAR(255) NULL,
  rendered_body MEDIUMTEXT NOT NULL,
  rendered_summary MEDIUMTEXT NULL,
  source_proposal_snapshot MEDIUMTEXT NULL,
  policy_reason VARCHAR(255) NULL,
  signature_provider VARCHAR(80) NULL,
  signature_reference VARCHAR(190) NULL,
  signature_url VARCHAR(255) NULL,
  sent_for_signature_at DATETIME NULL,
  signed_at DATETIME NULL,
  effective_date DATE NULL,
  expires_at DATE NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_contracts_proposal (proposal_id),
  INDEX idx_contracts_status (status),
  INDEX idx_contracts_client (client_id),
  INDEX idx_contracts_effective (effective_date),
  CONSTRAINT fk_contracts_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_contracts_template FOREIGN KEY (template_id) REFERENCES contract_templates(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contract_versions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  version INT UNSIGNED NOT NULL,
  template_snapshot MEDIUMTEXT NULL,
  proposal_snapshot MEDIUMTEXT NULL,
  rendered_body MEDIUMTEXT NOT NULL,
  file_path VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_contract_versions_contract_version (contract_id, version),
  INDEX idx_contract_versions_contract (contract_id),
  CONSTRAINT fk_contract_versions_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contract_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  contract_id INT UNSIGNED NOT NULL,
  type ENUM('signature_pending','signature_reminder','print_pending','status_changed') NOT NULL,
  recipient_name VARCHAR(190) NULL,
  recipient_email VARCHAR(190) NULL,
  channel ENUM('system','email','manual') NOT NULL DEFAULT 'system',
  status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  message TEXT NOT NULL,
  metadata MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  INDEX idx_contract_notifications_contract (contract_id),
  INDEX idx_contract_notifications_status (status),
  CONSTRAINT fk_contract_notifications_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO contract_templates (
  id, name, slug, description, is_active, auto_criteria_json, signature_mode_default,
  require_signature_default, header_title, body_template, footer_notes, created_at, updated_at
) VALUES (
  1,
  'Prestacao de Servicos Padrao',
  'prestacao-servicos-padrao',
  'Template padrao para contratos gerados a partir de propostas aprovadas.',
  1,
  '{"enabled":true,"min_total":5000,"required_client_ids":[],"required_service_ids":[],"service_keywords":["mensalidade","suporte","desenvolvimento","implantacao"]}',
  'digital',
  1,
  'Contrato de Prestacao de Servicos',
  'CONTRATADA:\n{{company_legal_name}}\nCNPJ: {{company_cnpj}}\nE-mail: {{company_email}}\n\nCONTRATANTE:\n{{client_name}}\nEmpresa: {{client_company}}\nE-mail: {{client_email}}\nTelefone: {{client_phone}}\n\nOBJETO\nA CONTRATADA prestara os servicos descritos na proposta aprovada {{proposal_title}}.\n\nSERVICOS CONTRATADOS\n{{services_summary}}\n\nVALORES E CONDICOES DE PAGAMENTO\nValor total da proposta: {{proposal_total}}\n{{payment_schedule}}\n\nPRAZOS\nInicio previsto: {{delivery_start}}\nTermino previsto: {{delivery_end}}\n{{milestones_summary}}\n\nTERMOS COMERCIAIS\n{{proposal_terms}}\n\nOBSERVACOES\n{{proposal_notes}}\n\nFORMALIZACAO\nEste contrato foi gerado automaticamente a partir da proposta #{{proposal_id}} e deve seguir o fluxo de assinatura selecionado para a proposta.',
  'As partes declaram estar de acordo com os termos acima.',
  NOW(),
  NOW()
) ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  slug = VALUES(slug),
  description = VALUES(description),
  auto_criteria_json = VALUES(auto_criteria_json),
  signature_mode_default = VALUES(signature_mode_default),
  require_signature_default = VALUES(require_signature_default),
  header_title = VALUES(header_title),
  body_template = VALUES(body_template),
  footer_notes = VALUES(footer_notes),
  is_active = VALUES(is_active),
  updated_at = NOW();

CREATE TABLE IF NOT EXISTS proposal_branding (
  id INT UNSIGNED PRIMARY KEY,
  company_name VARCHAR(190) NOT NULL,
  logo_path VARCHAR(255) NULL,
  primary_color VARCHAR(16) NOT NULL DEFAULT '#293241',
  accent_color VARCHAR(16) NOT NULL DEFAULT '#ee6c4d',
  font_name VARCHAR(80) NOT NULL DEFAULT 'Helvetica',
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO proposal_branding (id, company_name, logo_path, primary_color, accent_color, font_name, updated_at)
VALUES (1, 'TRAXTER', NULL, '#293241', '#ee6c4d', 'Helvetica', NOW())
ON DUPLICATE KEY UPDATE updated_at = updated_at;

INSERT INTO payment_methods (name, type, active, discount_percent, installments_count, interval_days, has_down_payment, down_payment_percent, special_terms, created_at)
VALUES
('À vista (5% desconto)', 'avista', 1, 5.00, 1, 30, 0, 0.00, NULL, NOW()),
('Parcelado 3x (sem entrada)', 'parcelado', 1, 0.00, 3, 30, 0, 0.00, NULL, NOW())
;

CREATE TABLE IF NOT EXISTS proposal_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NULL,
  is_bonus TINYINT(1) NOT NULL DEFAULT 0,
  catalog_price DECIMAL(12,2) NULL,
  description VARCHAR(255) NOT NULL,
  qty DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  INDEX idx_proposal_id (proposal_id),
  INDEX idx_item_service (service_id),
  CONSTRAINT fk_items_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE proposal_items
  ADD CONSTRAINT fk_items_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  status ENUM('ativo','pausado','finalizado','cancelado') NOT NULL DEFAULT 'ativo',
  workflow_phase ENUM('planejamento','execucao','acompanhamento','entrega','pos_venda') NOT NULL DEFAULT 'planejamento',
  description MEDIUMTEXT NULL,
  owner_user_id INT UNSIGNED NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_project_client (client_id),
  INDEX idx_project_phase (workflow_phase),
  CONSTRAINT fk_projects_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE RESTRICT,
  CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_installments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposal_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  installment_no INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE NOT NULL,
  status ENUM('pendente','pago','cancelado','reaberto') NOT NULL DEFAULT 'pendente',
  paid_at DATETIME NULL,
  canceled_at DATETIME NULL,
  canceled_by INT UNSIGNED NULL,
  cancel_reason TEXT NULL,
  reopened_at DATETIME NULL,
  reopened_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_finance_project (project_id),
  INDEX idx_finance_due (due_date),
  INDEX idx_finance_status (status),
  CONSTRAINT fk_finance_proposal FOREIGN KEY (proposal_id) REFERENCES proposals(id) ON DELETE RESTRICT,
  CONSTRAINT fk_finance_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installment_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method VARCHAR(80) NULL,
  reference VARCHAR(120) NULL,
  note TEXT NULL,
  paid_at DATETIME NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_payments_installment (installment_id),
  INDEX idx_payments_paid_at (paid_at),
  CONSTRAINT fk_payments_installment FOREIGN KEY (installment_id) REFERENCES finance_installments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS finance_cancellation_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  installment_id INT UNSIGNED NOT NULL,
  requested_by INT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  status ENUM('pendente','aprovada','rejeitada') NOT NULL DEFAULT 'pendente',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  interest_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX idx_cancel_installment (installment_id),
  INDEX idx_cancel_status (status),
  CONSTRAINT fk_cancel_installment FOREIGN KEY (installment_id) REFERENCES finance_installments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  phase ENUM('planejamento','execucao','acompanhamento','entrega','pos_venda') NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  assigned_user_id INT UNSIGNED NULL,
  status ENUM('pendente','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'pendente',
  due_date DATE NULL,
  order_no INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_tasks_project (project_id),
  INDEX idx_tasks_phase (phase),
  INDEX idx_tasks_assigned (assigned_user_id),
  INDEX idx_tasks_status (status),
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_milestones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  due_date DATE NULL,
  notes TEXT NULL,
  status ENUM('pendente','concluida','cancelada') NOT NULL DEFAULT 'pendente',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_pm_project (project_id),
  INDEX idx_pm_due (due_date),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  from_phase ENUM('planejamento','execucao','acompanhamento','entrega','pos_venda') NULL,
  to_phase ENUM('planejamento','execucao','acompanhamento','entrega','pos_venda') NOT NULL,
  reason TEXT NULL,
  actor_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_psh_project (project_id),
  INDEX idx_psh_created_at (created_at),
  CONSTRAINT fk_psh_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  kind VARCHAR(40) NOT NULL,
  message VARCHAR(255) NOT NULL,
  payload MEDIUMTEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_pe_project (project_id),
  INDEX idx_pe_created_at (created_at),
  CONSTRAINT fk_pe_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  action VARCHAR(60) NOT NULL,
  actor_id INT UNSIGNED NULL,
  data MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS company_profile (
  id INT UNSIGNED PRIMARY KEY,
  legal_name VARCHAR(190) NOT NULL,
  trade_name VARCHAR(190) NULL,
  brand_name VARCHAR(190) NULL,
  brand_tagline VARCHAR(255) NULL,
  cnpj VARCHAR(14) NOT NULL,
  domain VARCHAR(190) NULL,
  website VARCHAR(190) NULL,
  primary_color VARCHAR(16) NOT NULL DEFAULT '#293241',
  accent_color VARCHAR(16) NOT NULL DEFAULT '#ee6c4d',
  font_name VARCHAR(80) NOT NULL DEFAULT 'Helvetica',
  meta_title VARCHAR(190) NULL,
  meta_description TEXT NULL,
  meta_keywords TEXT NULL,
  email_cipher TEXT NULL,
  phones_cipher MEDIUMTEXT NULL,
  whatsapp_cipher TEXT NULL,
  address_cipher MEDIUMTEXT NULL,
  favicon_path VARCHAR(255) NULL,
  favicon_mime VARCHAR(120) NULL,
  favicon_original_name VARCHAR(255) NULL,
  meta_image_path VARCHAR(255) NULL,
  meta_image_mime VARCHAR(120) NULL,
  meta_image_original_name VARCHAR(255) NULL,
  logo_light_path VARCHAR(255) NULL,
  logo_light_mime VARCHAR(120) NULL,
  logo_light_original_name VARCHAR(255) NULL,
  logo_dark_path VARCHAR(255) NULL,
  logo_dark_mime VARCHAR(120) NULL,
  logo_dark_original_name VARCHAR(255) NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_company_profile_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS company_profile_audit (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id INT UNSIGNED NOT NULL,
  action ENUM('create','update','delete','logo_update') NOT NULL,
  source ENUM('ui','api') NOT NULL DEFAULT 'ui',
  diff MEDIUMTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_company_profile_audit_created_at (created_at DESC),
  INDEX idx_company_profile_audit_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL DEFAULT 1,
  name VARCHAR(190) NOT NULL,
  type ENUM('receivable') NOT NULL DEFAULT 'receivable',
  color VARCHAR(16) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_financial_categories_company_name (company_id, name),
  INDEX idx_financial_categories_company (company_id),
  CONSTRAINT fk_financial_categories_company FOREIGN KEY (company_id) REFERENCES company_profile(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_cost_centers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL DEFAULT 1,
  name VARCHAR(190) NOT NULL,
  code VARCHAR(60) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_financial_cost_centers_company_name (company_id, name),
  INDEX idx_financial_cost_centers_company (company_id),
  CONSTRAINT fk_financial_cost_centers_company FOREIGN KEY (company_id) REFERENCES company_profile(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_bank_accounts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL DEFAULT 1,
  bank_name VARCHAR(190) NOT NULL,
  account_name VARCHAR(190) NOT NULL,
  branch_number VARCHAR(50) NULL,
  account_number VARCHAR(80) NULL,
  pix_key VARCHAR(190) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_financial_bank_accounts_company (company_id),
  CONSTRAINT fk_financial_bank_accounts_company FOREIGN KEY (company_id) REFERENCES company_profile(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_accounts_receivable (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL DEFAULT 1,
  project_id INT UNSIGNED NULL,
  client_id INT UNSIGNED NOT NULL,
  contract_id INT UNSIGNED NULL,
  source_installment_id INT UNSIGNED NULL,
  installment_number INT UNSIGNED NOT NULL DEFAULT 1,
  total_installments INT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  original_amount DECIMAL(12,2) NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  interest_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  fine_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  received_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  remaining_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_date DATE NOT NULL,
  issue_date DATE NULL,
  payment_date DATE NULL,
  competence_date DATE NULL,
  status ENUM('pending','partially_paid','paid','overdue','canceled','renegotiated') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(80) NULL,
  payment_channel VARCHAR(80) NULL,
  bank_account_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  cost_center_id INT UNSIGNED NULL,
  invoice_number VARCHAR(120) NULL,
  external_reference VARCHAR(120) NULL,
  recurrence_group VARCHAR(80) NULL,
  recurrence_interval_months INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_financial_receivable_company (company_id, status, due_date),
  INDEX idx_financial_receivable_client (client_id, due_date),
  INDEX idx_financial_receivable_project (project_id, due_date),
  INDEX idx_financial_receivable_invoice (invoice_number),
  INDEX idx_financial_receivable_reference (external_reference),
  CONSTRAINT fk_financial_receivable_company FOREIGN KEY (company_id) REFERENCES company_profile(id) ON DELETE RESTRICT,
  CONSTRAINT fk_financial_receivable_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_financial_receivable_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
  CONSTRAINT fk_financial_receivable_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL,
  CONSTRAINT fk_financial_receivable_installment FOREIGN KEY (source_installment_id) REFERENCES finance_installments(id) ON DELETE SET NULL,
  CONSTRAINT fk_financial_receivable_bank FOREIGN KEY (bank_account_id) REFERENCES financial_bank_accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_financial_receivable_category FOREIGN KEY (category_id) REFERENCES financial_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_financial_receivable_cost_center FOREIGN KEY (cost_center_id) REFERENCES financial_cost_centers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_receipts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receivable_id INT UNSIGNED NOT NULL,
  amount_received DECIMAL(12,2) NOT NULL,
  interest_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  fine_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method VARCHAR(80) NULL,
  payment_date DATETIME NOT NULL,
  transaction_reference VARCHAR(120) NULL,
  bank_reference VARCHAR(120) NULL,
  receipt_file_path VARCHAR(255) NULL,
  observation TEXT NULL,
  reversed_at DATETIME NULL,
  reversed_by INT UNSIGNED NULL,
  reversal_reason TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_financial_receipts_receivable (receivable_id, payment_date),
  INDEX idx_financial_receipts_reversed (reversed_at),
  CONSTRAINT fk_financial_receipts_receivable FOREIGN KEY (receivable_id) REFERENCES financial_accounts_receivable(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS financial_audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL DEFAULT 1,
  receivable_id INT UNSIGNED NULL,
  actor_id INT UNSIGNED NULL,
  ip_address VARCHAR(64) NULL,
  action VARCHAR(80) NOT NULL,
  before_data MEDIUMTEXT NULL,
  after_data MEDIUMTEXT NULL,
  metadata MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_financial_audit_company (company_id, created_at),
  INDEX idx_financial_audit_receivable (receivable_id, created_at),
  CONSTRAINT fk_financial_audit_company FOREIGN KEY (company_id) REFERENCES company_profile(id) ON DELETE RESTRICT,
  CONSTRAINT fk_financial_audit_receivable FOREIGN KEY (receivable_id) REFERENCES financial_accounts_receivable(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO financial_categories (company_id, name, type, color, active, created_at, updated_at)
VALUES
(1, 'Mensalidades', 'receivable', '#3B82F6', 1, NOW(), NOW()),
(1, 'Projetos', 'receivable', '#22C55E', 1, NOW(), NOW()),
 (1, 'Serviços recorrentes', 'receivable', '#F59E0B', 1, NOW(), NOW()),
 (1, 'Serviços avulsos', 'receivable', '#8B5CF6', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

INSERT INTO financial_cost_centers (company_id, name, code, active, created_at, updated_at)
VALUES
(1, 'Operacional', 'OP', 1, NOW(), NOW()),
(1, 'Comercial', 'COM', 1, NOW(), NOW()),
 (1, 'Projetos', 'PRJ', 1, NOW(), NOW()),
 (1, 'Serviços Avulsos', 'OS', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

INSERT INTO financial_bank_accounts (company_id, bank_name, account_name, branch_number, account_number, pix_key, active, created_at, updated_at)
VALUES
(1, 'Conta principal', 'Conta principal TRAXTER', NULL, NULL, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
