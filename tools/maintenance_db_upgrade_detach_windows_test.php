<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\AuditLogRepository;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

if (PHP_OS_FAMILY !== 'Windows') {
    echo "SKIP\n";
    exit(0);
}

$pdo = DB::pdo();

$jobId = bin2hex(random_bytes(8));
$dir = __DIR__ . '/../storage/jobs';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
if (!is_dir($dir)) {
    fail('Falha ao criar storage/jobs');
}

$jobFile = $dir . '/db-upgrade-' . $jobId . '.json';
$job = [
    'job_id' => $jobId,
    'status' => 'running',
    'started_at' => date('c'),
    'finished_at' => null,
    'actor_id' => 1,
    'result' => null,
    'error' => null,
];
@file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE));

$php = PHP_BINARY !== '' ? PHP_BINARY : 'php';
$worker = __DIR__ . '/db_upgrade_worker.php';
if (!is_file($worker)) {
    fail('Worker não encontrado.');
}

$cmd = 'cmd /c start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($jobId);
$p = @popen($cmd, 'r');
if (!is_resource($p)) {
    fail('Falha ao iniciar processo em background.');
}
@pclose($p);

$deadline = microtime(true) + 10.0;
do {
    usleep(200000);
    $raw = @file_get_contents($jobFile);
    $data = $raw !== false ? json_decode((string) $raw, true) : null;
    if (is_array($data) && ($data['status'] ?? null) !== 'running') {
        break;
    }
} while (microtime(true) < $deadline);

$raw = @file_get_contents($jobFile);
$final = $raw !== false ? json_decode((string) $raw, true) : null;
if (!is_array($final)) {
    fail('Job inválido após execução.');
}
if (($final['status'] ?? null) === 'running') {
    fail('Job não finalizou em tempo (detach pode ter falhado).');
}
if (!in_array(($final['status'] ?? null), ['done', 'error'], true)) {
    fail('Status final inesperado: ' . (string) ($final['status'] ?? '')); 
}

$logs = (new AuditLogRepository())->list('maintenance', 0, 50);
$hasFinish = false;
foreach ($logs as $row) {
    if (($row['action'] ?? null) === 'db_upgrade_finish') {
        $d = is_array($row['data'] ?? null) ? $row['data'] : [];
        if (($d['job_id'] ?? null) === $jobId) {
            $hasFinish = true;
            break;
        }
    }
}
if (!$hasFinish) {
    fail('Auditoria db_upgrade_finish não encontrada para job ' . $jobId);
}

echo "OK\n";

