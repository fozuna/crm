<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\XlsxBuilder;

try {
    if (!class_exists(ZipArchive::class)) {
        echo "SKIP\n";
        exit(0);
    }

    $bytes = (new XlsxBuilder())->build(
        ['Vencimento', 'Cliente', 'Total'],
        [
            ['01/05/2026', 'Cliente A', 1234.56],
            ['02/05/2026', 'Cliente B', 78.9],
        ],
        'Financeiro'
    );

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_test_');
    if ($tmp === false) {
        throw new RuntimeException('Falha ao criar arquivo temporário.');
    }
    file_put_contents($tmp, $bytes);

    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        @unlink($tmp);
        throw new RuntimeException('Arquivo XLSX gerado não é um ZIP válido.');
    }

    foreach (['[Content_Types].xml', 'xl/workbook.xml', 'xl/styles.xml', 'xl/sharedStrings.xml', 'xl/worksheets/sheet1.xml'] as $entry) {
        if ($zip->locateName($entry) === false) {
            $zip->close();
            @unlink($tmp);
            throw new RuntimeException('Entrada ausente no XLSX: ' . $entry);
        }
    }

    $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
    @unlink($tmp);

    if ($sheet === '' || strpos($sheet, '<sheetData>') === false) {
        throw new RuntimeException('Worksheet inválida no XLSX.');
    }
    if (strpos($shared, 'Cliente A') === false || strpos($shared, 'Vencimento') === false) {
        throw new RuntimeException('Shared strings não contém os dados esperados.');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

