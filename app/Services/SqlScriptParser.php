<?php
declare(strict_types=1);

namespace App\Services;

final class SqlScriptParser
{
    public function split(string $sql): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $sql) ?: [];
        $delimiter = ';';
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches) === 1) {
                $delimiter = trim((string) ($matches[1] ?? ';'));
                continue;
            }

            $buffer .= $line . "\n";
            $candidate = rtrim($buffer);
            if ($delimiter !== '' && str_ends_with($candidate, $delimiter)) {
                $statement = trim(substr($candidate, 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
