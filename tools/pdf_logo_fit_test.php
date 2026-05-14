<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\ProposalPdfGenerator;

try {
    $ref = new ReflectionClass(ProposalPdfGenerator::class);
    $m = $ref->getMethod('fitBox');
    $m->setAccessible(true);

    [$w, $h] = $m->invoke(null, 300, 100, 200, 32);
    if ($w !== 96 || $h !== 32) {
        throw new RuntimeException('Fit incorreto para 300x100 em 200x32. Obtido: ' . $w . 'x' . $h);
    }

    [$w2, $h2] = $m->invoke(null, 100, 300, 200, 32);
    if ($w2 !== 10 || $h2 !== 32) {
        throw new RuntimeException('Fit incorreto para 100x300 em 200x32. Obtido: ' . $w2 . 'x' . $h2);
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

