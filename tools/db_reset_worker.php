<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\DB;
use App\Repositories\AuditLogRepository;
use App\Services\DbResetRunner;

$jobId = (string) ($argv[1] ?? '');
if ($jobId === '' || preg_match('/^[a-f0-9]{16}$/', $jobId) !== 1) {
    exit(2);
}

$jobFile = __DIR__ . '/../storage/jobs/db-reset-' . $jobId . '.json';
if (!is_file($jobFile)) {
    exit(3);
}

$raw = @file_get_contents($jobFile);
$job = $raw !== false ? json_decode((string) $raw, true) : null;
if (!is_array($job)) {
    $job = ['job_id' => $jobId];
}

$actorId = isset($job['actor_id']) ? (int) $job['actor_id'] : 0;

$passphrase = '';
try {
    if (!isset($job['passphrase']) || !is_array($job['passphrase'])) {
        throw new RuntimeException('Passphrase ausente no job.');
    }
    $p = $job['passphrase'];
    $appKey = (string) Config::get('APP_KEY', '');
    if ($appKey === '' || $appKey === 'defina-uma-chave-unica-aqui') {
        throw new RuntimeException('APP_KEY inválida.');
    }
    $key = hash('sha256', $appKey, true);
    $iv = base64_decode((string) ($p['iv_b64'] ?? ''), true);
    $tag = base64_decode((string) ($p['tag_b64'] ?? ''), true);
    $cipher = base64_decode((string) ($p['cipher_b64'] ?? ''), true);
    if (!is_string($iv) || !is_string($tag) || !is_string($cipher) || $iv === '' || $tag === '' || $cipher === '') {
        throw new RuntimeException('Passphrase inválida no job.');
    }
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($plain) || $plain === '') {
        throw new RuntimeException('Falha ao descriptografar passphrase.');
    }
    $passphrase = $plain;
} catch (Throwable $e) {
    $job['status'] = 'error';
    $job['finished_at'] = date('c');
    $job['result'] = null;
    $job['error'] = (string) $e;
    $job['passphrase'] = null;
    @file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE));

    (new AuditLogRepository())->create('maintenance', 0, 'db_reset_finish', $actorId > 0 ? $actorId : null, [
        'job_id' => $jobId,
        'status' => $job['status'],
        'result' => null,
        'error' => $job['error'],
    ]);
    exit(1);
}

$log = static function (string $event, array $data = []) use ($jobId, $jobFile): void {
    $dir = __DIR__ . '/../storage/logs';
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
    $j = $raw !== false ? json_decode((string) $raw, true) : null;
    if (is_array($j)) {
        $j['last_event'] = $event;
        $j['updated_at'] = date('c');
        @file_put_contents($jobFile, json_encode($j, JSON_UNESCAPED_UNICODE));
    }
};

$runner = new DbResetRunner();
$res = null;
$err = null;

$preserve = [];
$rawPreserve = (string) Config::get('DB_RESET_PRESERVE_TABLES', '');
if ($rawPreserve !== '') {
    $parts = array_map('trim', explode(',', $rawPreserve));
    foreach ($parts as $p) {
        if ($p !== '') {
            $preserve[] = $p;
        }
    }
    $preserve = array_values(array_unique($preserve));
}

$dryRun = isset($job['dry_run']) ? (bool) $job['dry_run'] : false;

try {
    $res = $runner->run(DB::pdo(), [
        'passphrase' => $passphrase,
        'preserve_tables' => $preserve,
        'seed_minimum' => (bool) Config::get('DB_RESET_SEED_MINIMUM', true),
        'dry_run' => $dryRun,
    ], $log);
} catch (Throwable $e) {
    $err = (string) $e;
}

$job['status'] = $err === null && is_array($res) && ($res['ok'] ?? false) ? 'done' : 'error';
$job['finished_at'] = date('c');
$job['result'] = $res;
$job['error'] = $err;
$job['passphrase'] = null;

@file_put_contents($jobFile, json_encode($job, JSON_UNESCAPED_UNICODE));

(new AuditLogRepository())->create('maintenance', 0, 'db_reset_finish', $actorId > 0 ? $actorId : null, [
    'job_id' => $jobId,
    'status' => $job['status'],
    'result' => $res,
    'error' => $err,
]);

exit($job['status'] === 'done' ? 0 : 1);
