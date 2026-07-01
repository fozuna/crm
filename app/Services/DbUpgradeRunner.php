<?php
declare(strict_types=1);

namespace App\Services;

final class DbUpgradeRunner
{
    public function inspect(\PDO $pdo): array
    {
        $requiredTables = [
            'projects',
            'project_tasks',
            'project_milestones',
            'project_status_history',
            'project_events',
            'finance_installments',
            'finance_payments',
            'finance_cancellation_requests',
            'leads',
            'lead_interactions',
            'lead_stage_history',
            'audit_log',
            'company_profile',
            'company_profile_audit',
            'services',
            'financial_categories',
            'financial_cost_centers',
            'financial_bank_accounts',
            'financial_accounts_receivable',
            'financial_receipts',
            'financial_audit_logs',
            'contract_templates',
            'contracts',
            'contract_versions',
            'contract_notifications',
        ];

        $missingTables = [];
        foreach ($requiredTables as $t) {
            if (!$this->hasTable($pdo, $t)) {
                $missingTables[] = $t;
            }
        }

        $missingColumns = [];
        $needsColumns = [
            'clients' => ['person_type', 'document_number', 'secondary_phone', 'postal_code', 'street', 'street_number', 'address_complement', 'neighborhood', 'city', 'state', 'birth_or_opening_date', 'market_segment', 'acquisition_source', 'billing_email', 'billing_phone', 'billing_notes', 'contract_notes', 'source_lead_id', 'has_hosting_contract', 'hosting_contract_amount', 'hosting_due_date', 'hosting_renewal_days', 'manages_domain', 'domain_due_date', 'domain_amount'],
            'proposals' => ['requires_contract', 'contract_template_id', 'contract_policy_reason'],
            'company_profile' => ['brand_name', 'brand_tagline', 'primary_color', 'accent_color', 'font_name', 'meta_title', 'meta_description', 'meta_keywords', 'favicon_path', 'favicon_mime', 'favicon_original_name', 'meta_image_path', 'meta_image_mime', 'meta_image_original_name'],
            'contract_templates' => ['slug', 'description', 'is_active', 'auto_criteria_json', 'signature_mode_default', 'require_signature_default', 'header_title', 'body_template', 'footer_notes', 'updated_at'],
            'contracts' => ['client_id', 'status', 'signature_mode', 'needs_signature', 'contract_number', 'title', 'current_version', 'current_file_path', 'rendered_body', 'rendered_summary', 'source_proposal_snapshot', 'policy_reason', 'signature_provider', 'signature_reference', 'signature_url', 'sent_for_signature_at', 'signed_at', 'effective_date', 'expires_at', 'created_by', 'updated_by', 'updated_at'],
            'contract_versions' => ['version', 'template_snapshot', 'proposal_snapshot', 'rendered_body', 'file_path', 'created_by', 'created_at'],
            'contract_notifications' => ['type', 'recipient_name', 'recipient_email', 'channel', 'status', 'message', 'metadata', 'created_at', 'sent_at'],
            'finance_installments' => ['paid_amount'],
            'projects' => ['description', 'owner_user_id', 'start_date', 'end_date', 'total', 'updated_at'],
            'proposal_items' => ['service_id', 'is_bonus', 'catalog_price'],
            'financial_accounts_receivable' => ['company_id', 'remaining_amount', 'status', 'source_installment_id'],
            'financial_receipts' => ['receipt_file_path', 'reversed_at', 'reversal_reason'],
            'leads' => ['person_type', 'document_number', 'email', 'phone', 'postal_code', 'street', 'street_number', 'neighborhood', 'city', 'state', 'birth_or_opening_date', 'market_segment', 'acquisition_source', 'stage', 'converted_at'],
        ];
        foreach ($needsColumns as $table => $cols) {
            foreach ($cols as $col) {
                if (!$this->hasColumn($pdo, $table, $col)) {
                    $missingColumns[] = $table . '.' . $col;
                }
            }
        }

        $enumMismatches = [];
        $pStatus = $this->columnType($pdo, 'projects', 'status');
        if (is_string($pStatus) && !str_contains($pStatus, "'cancelado'")) {
            $enumMismatches[] = 'projects.status missing cancelado';
        }

        $fiStatus = $this->columnType($pdo, 'finance_installments', 'status');
        if (is_string($fiStatus) && (!str_contains($fiStatus, "'cancelado'") || !str_contains($fiStatus, "'reaberto'"))) {
            $enumMismatches[] = 'finance_installments.status missing cancelado/reaberto';
        }

        $pending = count($missingTables) > 0 || count($missingColumns) > 0 || count($enumMismatches) > 0;

        return [
            'pending' => $pending,
            'missing_tables' => $missingTables,
            'missing_columns' => $missingColumns,
            'enum_mismatches' => $enumMismatches,
        ];
    }

    public function run(\PDO $pdo): array
    {
        $path = __DIR__ . '/../../database/upgrade.sql';
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            throw new \RuntimeException('upgrade.sql não encontrado ou vazio.');
        }

        $statements = [];
        foreach ($this->splitSqlStatements($raw) as $sql) {
            $expanded = $this->explodeAlterAddColumns($sql);
            if (is_array($expanded)) {
                foreach ($expanded as $s) {
                    $statements[] = $s;
                }
                continue;
            }
            $statements[] = $sql;
        }

        $applied = 0;
        $skipped = 0;
        $deferredStatements = [];
        foreach ($statements as $i => $sql) {
            if ($this->shouldDeferStatement($sql)) {
                $deferredStatements[] = [$i, $sql];
                continue;
            }
            try {
                $pdo->exec($sql);
                $applied++;
            } catch (\Throwable $e) {
                if ($this->shouldIgnorePdoException($e)) {
                    $skipped++;
                    continue;
                }
                throw new \RuntimeException('Falha no statement #' . ($i + 1) . ': ' . $e->getMessage() . "\n" . $sql, 0, $e);
            }
        }

        [$ensAdded, $ensSkipped] = $this->ensureColumns($pdo, [
            'clients' => [
                'person_type' => "ALTER TABLE clients ADD COLUMN person_type ENUM('pf','pj') NOT NULL DEFAULT 'pj'",
                'document_number' => "ALTER TABLE clients ADD COLUMN document_number VARCHAR(18) NULL",
                'secondary_phone' => "ALTER TABLE clients ADD COLUMN secondary_phone VARCHAR(60) NULL",
                'postal_code' => "ALTER TABLE clients ADD COLUMN postal_code VARCHAR(12) NULL",
                'street' => "ALTER TABLE clients ADD COLUMN street VARCHAR(190) NULL",
                'street_number' => "ALTER TABLE clients ADD COLUMN street_number VARCHAR(30) NULL",
                'address_complement' => "ALTER TABLE clients ADD COLUMN address_complement VARCHAR(190) NULL",
                'neighborhood' => "ALTER TABLE clients ADD COLUMN neighborhood VARCHAR(120) NULL",
                'city' => "ALTER TABLE clients ADD COLUMN city VARCHAR(120) NULL",
                'state' => "ALTER TABLE clients ADD COLUMN state VARCHAR(2) NULL",
                'birth_or_opening_date' => "ALTER TABLE clients ADD COLUMN birth_or_opening_date DATE NULL",
                'market_segment' => "ALTER TABLE clients ADD COLUMN market_segment VARCHAR(120) NULL",
                'acquisition_source' => "ALTER TABLE clients ADD COLUMN acquisition_source VARCHAR(120) NULL",
                'billing_email' => "ALTER TABLE clients ADD COLUMN billing_email VARCHAR(190) NULL",
                'billing_phone' => "ALTER TABLE clients ADD COLUMN billing_phone VARCHAR(60) NULL",
                'billing_notes' => "ALTER TABLE clients ADD COLUMN billing_notes TEXT NULL",
                'contract_notes' => "ALTER TABLE clients ADD COLUMN contract_notes TEXT NULL",
                'source_lead_id' => "ALTER TABLE clients ADD COLUMN source_lead_id INT UNSIGNED NULL",
                'has_hosting_contract' => "ALTER TABLE clients ADD COLUMN has_hosting_contract TINYINT(1) NOT NULL DEFAULT 0",
                'hosting_contract_amount' => "ALTER TABLE clients ADD COLUMN hosting_contract_amount DECIMAL(12,2) NULL",
                'hosting_due_date' => "ALTER TABLE clients ADD COLUMN hosting_due_date DATE NULL",
                'hosting_renewal_days' => "ALTER TABLE clients ADD COLUMN hosting_renewal_days TINYINT UNSIGNED NULL",
                'manages_domain' => "ALTER TABLE clients ADD COLUMN manages_domain TINYINT(1) NOT NULL DEFAULT 0",
                'domain_due_date' => "ALTER TABLE clients ADD COLUMN domain_due_date DATE NULL",
                'domain_amount' => "ALTER TABLE clients ADD COLUMN domain_amount DECIMAL(12,2) NULL",
            ],
            'company_profile' => [
                'brand_name' => "ALTER TABLE company_profile ADD COLUMN brand_name VARCHAR(190) NULL AFTER trade_name",
                'brand_tagline' => "ALTER TABLE company_profile ADD COLUMN brand_tagline VARCHAR(255) NULL AFTER brand_name",
                'primary_color' => "ALTER TABLE company_profile ADD COLUMN primary_color VARCHAR(16) NOT NULL DEFAULT '#293241' AFTER website",
                'accent_color' => "ALTER TABLE company_profile ADD COLUMN accent_color VARCHAR(16) NOT NULL DEFAULT '#ee6c4d' AFTER primary_color",
                'font_name' => "ALTER TABLE company_profile ADD COLUMN font_name VARCHAR(80) NOT NULL DEFAULT 'Helvetica' AFTER accent_color",
                'meta_title' => "ALTER TABLE company_profile ADD COLUMN meta_title VARCHAR(190) NULL AFTER font_name",
                'meta_description' => "ALTER TABLE company_profile ADD COLUMN meta_description TEXT NULL AFTER meta_title",
                'meta_keywords' => "ALTER TABLE company_profile ADD COLUMN meta_keywords TEXT NULL AFTER meta_description",
                'favicon_path' => "ALTER TABLE company_profile ADD COLUMN favicon_path VARCHAR(255) NULL AFTER address_cipher",
                'favicon_mime' => "ALTER TABLE company_profile ADD COLUMN favicon_mime VARCHAR(120) NULL AFTER favicon_path",
                'favicon_original_name' => "ALTER TABLE company_profile ADD COLUMN favicon_original_name VARCHAR(255) NULL AFTER favicon_mime",
                'meta_image_path' => "ALTER TABLE company_profile ADD COLUMN meta_image_path VARCHAR(255) NULL AFTER favicon_original_name",
                'meta_image_mime' => "ALTER TABLE company_profile ADD COLUMN meta_image_mime VARCHAR(120) NULL AFTER meta_image_path",
                'meta_image_original_name' => "ALTER TABLE company_profile ADD COLUMN meta_image_original_name VARCHAR(255) NULL AFTER meta_image_mime",
            ],
            'contract_templates' => [
                'slug' => "ALTER TABLE contract_templates ADD COLUMN slug VARCHAR(120) NULL",
                'description' => "ALTER TABLE contract_templates ADD COLUMN description TEXT NULL",
                'is_active' => "ALTER TABLE contract_templates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
                'auto_criteria_json' => "ALTER TABLE contract_templates ADD COLUMN auto_criteria_json MEDIUMTEXT NULL",
                'signature_mode_default' => "ALTER TABLE contract_templates ADD COLUMN signature_mode_default ENUM('digital','print') NOT NULL DEFAULT 'print'",
                'require_signature_default' => "ALTER TABLE contract_templates ADD COLUMN require_signature_default TINYINT(1) NOT NULL DEFAULT 1",
                'header_title' => "ALTER TABLE contract_templates ADD COLUMN header_title VARCHAR(190) NOT NULL DEFAULT 'Contrato de Prestacao de Servicos'",
                'body_template' => "ALTER TABLE contract_templates ADD COLUMN body_template MEDIUMTEXT NULL",
                'footer_notes' => "ALTER TABLE contract_templates ADD COLUMN footer_notes TEXT NULL",
                'created_at' => "ALTER TABLE contract_templates ADD COLUMN created_at DATETIME NULL",
                'updated_at' => "ALTER TABLE contract_templates ADD COLUMN updated_at DATETIME NULL",
            ],
            'contracts' => [
                'client_id' => "ALTER TABLE contracts ADD COLUMN client_id INT UNSIGNED NULL AFTER proposal_id",
                'status' => "ALTER TABLE contracts ADD COLUMN status ENUM('rascunho','pendente_assinatura','assinado','vigente') NOT NULL DEFAULT 'rascunho' AFTER template_id",
                'signature_mode' => "ALTER TABLE contracts ADD COLUMN signature_mode ENUM('digital','print') NOT NULL DEFAULT 'print' AFTER status",
                'needs_signature' => "ALTER TABLE contracts ADD COLUMN needs_signature TINYINT(1) NOT NULL DEFAULT 1 AFTER signature_mode",
                'contract_number' => "ALTER TABLE contracts ADD COLUMN contract_number VARCHAR(40) NOT NULL DEFAULT '' AFTER needs_signature",
                'title' => "ALTER TABLE contracts ADD COLUMN title VARCHAR(190) NOT NULL DEFAULT 'Contrato de Prestacao de Servicos' AFTER contract_number",
                'current_version' => "ALTER TABLE contracts ADD COLUMN current_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER title",
                'current_file_path' => "ALTER TABLE contracts ADD COLUMN current_file_path VARCHAR(255) NULL AFTER current_version",
                'rendered_body' => "ALTER TABLE contracts ADD COLUMN rendered_body MEDIUMTEXT NULL AFTER current_file_path",
                'rendered_summary' => "ALTER TABLE contracts ADD COLUMN rendered_summary MEDIUMTEXT NULL AFTER rendered_body",
                'source_proposal_snapshot' => "ALTER TABLE contracts ADD COLUMN source_proposal_snapshot MEDIUMTEXT NULL AFTER rendered_summary",
                'policy_reason' => "ALTER TABLE contracts ADD COLUMN policy_reason VARCHAR(255) NULL AFTER source_proposal_snapshot",
                'signature_provider' => "ALTER TABLE contracts ADD COLUMN signature_provider VARCHAR(80) NULL AFTER policy_reason",
                'signature_reference' => "ALTER TABLE contracts ADD COLUMN signature_reference VARCHAR(190) NULL AFTER signature_provider",
                'signature_url' => "ALTER TABLE contracts ADD COLUMN signature_url VARCHAR(255) NULL AFTER signature_reference",
                'sent_for_signature_at' => "ALTER TABLE contracts ADD COLUMN sent_for_signature_at DATETIME NULL AFTER signature_url",
                'signed_at' => "ALTER TABLE contracts ADD COLUMN signed_at DATETIME NULL AFTER sent_for_signature_at",
                'effective_date' => "ALTER TABLE contracts ADD COLUMN effective_date DATE NULL AFTER signed_at",
                'expires_at' => "ALTER TABLE contracts ADD COLUMN expires_at DATE NULL AFTER effective_date",
                'created_by' => "ALTER TABLE contracts ADD COLUMN created_by INT UNSIGNED NULL AFTER expires_at",
                'updated_by' => "ALTER TABLE contracts ADD COLUMN updated_by INT UNSIGNED NULL AFTER created_by",
                'updated_at' => "ALTER TABLE contracts ADD COLUMN updated_at DATETIME NULL AFTER created_at",
            ],
            'contract_versions' => [
                'version' => "ALTER TABLE contract_versions ADD COLUMN version INT UNSIGNED NOT NULL DEFAULT 1 AFTER contract_id",
                'template_snapshot' => "ALTER TABLE contract_versions ADD COLUMN template_snapshot MEDIUMTEXT NULL AFTER version",
                'proposal_snapshot' => "ALTER TABLE contract_versions ADD COLUMN proposal_snapshot MEDIUMTEXT NULL AFTER template_snapshot",
                'rendered_body' => "ALTER TABLE contract_versions ADD COLUMN rendered_body MEDIUMTEXT NULL AFTER proposal_snapshot",
                'file_path' => "ALTER TABLE contract_versions ADD COLUMN file_path VARCHAR(255) NULL AFTER rendered_body",
                'created_by' => "ALTER TABLE contract_versions ADD COLUMN created_by INT UNSIGNED NULL AFTER file_path",
                'created_at' => "ALTER TABLE contract_versions ADD COLUMN created_at DATETIME NULL AFTER created_by",
            ],
            'contract_notifications' => [
                'type' => "ALTER TABLE contract_notifications ADD COLUMN type ENUM('signature_pending','signature_reminder','print_pending','status_changed') NOT NULL DEFAULT 'status_changed' AFTER contract_id",
                'recipient_name' => "ALTER TABLE contract_notifications ADD COLUMN recipient_name VARCHAR(190) NULL AFTER type",
                'recipient_email' => "ALTER TABLE contract_notifications ADD COLUMN recipient_email VARCHAR(190) NULL AFTER recipient_name",
                'channel' => "ALTER TABLE contract_notifications ADD COLUMN channel ENUM('system','email','manual') NOT NULL DEFAULT 'system' AFTER recipient_email",
                'status' => "ALTER TABLE contract_notifications ADD COLUMN status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending' AFTER channel",
                'message' => "ALTER TABLE contract_notifications ADD COLUMN message TEXT NULL AFTER status",
                'metadata' => "ALTER TABLE contract_notifications ADD COLUMN metadata MEDIUMTEXT NULL AFTER message",
                'created_at' => "ALTER TABLE contract_notifications ADD COLUMN created_at DATETIME NULL AFTER metadata",
                'sent_at' => "ALTER TABLE contract_notifications ADD COLUMN sent_at DATETIME NULL AFTER created_at",
            ],
            'proposals' => [
                'requires_contract' => "ALTER TABLE proposals ADD COLUMN requires_contract TINYINT(1) NOT NULL DEFAULT 0",
                'contract_template_id' => "ALTER TABLE proposals ADD COLUMN contract_template_id INT UNSIGNED NULL",
                'contract_policy_reason' => "ALTER TABLE proposals ADD COLUMN contract_policy_reason VARCHAR(255) NULL",
            ],
            'finance_installments' => [
                'paid_amount' => "ALTER TABLE finance_installments ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0",
                'paid_at' => "ALTER TABLE finance_installments ADD COLUMN paid_at DATETIME NULL",
                'canceled_at' => "ALTER TABLE finance_installments ADD COLUMN canceled_at DATETIME NULL",
                'canceled_by' => "ALTER TABLE finance_installments ADD COLUMN canceled_by INT UNSIGNED NULL",
                'cancel_reason' => "ALTER TABLE finance_installments ADD COLUMN cancel_reason TEXT NULL",
                'reopened_at' => "ALTER TABLE finance_installments ADD COLUMN reopened_at DATETIME NULL",
                'reopened_by' => "ALTER TABLE finance_installments ADD COLUMN reopened_by INT UNSIGNED NULL",
                'created_at' => "ALTER TABLE finance_installments ADD COLUMN created_at DATETIME NULL",
                'updated_at' => "ALTER TABLE finance_installments ADD COLUMN updated_at DATETIME NULL",
            ],
            'projects' => [
                'description' => "ALTER TABLE projects ADD COLUMN description MEDIUMTEXT NULL",
                'owner_user_id' => "ALTER TABLE projects ADD COLUMN owner_user_id INT UNSIGNED NULL",
                'start_date' => "ALTER TABLE projects ADD COLUMN start_date DATE NULL",
                'end_date' => "ALTER TABLE projects ADD COLUMN end_date DATE NULL",
                'total' => "ALTER TABLE projects ADD COLUMN total DECIMAL(12,2) NOT NULL DEFAULT 0",
                'updated_at' => "ALTER TABLE projects ADD COLUMN updated_at DATETIME NULL",
                'workflow_phase' => "ALTER TABLE projects ADD COLUMN workflow_phase ENUM('planejamento','execucao','acompanhamento','entrega','pos_venda') NOT NULL DEFAULT 'planejamento'",
                'progress_percent' => "ALTER TABLE projects ADD COLUMN progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0",
            ],
            'users' => [
                'is_admin' => "ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0",
                'role' => "ALTER TABLE users ADD COLUMN role ENUM('admin','pm','finance','auditor') NOT NULL DEFAULT 'pm'",
            ],
        ]);

        $this->harmonizeLegacyContractSchema($pdo);

        $mods = [];
        $pStatus = $this->columnType($pdo, 'projects', 'status');
        if (is_string($pStatus) && !str_contains($pStatus, "'cancelado'")) {
            $mods[] = "ALTER TABLE projects MODIFY COLUMN status ENUM('ativo','pausado','finalizado','cancelado') NOT NULL DEFAULT 'ativo'";
        }

        $fiStatus = $this->columnType($pdo, 'finance_installments', 'status');
        if (is_string($fiStatus) && (!str_contains($fiStatus, "'cancelado'") || !str_contains($fiStatus, "'reaberto'"))) {
            $mods[] = "ALTER TABLE finance_installments MODIFY COLUMN status ENUM('pendente','pago','cancelado','reaberto','atrasado') NOT NULL DEFAULT 'pendente'";
        }

        [$modApplied, $modSkipped] = $this->ensureStatements($pdo, $mods);

        foreach ($deferredStatements as [$i, $sql]) {
            try {
                $pdo->exec($sql);
                $applied++;
            } catch (\Throwable $e) {
                if ($this->shouldIgnorePdoException($e)) {
                    $skipped++;
                    continue;
                }
                throw new \RuntimeException('Falha no statement #' . ($i + 1) . ': ' . $e->getMessage() . "\n" . $sql, 0, $e);
            }
        }

        $inspect = $this->inspect($pdo);
        if ($inspect['pending']) {
            return [
                'ok' => false,
                'applied' => $applied,
                'skipped' => $skipped,
                'ensured_added' => $ensAdded,
                'ensured_skipped' => $ensSkipped,
                'adjusted_applied' => $modApplied,
                'adjusted_skipped' => $modSkipped,
                'inspect' => $inspect,
            ];
        }

        return [
            'ok' => true,
            'applied' => $applied,
            'skipped' => $skipped,
            'ensured_added' => $ensAdded,
            'ensured_skipped' => $ensSkipped,
            'adjusted_applied' => $modApplied,
            'adjusted_skipped' => $modSkipped,
            'inspect' => $inspect,
        ];
    }

    private function splitSqlStatements(string $sql): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $parts = preg_split('/;\s*\n/', $sql) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private function explodeAlterAddColumns(string $sql): ?array
    {
        if (preg_match('/^ALTER\s+TABLE\s+([`\w]+)\s+(.*)$/is', trim($sql), $m) !== 1) {
            return null;
        }

        $table = $m[1];
        $rest = trim($m[2]);
        if (!str_contains(strtoupper($rest), 'ADD COLUMN')) {
            return null;
        }

        $parts = preg_split('/,\s*\n\s*/', $rest) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn($x) => $x !== ''));
        if (count($parts) < 2) {
            return null;
        }

        foreach ($parts as $p) {
            if (stripos($p, 'ADD COLUMN') !== 0) {
                return null;
            }
        }

        $out = [];
        foreach ($parts as $p) {
            $out[] = 'ALTER TABLE ' . $table . ' ' . $p;
        }
        return $out;
    }

    private function shouldIgnorePdoException(\Throwable $e): bool
    {
        if (!($e instanceof \PDOException)) {
            return false;
        }
        $err = $e->errorInfo[1] ?? null;
        $ignore = [1050, 1060, 1061, 1062, 1091, 1826];
        return is_int($err) && in_array($err, $ignore, true);
    }

    private function shouldDeferStatement(string $sql): bool
    {
        $normalized = strtoupper(trim($sql));
        return str_starts_with($normalized, 'INSERT INTO CONTRACT_TEMPLATES');
    }

    private function hasTable(\PDO $pdo, string $table): bool
    {
        $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
        $row = $st ? $st->fetch(\PDO::FETCH_NUM) : false;
        return $row !== false;
    }

    private function hasColumn(\PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column));
        $row = $st ? $st->fetch(\PDO::FETCH_NUM) : false;
        return $row !== false;
    }

    private function columnType(\PDO $pdo, string $table, string $column): ?string
    {
        $st = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ' . $pdo->quote($column));
        $row = $st ? $st->fetch(\PDO::FETCH_ASSOC) : false;
        if (!is_array($row)) {
            return null;
        }
        return isset($row['Type']) ? (string) $row['Type'] : null;
    }

    private function ensureColumns(\PDO $pdo, array $map): array
    {
        $added = 0;
        $skipped = 0;
        foreach ($map as $table => $cols) {
            foreach ($cols as $col => $ddl) {
                if ($this->hasColumn($pdo, (string) $table, (string) $col)) {
                    $skipped++;
                    continue;
                }
                try {
                    $pdo->exec((string) $ddl);
                    $added++;
                } catch (\Throwable $e) {
                    if ($this->shouldIgnorePdoException($e)) {
                        $skipped++;
                        continue;
                    }
                    throw $e;
                }
            }
        }
        return [$added, $skipped];
    }

    private function ensureStatements(\PDO $pdo, array $sqls): array
    {
        $applied = 0;
        $skipped = 0;
        foreach ($sqls as $sql) {
            try {
                $pdo->exec((string) $sql);
                $applied++;
            } catch (\Throwable $e) {
                if ($this->shouldIgnorePdoException($e)) {
                    $skipped++;
                    continue;
                }
                throw $e;
            }
        }
        return [$applied, $skipped];
    }

    private function harmonizeLegacyContractSchema(\PDO $pdo): void
    {
        if ($this->hasTable($pdo, 'contract_templates')) {
            if ($this->hasColumn($pdo, 'contract_templates', 'body')) {
                $this->execIgnoringDuplicates($pdo, "ALTER TABLE contract_templates MODIFY COLUMN body MEDIUMTEXT NULL");
            }
            if ($this->hasColumn($pdo, 'contract_templates', 'body') && $this->hasColumn($pdo, 'contract_templates', 'body_template')) {
                $pdo->exec("UPDATE contract_templates SET body_template = COALESCE(NULLIF(body_template, ''), body) WHERE body IS NOT NULL");
                $pdo->exec("UPDATE contract_templates SET body = COALESCE(body, body_template) WHERE body IS NULL AND body_template IS NOT NULL");
            }
            if ($this->hasColumn($pdo, 'contract_templates', 'slug')) {
                $pdo->exec("UPDATE contract_templates SET slug = CONCAT('template-', id) WHERE slug IS NULL OR slug = ''");
            }
        }

        if ($this->hasTable($pdo, 'company_profile')) {
            if ($this->hasColumn($pdo, 'company_profile', 'primary_color')) {
                $pdo->exec("UPDATE company_profile SET primary_color = '#293241' WHERE primary_color IS NULL OR primary_color = ''");
            }
            if ($this->hasColumn($pdo, 'company_profile', 'accent_color')) {
                $pdo->exec("UPDATE company_profile SET accent_color = '#ee6c4d' WHERE accent_color IS NULL OR accent_color = ''");
            }
            if ($this->hasColumn($pdo, 'company_profile', 'font_name')) {
                $pdo->exec("UPDATE company_profile SET font_name = 'Helvetica' WHERE font_name IS NULL OR font_name = ''");
            }
        }

        if ($this->hasTable($pdo, 'proposal_branding') && $this->hasTable($pdo, 'company_profile')) {
            $hasBrandName = $this->hasColumn($pdo, 'company_profile', 'brand_name');
            $hasPrimaryColor = $this->hasColumn($pdo, 'company_profile', 'primary_color');
            $hasAccentColor = $this->hasColumn($pdo, 'company_profile', 'accent_color');
            $hasFontName = $this->hasColumn($pdo, 'company_profile', 'font_name');
            $hasMetaTitle = $this->hasColumn($pdo, 'company_profile', 'meta_title');

            if ($hasBrandName || $hasPrimaryColor || $hasAccentColor || $hasFontName || $hasMetaTitle) {
                $sets = [];
                if ($hasBrandName) {
                    $sets[] = "cp.brand_name = CASE WHEN cp.brand_name IS NULL OR cp.brand_name = '' THEN NULLIF(pb.company_name, '') ELSE cp.brand_name END";
                }
                if ($hasPrimaryColor) {
                    $sets[] = "cp.primary_color = CASE WHEN cp.primary_color IS NULL OR cp.primary_color = '' OR cp.primary_color = '#293241' THEN COALESCE(NULLIF(pb.primary_color, ''), cp.primary_color) ELSE cp.primary_color END";
                }
                if ($hasAccentColor) {
                    $sets[] = "cp.accent_color = CASE WHEN cp.accent_color IS NULL OR cp.accent_color = '' OR cp.accent_color = '#ee6c4d' THEN COALESCE(NULLIF(pb.accent_color, ''), cp.accent_color) ELSE cp.accent_color END";
                }
                if ($hasFontName) {
                    $sets[] = "cp.font_name = CASE WHEN cp.font_name IS NULL OR cp.font_name = '' OR cp.font_name = 'Helvetica' THEN COALESCE(NULLIF(pb.font_name, ''), cp.font_name) ELSE cp.font_name END";
                }
                if ($hasMetaTitle) {
                    $sets[] = "cp.meta_title = CASE WHEN cp.meta_title IS NULL OR cp.meta_title = '' THEN NULLIF(pb.company_name, '') ELSE cp.meta_title END";
                }

                if (count($sets) > 0) {
                    $pdo->exec('UPDATE company_profile cp JOIN proposal_branding pb ON pb.id = cp.id SET ' . implode(', ', $sets) . ' WHERE cp.id = 1');
                }
            }
        }

        if ($this->hasTable($pdo, 'contracts')) {
            if ($this->hasColumn($pdo, 'contracts', 'body')) {
                $this->execIgnoringDuplicates($pdo, "ALTER TABLE contracts MODIFY COLUMN body MEDIUMTEXT NULL");
            }
            if ($this->hasColumn($pdo, 'contracts', 'client_id')) {
                $pdo->exec("UPDATE contracts ct INNER JOIN proposals p ON p.id = ct.proposal_id SET ct.client_id = p.client_id WHERE ct.client_id IS NULL");
            }
            if ($this->hasColumn($pdo, 'contracts', 'rendered_body') && $this->hasColumn($pdo, 'contracts', 'body')) {
                $pdo->exec("UPDATE contracts SET rendered_body = COALESCE(NULLIF(rendered_body, ''), body) WHERE body IS NOT NULL");
            }
            if ($this->hasColumn($pdo, 'contracts', 'contract_number')) {
                $pdo->exec("UPDATE contracts SET contract_number = CONCAT('CTR-', LPAD(id, 6, '0')) WHERE contract_number IS NULL OR contract_number = ''");
            }
            if ($this->hasColumn($pdo, 'contracts', 'title')) {
                $pdo->exec("UPDATE contracts ct INNER JOIN proposals p ON p.id = ct.proposal_id SET ct.title = COALESCE(NULLIF(ct.title, ''), NULLIF(p.title, ''), 'Contrato de Prestacao de Servicos') WHERE ct.title IS NULL OR ct.title = ''");
            }
            if ($this->hasColumn($pdo, 'contracts', 'current_version')) {
                $pdo->exec("UPDATE contracts SET current_version = 1 WHERE current_version IS NULL OR current_version = 0");
            }
            if ($this->hasColumn($pdo, 'contracts', 'status')) {
                $pdo->exec("UPDATE contracts SET status = 'rascunho' WHERE status IS NULL OR status = ''");
            }
            if ($this->hasColumn($pdo, 'contracts', 'signature_mode')) {
                $pdo->exec("UPDATE contracts SET signature_mode = 'print' WHERE signature_mode IS NULL OR signature_mode = ''");
            }
            if ($this->hasColumn($pdo, 'contracts', 'needs_signature')) {
                $pdo->exec("UPDATE contracts SET needs_signature = 1 WHERE needs_signature IS NULL");
            }
            if ($this->hasColumn($pdo, 'contracts', 'updated_at')) {
                $pdo->exec("UPDATE contracts SET updated_at = COALESCE(updated_at, created_at, NOW()) WHERE updated_at IS NULL");
            }
        }

        if ($this->hasTable($pdo, 'contract_versions') && $this->hasColumn($pdo, 'contract_versions', 'rendered_body')) {
            $pdo->exec("UPDATE contract_versions SET rendered_body = '' WHERE rendered_body IS NULL");
        }

        if ($this->hasTable($pdo, 'contract_notifications')) {
            if ($this->hasColumn($pdo, 'contract_notifications', 'message')) {
                $pdo->exec("UPDATE contract_notifications SET message = 'Atualizacao de contrato' WHERE message IS NULL");
            }
            if ($this->hasColumn($pdo, 'contract_notifications', 'created_at')) {
                $pdo->exec("UPDATE contract_notifications SET created_at = NOW() WHERE created_at IS NULL");
            }
        }
    }

    private function execIgnoringDuplicates(\PDO $pdo, string $sql): void
    {
        try {
            $pdo->exec($sql);
        } catch (\Throwable $e) {
            if (!$this->shouldIgnorePdoException($e)) {
                throw $e;
            }
        }
    }
}
