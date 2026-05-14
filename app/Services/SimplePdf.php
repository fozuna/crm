<?php
declare(strict_types=1);

namespace App\Services;

final class SimplePdf
{
    private array $pages = [];
    private int $fontSize = 12;

    public function addPage(): void
    {
        $this->pages[] = [];
    }

    public function setFontSize(int $size): void
    {
        $this->fontSize = max(8, min(24, $size));
    }

    public function text(int $x, int $y, string $text): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }

        $safe = $this->escape($text);
        $this->pages[count($this->pages) - 1][] = sprintf('BT /F1 %d Tf %d %d Td (%s) Tj ET', $this->fontSize, $x, $y, $safe);
    }

    public function output(): string
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }

        $objects = [];

        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";

        $kids = [];
        $pageObjNumbers = [];
        $contentObjNumbers = [];

        $baseObj = 3;
        foreach ($this->pages as $i => $contentLines) {
            $pageObj = $baseObj + ($i * 2);
            $contentObj = $pageObj + 1;
            $pageObjNumbers[] = $pageObj;
            $contentObjNumbers[] = $contentObj;
            $kids[] = $pageObj . ' 0 R';
        }

        $objects[] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($kids) . " >>";

        foreach ($this->pages as $i => $contentLines) {
            $pageObj = $pageObjNumbers[$i];
            $contentObj = $contentObjNumbers[$i];

            $objects[$pageObj - 1] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents {$contentObj} 0 R >>";

            $stream = implode("\n", $contentLines) . "\n";
            $objects[$contentObj - 1] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $i => $obj) {
            $objNum = $i + 1;
            $offsets[$objNum] = strlen($pdf);
            $pdf .= $objNum . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

        return $pdf;
    }

    private function escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        $text = preg_replace('/[^\x20-\x7E]/', ' ', $text);
        return (string) $text;
    }
}

