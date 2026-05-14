<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$app = new App\Core\Application();
$app->run();

