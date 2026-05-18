<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DbResetRunner
{
    public function inspect(PDO $pdo, array $explicitPreserve = []): array
    {
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $tables = $this->listBaseTables($pdo);
        $preserve = $this->resolvePreserveTables($pdo, $dbName, $explicitPreserve);
        $purge = array_values(array_diff($tables, $preserve));
        sort($preserve);
        sort($purge);

        return [
            'db' => $dbName,
            'tables_total' => count($tables),
            'tables_preserve' => $preserve,
            'tables_purge' => $purge,
        ];
    }

    public function run(PDO $pdo, array $options, callable $log): array
    {
        $startedAt = microtime(true);
        $passphrase = (string) ($options['passphrase'] ?? '');
        if ($passphrase === '') {
            throw new \RuntimeException('Passphrase obrigatória para criptografar o backup.');
        }

        $explicitPreserve = (array) ($options['preserve_tables'] ?? []);
        $seedMinimum = (bool) ($options['seed_minimum'] ?? true);
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $inspect = $this->inspect($pdo, $explicitPreserve);
        $preserve = (array) ($inspect['tables_preserve'] ?? []);
        $purge = (array) ($inspect['tables_purge'] ?? []);

        $log('inspect', [
            'db' => $inspect['db'] ?? null,
            'tables_total' => $inspect['tables_total'] ?? null,
            'preserve_count' => count($preserve),
            'purge_count' => count($purge),
        ]);

        $backupDir = __DIR__ . '/../../storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0700, true);
        }
        if (!is_dir($backupDir)) {
            throw new \RuntimeException('Falha ao criar diretório de backup.');
        }
        @chmod($backupDir, 0700);

        $ts = date('Ymd_His');
        $dbName = (string) ($inspect['db'] ?? 'db');
        $plainGz = $backupDir . '/backup_' . $dbName . '_' . $ts . '.sql.gz';
        $encFile = $plainGz . '.enc';
        $metaFile = $plainGz . '.enc.json';

        $log('backup_start', ['file' => basename($plainGz)]);
        $backup = $this->backupToGzip($pdo, $plainGz, $log);
        $log('backup_done', ['bytes' => $backup['bytes'] ?? null]);

        $log('encrypt_start', ['file' => basename($encFile)]);
        $meta = $this->encryptFileAes256CbcHmac($plainGz, $encFile, $passphrase);
        @file_put_contents($metaFile, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        @chmod($encFile, 0600);
        @chmod($metaFile, 0600);
        @unlink($plainGz);
        $log('encrypt_done', ['enc' => basename($encFile), 'meta' => basename($metaFile)]);

        if ($dryRun) {
            $log('dry_run', ['purge_skipped' => true]);
            return [
                'ok' => true,
                'dry_run' => true,
                'backup' => [
                    'file' => $encFile,
                    'meta' => $metaFile,
                    'bytes_plain' => $backup['bytes'] ?? null,
                ],
                'inspect' => $inspect,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }

        $log('purge_start', ['tables' => count($purge)]);
        $purgeRes = $this->purgeTables($pdo, $purge, $log);
        $log('purge_done', ['tables' => count($purge), 'total_deleted' => $purgeRes['total_deleted'] ?? 0]);

        if ($seedMinimum) {
            $log('seed_start', []);
            $seedRes = $this->seedMinimum($pdo);
            $log('seed_done', $seedRes);
        }

        $verify = $this->verify($pdo, $preserve, $purge);
        $log('verify', $verify);

        return [
            'ok' => ($verify['ok'] ?? false) === true,
            'backup' => [
                'file' => $encFile,
                'meta' => $metaFile,
                'bytes_plain' => $backup['bytes'] ?? null,
            ],
            'purge' => $purgeRes,
            'verify' => $verify,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function listBaseTables(PDO $pdo): array
    {
        $rows = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll();
        $tables = [];
        foreach ($rows as $r) {
            if (!is_array($r) || count($r) < 1) {
                continue;
            }
            $name = (string) array_values($r)[0];
            if ($name !== '') {
                $tables[] = $name;
            }
        }
        sort($tables);
        return $tables;
    }

    private function resolvePreserveTables(PDO $pdo, string $dbName, array $explicitPreserve): array
    {
        $explicitPreserve = array_values(array_filter(array_map(static fn ($t) => (string) $t, $explicitPreserve), static fn ($t) => $t !== ''));
        $preserve = array_values(array_unique(array_merge(['users', 'audit_log', 'financial_audit_logs'], $explicitPreserve)));

        $queue = $preserve;
        $seen = array_fill_keys($preserve, true);

        while ($queue !== []) {
            $table = array_shift($queue);
            $stmt = $pdo->prepare(
                "SELECT TABLE_NAME
                   FROM information_schema.KEY_COLUMN_USAGE
                  WHERE CONSTRAINT_SCHEMA = :db
                    AND REFERENCED_TABLE_NAME = :ref
                    AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            $stmt->execute([':db' => $dbName, ':ref' => $table]);
            $deps = $stmt->fetchAll();
            foreach ($deps as $d) {
                $t = is_array($d) ? (string) ($d['TABLE_NAME'] ?? '') : '';
                if ($t === '' || isset($seen[$t])) {
                    continue;
                }
                $seen[$t] = true;
                $preserve[] = $t;
                $queue[] = $t;
            }
        }

        $allTables = $this->listBaseTables($pdo);
        $preserve = array_values(array_intersect($allTables, $preserve));
        sort($preserve);
        return $preserve;
    }

    private function backupToGzip(PDO $pdo, string $outFile, callable $log): array
    {
        $gz = @gzopen($outFile, 'wb9');
        if (!is_resource($gz)) {
            throw new \RuntimeException('Falha ao abrir arquivo de backup.');
        }

        $bytes = 0;
        $write = static function ($gz, string $s) use (&$bytes): void {
            $bytes += (int) gzwrite($gz, $s);
        };

        $write($gz, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
        $write($gz, "SET FOREIGN_KEY_CHECKS=0;\n");
        $write($gz, "SET NAMES utf8mb4;\n\n");

        $tables = $this->listBaseTables($pdo);
        foreach ($tables as $t) {
            $log('backup_table', ['table' => $t]);
            $qt = $this->quoteIdent($t);

            $create = $pdo->query('SHOW CREATE TABLE ' . $qt)->fetch();
            $createSql = is_array($create) ? (string) ($create['Create Table'] ?? '') : '';
            if ($createSql !== '') {
                $write($gz, "DROP TABLE IF EXISTS {$qt};\n");
                $write($gz, $createSql . ";\n\n");
            }

            $cols = $pdo->query('SHOW COLUMNS FROM ' . $qt)->fetchAll();
            $colNames = [];
            foreach ($cols as $c) {
                if (is_array($c) && isset($c['Field'])) {
                    $colNames[] = (string) $c['Field'];
                }
            }
            if ($colNames === []) {
                continue;
            }
            $colList = implode(', ', array_map([$this, 'quoteIdent'], $colNames));

            $stmt = $pdo->query('SELECT * FROM ' . $qt);
            $batch = [];
            $batchSize = 200;
            while (true) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    break;
                }
                $vals = [];
                foreach ($colNames as $cn) {
                    $v = $row[$cn] ?? null;
                    if ($v === null) {
                        $vals[] = 'NULL';
                        continue;
                    }
                    $vals[] = $pdo->quote((string) $v);
                }
                $batch[] = '(' . implode(', ', $vals) . ')';
                if (count($batch) >= $batchSize) {
                    $write($gz, 'INSERT INTO ' . $qt . ' (' . $colList . ') VALUES ' . implode(",\n", $batch) . ";\n");
                    $batch = [];
                }
            }
            if ($batch !== []) {
                $write($gz, 'INSERT INTO ' . $qt . ' (' . $colList . ') VALUES ' . implode(",\n", $batch) . ";\n");
            }
            $write($gz, "\n");
        }

        $write($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
        @chmod($outFile, 0600);

        return ['bytes' => $bytes];
    }

    private function encryptFileAes256CbcHmac(string $inFile, string $outFile, string $passphrase): array
    {
        if (!function_exists('hash_pbkdf2') || !function_exists('hash_hmac') || !function_exists('openssl_encrypt')) {
            throw new \RuntimeException('Extensões de criptografia indisponíveis no PHP.');
        }

        $salt = random_bytes(16);
        $iv = random_bytes(16);
        $iterations = 200000;
        $dk = hash_pbkdf2('sha256', $passphrase, $salt, $iterations, 64, true);
        $encKey = substr($dk, 0, 32);
        $macKey = substr($dk, 32, 32);

        $in = @fopen($inFile, 'rb');
        if (!is_resource($in)) {
            throw new \RuntimeException('Falha ao abrir backup para criptografar.');
        }
        $out = @fopen($outFile, 'wb');
        if (!is_resource($out)) {
            fclose($in);
            throw new \RuntimeException('Falha ao criar arquivo criptografado.');
        }

        $h = hash_init('sha256', HASH_HMAC, $macKey);
        hash_update($h, $salt);
        hash_update($h, $iv);

        $blockSize = 16;
        $buf = '';
        $bytesIn = 0;
        $bytesOut = 0;
        $curIv = $iv;

        while (!feof($in)) {
            $chunk = fread($in, 1024 * 1024);
            if ($chunk === false) {
                fclose($in);
                fclose($out);
                throw new \RuntimeException('Falha ao ler backup para criptografar.');
            }
            $bytesIn += strlen($chunk);
            $buf .= $chunk;

            $fullLen = intdiv(strlen($buf), $blockSize) * $blockSize;
            if ($fullLen === 0) {
                continue;
            }
            $toEnc = substr($buf, 0, $fullLen);
            $buf = substr($buf, $fullLen);

            $cipher = openssl_encrypt($toEnc, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $curIv);
            if ($cipher === false) {
                fclose($in);
                fclose($out);
                throw new \RuntimeException('Falha ao criptografar backup.');
            }
            $curIv = substr($cipher, -$blockSize);
            hash_update($h, $cipher);
            $bytesOut += (int) fwrite($out, $cipher);
        }

        $padLen = $blockSize - (strlen($buf) % $blockSize);
        $buf .= str_repeat(chr($padLen), $padLen);
        $cipher = openssl_encrypt($buf, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $curIv);
        if ($cipher === false) {
            fclose($in);
            fclose($out);
            throw new \RuntimeException('Falha ao criptografar backup (final).');
        }
        hash_update($h, $cipher);
        $bytesOut += (int) fwrite($out, $cipher);

        fclose($in);
        fclose($out);

        $mac = hash_final($h, true);

        return [
            'v' => 1,
            'cipher' => 'AES-256-CBC',
            'kdf' => 'PBKDF2-SHA256',
            'iterations' => $iterations,
            'salt_b64' => base64_encode($salt),
            'iv_b64' => base64_encode($iv),
            'hmac_sha256_b64' => base64_encode($mac),
            'in' => basename($inFile),
            'out' => basename($outFile),
            'bytes_in' => $bytesIn,
            'bytes_out' => $bytesOut,
        ];
    }

    private function purgeTables(PDO $pdo, array $tables, callable $log): array
    {
        $totalDeleted = 0;
        $perTable = [];

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            $t = (string) $t;
            if ($t === '') {
                continue;
            }
            $qt = $this->quoteIdent($t);
            $before = $this->countRows($pdo, $qt);

            $deleted = 0;
            $ok = true;
            try {
                $pdo->exec('TRUNCATE TABLE ' . $qt);
                $deleted = $before;
            } catch (\Throwable $e) {
                try {
                    $deleted = $pdo->exec('DELETE FROM ' . $qt);
                } catch (\Throwable $e2) {
                    $ok = false;
                }
            }

            $after = $this->countRows($pdo, $qt);
            $row = [
                'before' => $before,
                'deleted' => $deleted,
                'after' => $after,
                'ok' => $ok && $after === 0,
            ];
            $perTable[$t] = $row;
            $totalDeleted += (int) $deleted;
            $log('purge_table', ['table' => $t] + $row);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        return [
            'total_deleted' => $totalDeleted,
            'tables' => $perTable,
        ];
    }

    private function verify(PDO $pdo, array $preserve, array $purge): array
    {
        $bad = [];
        foreach ($purge as $t) {
            $qt = $this->quoteIdent((string) $t);
            $c = $this->countRows($pdo, $qt);
            if ($c !== 0) {
                $bad[(string) $t] = $c;
            }
        }

        $usersCount = null;
        try {
            $usersCount = $this->countRows($pdo, $this->quoteIdent('users'));
        } catch (\Throwable) {
            $usersCount = null;
        }

        $preserveCounts = [];
        foreach ($preserve as $t) {
            $t = (string) $t;
            if ($t === '') {
                continue;
            }
            try {
                $preserveCounts[$t] = $this->countRows($pdo, $this->quoteIdent($t));
            } catch (\Throwable) {
                $preserveCounts[$t] = null;
            }
        }

        return [
            'ok' => $bad === [],
            'purge_not_empty' => $bad,
            'preserve_counts' => $preserveCounts,
            'users_count' => $usersCount,
        ];
    }

    private function seedMinimum(PDO $pdo): array
    {
        $seeded = [
            'company_profile' => false,
            'financial_categories' => false,
            'financial_cost_centers' => false,
            'financial_bank_accounts' => false,
        ];

        if ($this->hasTable($pdo, 'company_profile')) {
            $exists = $pdo->query('SELECT 1 FROM company_profile WHERE id = 1')->fetchColumn() !== false;
            if (!$exists) {
                $stmt = $pdo->prepare('INSERT INTO company_profile (id, legal_name, trade_name, brand_name, brand_tagline, cnpj, domain, website, primary_color, accent_color, font_name, meta_title, meta_description, meta_keywords, email_cipher, phones_cipher, whatsapp_cipher, address_cipher, favicon_path, favicon_mime, favicon_original_name, meta_image_path, meta_image_mime, meta_image_original_name, logo_light_path, logo_light_mime, logo_light_original_name, logo_dark_path, logo_dark_mime, logo_dark_original_name, updated_by, created_at, updated_at) VALUES (1, :legal_name, NULL, NULL, NULL, :cnpj, NULL, NULL, :primary_color, :accent_color, :font_name, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())');
                $stmt->execute([
                    ':legal_name' => 'Empresa',
                    ':cnpj' => '00000000000000',
                    ':primary_color' => '#293241',
                    ':accent_color' => '#ee6c4d',
                    ':font_name' => 'Helvetica',
                ]);
                $seeded['company_profile'] = true;
            }
        }

        if ($this->hasTable($pdo, 'financial_categories')) {
            $sql = "INSERT INTO financial_categories (company_id, name, type, color, active, created_at, updated_at)
VALUES
(1, 'Mensalidades', 'receivable', '#3B82F6', 1, NOW(), NOW()),
(1, 'Projetos', 'receivable', '#22C55E', 1, NOW(), NOW()),
(1, 'Serviços recorrentes', 'receivable', '#F59E0B', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)";
            $pdo->exec($sql);
            $seeded['financial_categories'] = true;
        }

        if ($this->hasTable($pdo, 'financial_cost_centers')) {
            $sql = "INSERT INTO financial_cost_centers (company_id, name, code, active, created_at, updated_at)
VALUES
(1, 'Operacional', 'OP', 1, NOW(), NOW()),
(1, 'Comercial', 'COM', 1, NOW(), NOW()),
(1, 'Projetos', 'PRJ', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)";
            $pdo->exec($sql);
            $seeded['financial_cost_centers'] = true;
        }

        if ($this->hasTable($pdo, 'financial_bank_accounts')) {
            $sql = "INSERT INTO financial_bank_accounts (company_id, bank_name, account_name, branch_number, account_number, pix_key, active, created_at, updated_at)
VALUES
(1, 'Conta principal', 'Conta principal TRAXTER', NULL, NULL, NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)";
            $pdo->exec($sql);
            $seeded['financial_bank_accounts'] = true;
        }

        return $seeded;
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
        $stmt->execute([':t' => $table]);
        return $stmt->fetchColumn() !== false;
    }

    private function countRows(PDO $pdo, string $qt): int
    {
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM ' . $qt);
        $v = $stmt->fetchColumn();
        return (int) $v;
    }

    private function quoteIdent(string $name): string
    {
        $name = str_replace('`', '``', $name);
        return '`' . $name . '`';
    }
}
