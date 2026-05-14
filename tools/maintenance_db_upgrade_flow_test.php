<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Core\Session;
use App\Repositories\AuditLogRepository;
use App\Services\DbUpgradeRunner;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

$pdo = DB::pdo();
$runner = new DbUpgradeRunner();

$inspect0 = $runner->inspect($pdo);
if (!is_array($inspect0) || !array_key_exists('pending', $inspect0)) {
    fail('Inspect inválido.');
}

Session::set('user_id', 1);

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
$cmd = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/db_upgrade_worker.php') . ' ' . escapeshellarg($jobId);
$out = [];
$code = 0;
exec($cmd, $out, $code);

$raw = @file_get_contents($jobFile);
$jobAfter = $raw !== false ? json_decode((string) $raw, true) : null;
if (!is_array($jobAfter)) {
    fail('Job não foi atualizado pelo worker.');
}
if (!in_array(($jobAfter['status'] ?? null), ['done', 'error'], true)) {
    fail('Status final inesperado: ' . (string) ($jobAfter['status'] ?? '')); 
}

$inspect1 = $runner->inspect($pdo);
if (($jobAfter['status'] ?? null) === 'done' && ($inspect1['pending'] ?? true)) {
    fail('Upgrade marcou done mas inspect ainda mostra pendências.');
}

$logs = (new AuditLogRepository())->list('maintenance', 0, 50);
$hasFinish = false;
foreach ($logs as $row) {
    if (($row['action'] ?? null) === 'db_upgrade_finish') {
        $data = is_array($row['data'] ?? null) ? $row['data'] : [];
        if (($data['job_id'] ?? null) === $jobId) {
            $hasFinish = true;
            break;
        }
    }
}
if (!$hasFinish) {
    fail('Log de auditoria db_upgrade_finish não encontrado para job ' . $jobId);
}

echo "OK\n";

