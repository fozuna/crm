<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Config;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Services\DbLifecycleLogger;
use App\Services\DbResetRunner;
use App\Services\DbUpgradeRunner;

final class MaintenanceController
{
    public function checkDbUpgrade(Request $request): void
    {
        $runner = new DbUpgradeRunner();
        $inspect = $runner->inspect(\App\Core\DB::pdo());
        Response::json($inspect);
    }

    public function startDbUpgrade(Request $request): void
    {
        $runner = new DbUpgradeRunner();
        $inspect = $runner->inspect(\App\Core\DB::pdo());
        if (!($inspect['pending'] ?? false)) {
            Response::json(['ok' => false, 'message' => 'Nenhuma atualização pendente.'], 409);
        }

        $runningJob = $this->findRunningJobId();
        if ($runningJob !== null) {
            Response::json(['ok' => false, 'message' => 'Já existe uma atualização em execução.', 'job_id' => $runningJob], 409);
        }

        $jobId = bin2hex(random_bytes(8));
        $dir = __DIR__ . '/../../storage/jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            Response::json(['ok' => false, 'message' => 'Falha ao preparar diretório de jobs.'], 500);
        }

        $jobFile = $dir . '/db-upgrade-' . $jobId . '.json';
        $actorId = (int) Session::get('user_id', 0);
        $payload = [
            'job_id' => $jobId,
            'status' => 'running',
            'started_at' => date('c'),
            'finished_at' => null,
            'actor_id' => $actorId,
            'result' => null,
            'error' => null,
        ];
        @file_put_contents($jobFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

        (new AuditLogRepository())->create('maintenance', 0, 'db_upgrade_start', $actorId > 0 ? $actorId : null, [
            'job_id' => $jobId,
            'inspect' => $inspect,
        ]);

        $spawned = $this->spawnWorker($jobId);
        if (!$spawned) {
            $res = null;
            $err = null;
            try {
                $logger = new DbLifecycleLogger();
                $res = $runner->run(\App\Core\DB::pdo(), static function (string $event, array $context = [], ?\Throwable $exception = null) use ($logger, $jobId): void {
                    $logger->write('maintenance_db_upgrade_' . $event, [
                        'job_id' => $jobId,
                        'context' => $context,
                    ], $exception);
                });
            } catch (\Throwable $e) {
                $err = (string) $e;
            }
            $payload['status'] = $err === null && is_array($res) && ($res['ok'] ?? false) ? 'done' : 'error';
            $payload['finished_at'] = date('c');
            $payload['result'] = $res;
            $payload['error'] = $err;
            @file_put_contents($jobFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

            (new AuditLogRepository())->create('maintenance', 0, 'db_upgrade_finish', $actorId > 0 ? $actorId : null, [
                'job_id' => $jobId,
                'status' => $payload['status'],
                'result' => $res,
                'error' => $err,
            ]);
        }

        Response::json(['ok' => true, 'job_id' => $jobId, 'spawned' => $spawned]);
    }

    public function dbUpgradeStatus(Request $request, array $params): void
    {
        $jobId = (string) ($params['jobId'] ?? '');
        if ($jobId === '' || preg_match('/^[a-f0-9]{16}$/', $jobId) !== 1) {
            Response::json(['ok' => false, 'message' => 'Job inválido.'], 400);
        }

        $jobFile = __DIR__ . '/../../storage/jobs/db-upgrade-' . $jobId . '.json';
        if (!is_file($jobFile)) {
            Response::json(['ok' => false, 'message' => 'Job não encontrado.'], 404);
        }

        $raw = @file_get_contents($jobFile);
        $data = $raw !== false ? json_decode((string) $raw, true) : null;
        if (!is_array($data)) {
            Response::json(['ok' => false, 'message' => 'Job corrompido.'], 500);
        }
        Response::json(['ok' => true, 'job' => $data]);
    }

    public function dbResetPlan(Request $request): void
    {
        if (!(bool) Config::get('DB_RESET_ENABLED', false)) {
            Response::json(['ok' => false, 'message' => 'Ação desabilitada.'], 403);
        }

        $preserve = $this->configPreserveTables();
        $runner = new DbResetRunner();
        $inspect = $runner->inspect(\App\Core\DB::pdo(), $preserve);

        $usersCount = null;
        try {
            $usersCount = (int) \App\Core\DB::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (\Throwable) {
            $usersCount = null;
        }

        Response::json([
            'ok' => true,
            'target' => (string) Config::get('DB_RESET_TARGET', 'production'),
            'inspect' => $inspect,
            'users_count' => $usersCount,
        ]);
    }

    public function startDbReset(Request $request): void
    {
        if (!(bool) Config::get('DB_RESET_ENABLED', false)) {
            Response::json(['ok' => false, 'message' => 'Ação desabilitada.'], 403);
        }

        $body = $request->jsonBody();
        $confirm = (string) ($body['confirm'] ?? $request->input('confirm', ''));
        $target = (string) ($body['target'] ?? $request->input('target', ''));
        $passphrase = (string) ($body['passphrase'] ?? $request->input('passphrase', ''));
        $expectedUsersCount = (int) ($body['users_count'] ?? (int) $request->input('users_count', 0));
        $dryRun = (bool) ($body['dry_run'] ?? (bool) $request->input('dry_run', false));

        $requiredConfirm = (string) Config::get('DB_RESET_CONFIRM_PHRASE', 'RESETAR-BANCO');
        if (!hash_equals($requiredConfirm, $confirm)) {
            Response::json(['ok' => false, 'message' => 'Confirmação inválida.'], 400);
        }

        $cfgTarget = (string) Config::get('DB_RESET_TARGET', 'production');
        if (!hash_equals($cfgTarget, $target)) {
            Response::json(['ok' => false, 'message' => 'Target inválido.'], 400);
        }

        $pdo = \App\Core\DB::pdo();
        $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $allowedDb = (string) Config::get('DB_RESET_ALLOWED_DB', '');
        if ($allowedDb !== '' && !hash_equals($allowedDb, $dbName)) {
            Response::json(['ok' => false, 'message' => 'Banco de destino não autorizado.'], 403);
        }

        $allowedHost = (string) Config::get('DB_RESET_ALLOWED_HOST', '');
        if ($allowedHost !== '') {
            $curHost = (string) (parse_url((string) Config::get('APP_URL', ''), PHP_URL_HOST) ?? '');
            if ($curHost === '' || !hash_equals($allowedHost, $curHost)) {
                Response::json(['ok' => false, 'message' => 'Host de destino não autorizado.'], 403);
            }
        }

        if (strlen($passphrase) < 12) {
            Response::json(['ok' => false, 'message' => 'Passphrase fraca (mínimo 12 caracteres).'], 400);
        }

        $actualUsersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($expectedUsersCount <= 0 || $expectedUsersCount !== $actualUsersCount) {
            Response::json(['ok' => false, 'message' => 'users_count divergente.'], 400);
        }

        $runningJob = $this->findRunningJobIdByPrefix('db-reset-');
        if ($runningJob !== null) {
            Response::json(['ok' => false, 'message' => 'Já existe um reset em execução.', 'job_id' => $runningJob], 409);
        }

        $jobId = bin2hex(random_bytes(8));
        $dir = __DIR__ . '/../../storage/jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            Response::json(['ok' => false, 'message' => 'Falha ao preparar diretório de jobs.'], 500);
        }

        $encPass = $this->encryptPassphrase($passphrase);
        $jobFile = $dir . '/db-reset-' . $jobId . '.json';
        $actorId = (int) Session::get('user_id', 0);
        $payload = [
            'job_id' => $jobId,
            'status' => 'running',
            'started_at' => date('c'),
            'finished_at' => null,
            'actor_id' => $actorId,
            'last_event' => null,
            'result' => null,
            'error' => null,
            'target' => $cfgTarget,
            'db' => $dbName,
            'users_count' => $actualUsersCount,
            'passphrase' => $encPass,
            'dry_run' => $dryRun,
        ];
        @file_put_contents($jobFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

        (new AuditLogRepository())->create('maintenance', 0, 'db_reset_start', $actorId > 0 ? $actorId : null, [
            'job_id' => $jobId,
            'target' => $cfgTarget,
            'db' => $dbName,
            'users_count' => $actualUsersCount,
        ]);

        $spawned = $this->spawnResetWorker($jobId);
        if (!$spawned) {
            $runner = new DbResetRunner();
            $res = null;
            $err = null;
            try {
                $log = $this->jobLogger($jobId, $jobFile);
                $res = $runner->run($pdo, [
                    'passphrase' => $passphrase,
                    'preserve_tables' => $this->configPreserveTables(),
                    'seed_minimum' => (bool) Config::get('DB_RESET_SEED_MINIMUM', true),
                    'dry_run' => $dryRun,
                ], $log);
            } catch (\Throwable $e) {
                $err = (string) $e;
            }
            $payload['status'] = $err === null && is_array($res) && ($res['ok'] ?? false) ? 'done' : 'error';
            $payload['finished_at'] = date('c');
            $payload['result'] = $res;
            $payload['error'] = $err;
            $payload['passphrase'] = null;
            @file_put_contents($jobFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

            (new AuditLogRepository())->create('maintenance', 0, 'db_reset_finish', $actorId > 0 ? $actorId : null, [
                'job_id' => $jobId,
                'status' => $payload['status'],
                'result' => $res,
                'error' => $err,
            ]);
        }

        Response::json(['ok' => true, 'job_id' => $jobId, 'spawned' => $spawned]);
    }

    public function dbResetStatus(Request $request, array $params): void
    {
        $jobId = (string) ($params['jobId'] ?? '');
        if ($jobId === '' || preg_match('/^[a-f0-9]{16}$/', $jobId) !== 1) {
            Response::json(['ok' => false, 'message' => 'Job inválido.'], 400);
        }

        $jobFile = __DIR__ . '/../../storage/jobs/db-reset-' . $jobId . '.json';
        if (!is_file($jobFile)) {
            Response::json(['ok' => false, 'message' => 'Job não encontrado.'], 404);
        }

        $raw = @file_get_contents($jobFile);
        $data = $raw !== false ? json_decode((string) $raw, true) : null;
        if (!is_array($data)) {
            Response::json(['ok' => false, 'message' => 'Job corrompido.'], 500);
        }
        $data['passphrase'] = null;

        Response::json([
            'ok' => true,
            'job' => $data,
            'log_file' => 'storage/logs/db_reset_' . $jobId . '.log',
        ]);
    }

    private function spawnWorker(string $jobId): bool
    {
        $script = __DIR__ . '/../../tools/db_upgrade_worker.php';
        if (!is_file($script)) {
            return false;
        }

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $cwd = dirname($script);

        if (PHP_OS_FAMILY === 'Windows') {
            if (!function_exists('popen')) {
                return false;
            }
            $cmd = 'cmd /c start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId);
            $p = @popen($cmd, 'r');
            if (!is_resource($p)) {
                return false;
            }
            @pclose($p);
            return true;
        }

        if (!function_exists('proc_open')) {
            return false;
        }

        $cmd = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId) . ' >/dev/null 2>&1 &';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptorspec, $pipes, $cwd);
        if (!is_resource($proc)) {
            return false;
        }
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                @fclose($p);
            }
        }
        @proc_close($proc);
        return true;
    }

    private function findRunningJobId(): ?string
    {
        return $this->findRunningJobIdByPrefix('db-upgrade-');
    }

    private function findRunningJobIdByPrefix(string $prefix): ?string
    {
        $dir = __DIR__ . '/../../storage/jobs';
        if (!is_dir($dir)) {
            return null;
        }
        $files = glob($dir . '/' . $prefix . '*.json') ?: [];
        foreach ($files as $f) {
            $raw = @file_get_contents((string) $f);
            $data = $raw !== false ? json_decode((string) $raw, true) : null;
            if (!is_array($data)) {
                continue;
            }
            if (($data['status'] ?? null) === 'running' && is_string($data['job_id'] ?? null)) {
                return (string) $data['job_id'];
            }
        }
        return null;
    }

    private function spawnResetWorker(string $jobId): bool
    {
        $script = __DIR__ . '/../../tools/db_reset_worker.php';
        if (!is_file($script)) {
            return false;
        }

        $php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $cwd = dirname($script);

        if (PHP_OS_FAMILY === 'Windows') {
            if (!function_exists('popen')) {
                return false;
            }
            $cmd = 'cmd /c start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId);
            $p = @popen($cmd, 'r');
            if (!is_resource($p)) {
                return false;
            }
            @pclose($p);
            return true;
        }

        if (!function_exists('proc_open')) {
            return false;
        }

        $cmd = 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($jobId) . ' >/dev/null 2>&1 &';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptorspec, $pipes, $cwd);
        if (!is_resource($proc)) {
            return false;
        }
        foreach ($pipes as $p) {
            if (is_resource($p)) {
                @fclose($p);
            }
        }
        @proc_close($proc);
        return true;
    }

    private function jobLogger(string $jobId, string $jobFile): callable
    {
        return function (string $event, array $data = []) use ($jobId, $jobFile): void {
            $dir = __DIR__ . '/../../storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $row = [
                'ts' => date('c'),
                'event' => $event,
                'data' => $data,
            ];
            @file_put_contents($dir . '/db_reset_' . $jobId . '.log', json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            $raw = @file_get_contents($jobFile);
            $job = $raw !== false ? json_decode((string) $raw, true) : null;
            if (is_array($job)) {
                $job['last_event'] = $event;
                $job['updated_at'] = date('c');
                @file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE));
            }
        };
    }

    private function encryptPassphrase(string $passphrase): array
    {
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('OpenSSL indisponível.');
        }
        $appKey = (string) Config::get('APP_KEY', '');
        if ($appKey === '' || $appKey === 'defina-uma-chave-unica-aqui') {
            throw new \RuntimeException('APP_KEY inválida.');
        }
        $key = hash('sha256', $appKey, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($passphrase, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Falha ao criptografar passphrase.');
        }
        return [
            'v' => 1,
            'alg' => 'AES-256-GCM',
            'iv_b64' => base64_encode($iv),
            'tag_b64' => base64_encode($tag),
            'cipher_b64' => base64_encode($cipher),
        ];
    }

    private function configPreserveTables(): array
    {
        $raw = (string) Config::get('DB_RESET_PRESERVE_TABLES', '');
        if ($raw === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $raw));
        $out = [];
        foreach ($parts as $p) {
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return array_values(array_unique($out));
    }
}
