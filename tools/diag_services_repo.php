<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\ServiceRepository;

try {
    $repo = new ServiceRepository();
    $res = $repo->paginated(['q' => '', 'status' => '', 'type' => '', 'sort' => 'name_asc'], 1, 20);
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    $active = $repo->activeList(true);
    echo "\nACTIVE\n";
    echo json_encode($active, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e . "\n");
    exit(1);
}

