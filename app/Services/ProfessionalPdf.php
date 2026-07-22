<?php
declare(strict_types=1);

namespace App\Services;

final class ProfessionalPdf
{
    private array $pages = [];
    private int $fontSize = 12;
    private string $fontKey = 'F1';
    private array $images = [];
    private array $imagePaths = [];
    private array $temporaryFiles = [];

    public function addPage(): void
    {
        $this->pages[] = [];
    }

    public function setFontSize(int $size): void
    {
        $this->fontSize = max(8, min(24, $size));
    }

    public function setFont(string $key, int $size): void
    {
        $this->fontKey = $key;
        $this->setFontSize($size);
    }

    public function text(int $x, int $y, string $text, ?float $wordSpacing = null): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }
        $safe = $this->escape($text);
        $tw = '';
        if (is_float($wordSpacing)) {
            $tw = sprintf(' %.3f Tw', $wordSpacing);
        }
        $this->pages[count($this->pages) - 1][] = sprintf('BT /%s %d Tf%s %d %d Td (%s) Tj ET', $this->fontKey, $this->fontSize, $tw, $x, $y, $safe);
    }

    public function line(int $x1, int $y1, int $x2, int $y2): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }
        $this->pages[count($this->pages) - 1][] = sprintf('%d %d m %d %d l S', $x1, $y1, $x2, $y2);
    }

    public function setStrokeColor(int $r, int $g, int $b): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }
        $this->pages[count($this->pages) - 1][] = sprintf('%.3f %.3f %.3f RG', $r / 255, $g / 255, $b / 255);
    }

    public function setLineWidth(float $w): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }
        $this->pages[count($this->pages) - 1][] = sprintf('%.3f w', max(0.1, min(4.0, $w)));
    }

    public function setFillColor(int $r, int $g, int $b): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }
        $this->pages[count($this->pages) - 1][] = sprintf('%.3f %.3f %.3f rg', $r / 255, $g / 255, $b / 255);
    }

    public function rect(int $x, int $y, int $w, int $h, string $style = 'S'): void
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }
        $op = $style === 'F' ? 'f' : ($style === 'DF' ? 'B' : 'S');
        $this->pages[count($this->pages) - 1][] = sprintf('%d %d %d %d re %s', $x, $y, $w, $h, $op);
    }

    public function imageFromFile(int $x, int $y, int $w, int $h, string $filePath): void
    {
        $key = sha1($filePath);
        $name = $this->imagePaths[$key] ?? null;
        if (!is_string($name)) {
            $jpeg = $this->toJpeg($filePath);
            if ($jpeg === null) {
                return;
            }
            $name = 'Im' . (count($this->images) + 1);
            $this->images[$name] = $jpeg;
            $this->imagePaths[$key] = $name;
        }

        if (count($this->pages) === 0) {
            $this->addPage();
        }

        $this->pages[count($this->pages) - 1][] = sprintf('q %d 0 0 %d %d %d cm /%s Do Q', $w, $h, $x, $y, $name);
    }

    public function output(): string
    {
        if (count($this->pages) === 0) {
            $this->addPage();
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';

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

        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

        $fontObjNum1 = $baseObj + (count($this->pages) * 2);
        $fontObjNum2 = $fontObjNum1 + 1;

        $imageObjStart = $fontObjNum2 + 1;
        $imageObjNums = [];
        $i = 0;
        foreach (array_keys($this->images) as $name) {
            $imageObjNums[$name] = $imageObjStart + $i;
            $i++;
        }

        foreach ($this->pages as $i => $contentLines) {
            $pageObj = $pageObjNumbers[$i];
            $contentObj = $contentObjNumbers[$i];

            $xObjects = '';
            if (count($this->images) > 0) {
                $pairs = [];
                foreach ($imageObjNums as $name => $objNum) {
                    $pairs[] = '/' . $name . ' ' . $objNum . ' 0 R';
                }
                $xObjects = ' /XObject << ' . implode(' ', $pairs) . ' >>';
            }

            $objects[$pageObj - 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 ' . $fontObjNum1 . ' 0 R /F2 ' . $fontObjNum2 . ' 0 R >>' . $xObjects . ' >> /Contents ' . $contentObj . ' 0 R >>';

            $stream = implode("\n", $contentLines) . "\n";
            $objects[$contentObj - 1] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[$fontObjNum1 - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontObjNum2 - 1] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->images as $name => $img) {
            $objNum = $imageObjNums[$name];
            $stream = $img['data'];
            $dict = '<< /Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($stream) . ' >>';
            $objects[$objNum - 1] = $dict . "\nstream\n" . $stream . "\nendstream";
        }

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

    public function __destruct()
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_string($file) && $file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function escape(string $text): string
    {
        $converted = $this->encodeWinAnsi($text);

        $converted = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', (string) $converted);
        $converted = str_replace('\\', '\\\\', (string) $converted);
        $converted = str_replace('(', '\\(', (string) $converted);
        $converted = str_replace(')', '\\)', (string) $converted);
        return (string) $converted;
    }

    private function encodeWinAnsi(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $normalized = $text;
        if (preg_match('//u', $normalized) !== 1) {
            if (function_exists('mb_convert_encoding')) {
                $candidate = @mb_convert_encoding($normalized, 'UTF-8', 'Windows-1252');
                if (is_string($candidate) && $candidate !== '' && preg_match('//u', $candidate) === 1) {
                    $normalized = $candidate;
                }
            } else {
                $normalized = utf8_encode($normalized);
            }
        }

        if (function_exists('iconv')) {
            $tmp = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $normalized);
            if (is_string($tmp) && $tmp !== '') {
                return $tmp;
            }
        }

        return $normalized;
    }

    private function toJpeg(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $sourcePath = $this->resolveRenderableImagePath($path);
        if ($sourcePath === '') {
            return null;
        }

        $bytes = @file_get_contents($sourcePath);
        if (!is_string($bytes) || $bytes === '') {
            return null;
        }

        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        // JPEG não tem canal alfa: compor sobre um fundo branco antes de
        // codificar evita que áreas "transparentes" do PNG (ex.: logo)
        // herdem a cor de preenchimento subjacente do canvas — que no
        // caso dos logos gerados por LogoProcessor é preta — e apareçam
        // como um bloco preto sólido no PDF final.
        $flattened = imagecreatetruecolor($w, $h);
        if ($flattened === false) {
            $flattened = $img;
        } else {
            $white = imagecolorallocate($flattened, 255, 255, 255);
            imagefilledrectangle($flattened, 0, 0, $w, $h, $white);
            imagealphablending($flattened, true);
            imagecopy($flattened, $img, 0, 0, 0, 0, $w, $h);
            imagedestroy($img);
        }

        ob_start();
        imagejpeg($flattened, null, 92);
        $jpeg = (string) ob_get_clean();
        imagedestroy($flattened);

        if ($jpeg === '') {
            return null;
        }

        return ['data' => $jpeg, 'w' => $w, 'h' => $h];
    }

    private function resolveRenderableImagePath(string $path): string
    {
        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) @mime_content_type($path);
        }
        if ($mime === '') {
            $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
                default => '',
            };
        }

        if ($mime === 'image/svg+xml') {
            return $this->rasterizeSvgIfPossible($path);
        }

        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true) ? $path : '';
    }

    private function rasterizeSvgIfPossible(string $path): string
    {
        if (!class_exists(\Imagick::class)) {
            return '';
        }

        try {
            $imagick = new \Imagick();
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            $imagick->readImage($path);
            $imagick->setImageFormat('png32');

            $tmp = tempnam(sys_get_temp_dir(), 'traxter_pdf_logo_');
            if (!is_string($tmp) || $tmp === '') {
                $imagick->clear();
                $imagick->destroy();
                return '';
            }

            $target = $tmp . '.png';
            @unlink($tmp);
            if (!$imagick->writeImage($target) || !is_file($target)) {
                $imagick->clear();
                $imagick->destroy();
                return '';
            }

            $imagick->clear();
            $imagick->destroy();
            $this->temporaryFiles[] = $target;
            return $target;
        } catch (\Throwable) {
            return '';
        }
    }
}
