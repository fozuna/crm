<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

throw new App\Services\DatabaseStructureOutOfSyncException('ref123', ['pending' => true]);
