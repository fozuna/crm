<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Config;
use App\Core\Request;

function fail(string $msg): void
{
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

try {
    $old = Config::all();

    $tests = [
        'http://crmtraxter.test/gestor' => '/gestor',
        'https://crmtraxter.test/gestor/' => '/gestor',
        '/gestor' => '/gestor',
        'gestor' => '/gestor',
        '' => '/gestor',
    ];

    foreach ($tests as $in => $expected) {
        $items = $old;
        $items['APP_BASE_PATH'] = $in;
        Config::setAll($items);
        $_SERVER['SCRIPT_NAME'] = '/gestor/index.php';

        $req = new Request();
        $got = $req->basePath();
        if ($got !== $expected) {
            fail('APP_BASE_PATH=' . var_export($in, true) . ' esperado ' . var_export($expected, true) . ' recebido ' . var_export($got, true));
        }
    }

    Config::setAll($old);
    echo "OK\n";
} catch (Throwable $e) {
    fail($e->getMessage());
}
