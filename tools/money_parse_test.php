<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\Money;

function assertClose(float $actual, float $expected, string $label): void
{
    if (abs($actual - $expected) > 0.0001) {
        throw new RuntimeException($label . ' esperado ' . $expected . ' obtido ' . $actual);
    }
}

try {
    assertClose(Money::parseBRL('R$ 1.500,50'), 1500.50, 'R$ 1.500,50');
    assertClose(Money::parseBRL('1.500,50'), 1500.50, '1.500,50');
    assertClose(Money::parseBRL('1500,50'), 1500.50, '1500,50');
    assertClose(Money::parseBRL('1.000,00'), 1000.00, '1.000,00');
    assertClose(Money::parseBRL('1.000.000,00'), 1000000.00, '1.000.000,00');
    assertClose(Money::parseBRL('1.50'), 1.50, '1.50');
    assertClose(Money::parseBRL('1.500'), 1500.00, '1.500');
    assertClose(Money::parseBRL('0'), 0.00, '0');
    assertClose(Money::parseBRL(''), 0.00, '');

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

