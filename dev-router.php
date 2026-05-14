<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';

if (preg_match('#^/(app|config|database|resources|storage)(/|$)#', $path) === 1) {
    http_response_code(403);
    echo '403';
    exit;
}
$full = __DIR__ . $path;

if (is_file($full)) {
    return false;
}

require __DIR__ . '/index.php';
