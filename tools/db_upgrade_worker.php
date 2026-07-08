<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Services\DbLifecycleLogger;
use App\Services\DbUpgradeRunner;

$jobId = (string) ($argv[1] ?? '');
if ($jobId === '' || preg_match('/^[a-f0-9]{16}$/', $jobId) !== 1) {
    exit(2);
}

$jobFile = __DIR__ . '/../storage/jobs/db-upgrade-' . $jobId . '.json';
if (!is_file($jobFile)) {
    exit(3);
}

$raw = @file_get_contents($jobFile);
$job = $raw !== false ? json_decode((string) $raw, true) : null;
if (!is_array($job)) {
    $job = ['job_id' => $jobId];
}

$actorId = isset($job['actor_id']) ? (int) $job['actor_id'] : 0;

$runner = new DbUpgradeRunner();
$res = null;
$err = null;

try {
    $logger = new DbLifecycleLogger();
    $res = $runner->run(DB::pdo(), static function (string $event, array $context = [], ?\Throwable $exception = null) use ($logger, $jobId): void {
        $logger->write('worker_db_upgrade_' . $event, [
            'job_id' => $jobId,
            'context' => $context,
        ], $exception);
    });
} catch (Throwable $e) {
    $err = (string) $e;
}

$job['status'] = $err === null && is_array($res) && ($res['ok'] ?? false) ? 'done' : 'error';
$job['finished_at'] = date('c');
$job['result'] = $res;
$job['error'] = $err;

@file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE));

(new AuditLogRepository())->create('maintenance', 0, 'db_upgrade_finish', $actorId > 0 ? $actorId : null, [
    'job_id' => $jobId,
    'status' => $job['status'],
    'result' => $res,
    'error' => $err,
]);

exit($job['status'] === 'done' ? 0 : 1);
