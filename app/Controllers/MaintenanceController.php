<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
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
                $res = $runner->run(\App\Core\DB::pdo());
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
        $dir = __DIR__ . '/../../storage/jobs';
        if (!is_dir($dir)) {
            return null;
        }
        $files = glob($dir . '/db-upgrade-*.json') ?: [];
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
}
