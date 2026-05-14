<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

final class XlsxBuilder
{
    public function build(array $headers, array $rows, string $sheetName = 'Relatorio'): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Extensão ZipArchive indisponível para exportação XLSX.');
        }

        $headers = array_values(array_map([$this, 'scalarToCell'], $headers));
        $rows = array_map(function ($row): array {
            $line = is_array($row) ? array_values($row) : [(string) $row];
            return array_map([$this, 'normalizeValue'], $line);
        }, $rows);

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Não foi possível abrir o pacote XLSX para escrita.');
        }

        [$sharedStringsXml, $sharedIndexes] = $this->sharedStringsXml($headers, $rows);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($headers, $rows, $sharedIndexes));
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        if ($bytes === '') {
            throw new RuntimeException('Falha ao gerar conteúdo XLSX.');
        }

        return $bytes;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>'
            . '<sheet name="' . $this->xml($sheetName) . '" sheetId="1" r:id="rId1"/>'
            . '</sheets>'
            . '</workbook>';
    }

    private function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sharedStringsXml(array $headers, array $rows): array
    {
        $strings = [];
        $index = [];

        $register = function (string $value) use (&$strings, &$index): void {
            if (array_key_exists($value, $index)) {
                return;
            }
            $index[$value] = count($strings);
            $strings[] = $value;
        };

        foreach ($headers as $header) {
            $register((string) $header);
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                if (!is_int($cell) && !is_float($cell)) {
                    $register((string) $cell);
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $value) {
            $xml .= '<si><t>' . $this->xml($value) . '</t></si>';
        }
        $xml .= '</sst>';

        return [$xml, $index];
    }

    private function sheetXml(array $headers, array $rows, array $sharedIndexes): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . '<sheetData>';

        $xml .= '<row r="1">';
        foreach ($headers as $i => $header) {
            $xml .= $this->stringCell(1, $i + 1, (string) $header, $sharedIndexes, 1);
        }
        $xml .= '</row>';

        foreach ($rows as $rIndex => $row) {
            $rowNo = $rIndex + 2;
            $xml .= '<row r="' . $rowNo . '">';
            foreach ($row as $cIndex => $value) {
                if (is_int($value) || is_float($value)) {
                    $style = $cIndex >= 5 ? 2 : 0;
                    $xml .= $this->numberCell($rowNo, $cIndex + 1, (float) $value, $style);
                    continue;
                }
                $xml .= $this->stringCell($rowNo, $cIndex + 1, (string) $value, $sharedIndexes, 0);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        return $xml;
    }

    private function stringCell(int $row, int $col, string $value, array $sharedIndexes, int $style): string
    {
        $ref = $this->cellRef($row, $col);
        $idx = $sharedIndexes[$value] ?? 0;
        return '<c r="' . $ref . '" t="s" s="' . $style . '"><v>' . $idx . '</v></c>';
    }

    private function numberCell(int $row, int $col, float $value, int $style): string
    {
        $ref = $this->cellRef($row, $col);
        return '<c r="' . $ref . '" s="' . $style . '"><v>' . $this->decimal($value) . '</v></c>';
    }

    private function cellRef(int $row, int $col): string
    {
        $letters = '';
        while ($col > 0) {
            $mod = ($col - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $col = intdiv($col - 1, 26);
        }
        return $letters . $row;
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function scalarToCell($value): string
    {
        return $this->normalizeText((string) $value);
    }

    private function normalizeValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value) && preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        return $this->normalizeText((string) $value);
    }

    private function normalizeText(string $value): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
