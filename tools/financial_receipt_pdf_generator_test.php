<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Services\FinancialReceiptPdfGenerator;

function assertContainsText(string $pdf, string $text, string $message): void
{
    if (strpos($pdf, $text) === false) {
        throw new RuntimeException($message . ' Texto ausente: ' . $text);
    }
}

function pageCount(string $pdf): int
{
    if (preg_match('~/Type /Pages\b[\s\S]*?/Count (\d+)~', $pdf, $m) === 1) {
        return (int) $m[1];
    }
    return substr_count($pdf, '/Type /Page');
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createRasterLogo(string $format, int $width, int $height): string
{
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('SKIP_RASTER');
    }

    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Falha ao criar imagem de teste.');
    }

    $background = imagecolorallocate($image, 15, 23, 42);
    $foreground = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, $width, $height, $background);
    imagefilledrectangle($image, 8, 8, max(9, $width - 8), max(9, $height - 8), $foreground);

    $tmp = tempnam(sys_get_temp_dir(), 'traxter_receipt_logo_');
    if (!is_string($tmp) || $tmp === '') {
        imagedestroy($image);
        throw new RuntimeException('Falha ao preparar arquivo temporario.');
    }

    $path = $tmp . '.' . $format;
    @unlink($tmp);
    $saved = $format === 'jpg' ? imagejpeg($image, $path, 92) : imagepng($image, $path, 6);
    imagedestroy($image);

    if (!$saved || !is_file($path)) {
        throw new RuntimeException('Falha ao gravar logo de teste.');
    }

    return $path;
}

function createSvgLogo(int $width, int $height): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'traxter_receipt_logo_svg_');
    if (!is_string($tmp) || $tmp === '') {
        throw new RuntimeException('Falha ao preparar SVG temporario.');
    }

    $path = $tmp . '.svg';
    @unlink($tmp);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">' .
        '<rect width="' . $width . '" height="' . $height . '" rx="18" fill="#0f172a"/>' .
        '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="28" fill="#ffffff">TRAXTER</text>' .
        '</svg>';
    if (@file_put_contents($path, $svg) === false) {
        throw new RuntimeException('Falha ao gravar SVG temporario.');
    }

    return $path;
}

function extractImageTransform(string $pdf): ?array
{
    if (preg_match('~q (\d+) 0 0 (\d+) (\d+) (\d+) cm /Im\d+ Do Q~', $pdf, $m) !== 1) {
        return null;
    }

    return [
        'w' => (int) $m[1],
        'h' => (int) $m[2],
        'x' => (int) $m[3],
        'y' => (int) $m[4],
    ];
}

try {
    $branding = [
        'company_name' => 'TRAXTER',
        'logo_path' => '',
        'logo_mime' => '',
        'primary_color' => '#293241',
        'accent_color' => '#0ea5a4',
    ];

    $receivable = [
        'id' => 18,
        'client_name' => 'Cliente Exemplo',
        'client_company' => 'Cliente Exemplo LTDA',
        'project_title' => 'Implantacao do Portal Corporativo',
        'description' => 'Servico de implantacao, parametrizacao e treinamento do portal corporativo para o time interno do cliente.',
        'title' => 'Recebimento Portal',
        'invoice_number' => 'NF-100',
        'bank_name' => 'Banco Exemplo',
        'account_name' => 'Conta Principal',
        'competence_date' => '2026-05-10',
        'external_reference' => 'REF-15',
    ];

    $receipt = [
        'id' => 7,
        'amount_received' => 180.00,
        'interest_amount' => 10.00,
        'fine_amount' => 5.00,
        'discount_amount' => 2.50,
        'payment_method' => 'PIX',
        'payment_date' => '2026-05-10',
        'transaction_reference' => 'TX123',
        'observation' => 'Pagamento confirmado e conciliado.',
    ];

    $pdf = (new FinancialReceiptPdfGenerator())->build($branding, $receivable, $receipt);
    assertContainsText($pdf, 'RECIBO DE PAGAMENTO', 'Titulo principal nao encontrado.');
    assertContainsText($pdf, 'NOME DO CLIENTE', 'Campo de cliente nao encontrado.');
    assertContainsText($pdf, 'SERVICO QUE GEROU AQUELE RECEBIMENTO', 'Campo de servico nao encontrado.');
    assertContainsText($pdf, 'Cliente Exemplo LTDA', 'Nome do cliente nao encontrado.');
    assertContainsText($pdf, 'Declaracao de recebimento', 'Secao de declaracao nao encontrada.');

    $longReceivable = $receivable;
    $longReceipt = $receipt;
    $longReceivable['description'] = str_repeat('Descricao extensa do servico prestado com detalhamento tecnico, escopo operacional, entregas, treinamento e suporte pos-implantacao. ', 30);
    $longReceipt['observation'] = str_repeat('Observacao longa para validar quebra de pagina e manutencao do layout do recibo gerado. ', 50);

    $pdfLong = (new FinancialReceiptPdfGenerator())->build($branding, $longReceivable, $longReceipt);
    if (pageCount($pdfLong) < 2) {
        throw new RuntimeException('Era esperada quebra para mais de uma pagina no recibo com textos longos.');
    }

    $missingDataPdf = (new FinancialReceiptPdfGenerator())->build($branding, [
        'id' => 99,
        'client_name' => '',
        'client_company' => '',
        'project_title' => '',
        'description' => '',
        'title' => '',
        'invoice_number' => '',
        'bank_name' => '',
        'account_name' => '',
        'competence_date' => '',
        'external_reference' => '',
    ], [
        'id' => 11,
        'amount_received' => 1.00,
        'interest_amount' => 0.00,
        'fine_amount' => 0.00,
        'discount_amount' => 0.00,
        'payment_method' => '',
        'payment_date' => '',
        'transaction_reference' => '',
        'observation' => '',
    ]);
    assertContainsText($missingDataPdf, 'Cliente nao informado', 'Fallback de cliente ausente nao foi aplicado.');
    assertContainsText($missingDataPdf, 'Recebimento financeiro sem descricao detalhada.', 'Fallback de servico ausente nao foi aplicado.');

    try {
        $pngLogo = createRasterLogo('png', 800, 200);
        $pdfWithPng = (new FinancialReceiptPdfGenerator())->build(array_merge($branding, [
            'logo_path' => $pngLogo,
            'logo_mime' => 'image/png',
        ]), $receivable, $receipt);
        assertTrue(strpos($pdfWithPng, '/Subtype /Image') !== false, 'Logo PNG deveria ser embutido no PDF.');
        $pngTransform = extractImageTransform($pdfWithPng);
        assertTrue(is_array($pngTransform), 'Transformacao do logo PNG nao encontrada no PDF.');
        assertTrue(abs(($pngTransform['w'] / $pngTransform['h']) - 4.0) < 0.2, 'Logo PNG perdeu proporcao esperada.');

        $jpgLogo = createRasterLogo('jpg', 160, 320);
        $pdfWithJpg = (new FinancialReceiptPdfGenerator())->build(array_merge($branding, [
            'logo_path' => $jpgLogo,
            'logo_mime' => 'image/jpeg',
        ]), $receivable, $receipt);
        assertTrue(strpos($pdfWithJpg, '/Subtype /Image') !== false, 'Logo JPG deveria ser embutido no PDF.');
        $jpgTransform = extractImageTransform($pdfWithJpg);
        assertTrue(is_array($jpgTransform), 'Transformacao do logo JPG nao encontrada no PDF.');
        assertTrue(abs(($jpgTransform['w'] / $jpgTransform['h']) - 0.5) < 0.08, 'Logo JPG perdeu proporcao esperada.');

        @unlink($pngLogo);
        @unlink($jpgLogo);
    } catch (RuntimeException $rasterError) {
        if ($rasterError->getMessage() !== 'SKIP_RASTER') {
            throw $rasterError;
        }
    }

    $svgLogo = createSvgLogo(420, 120);
    $pdfWithSvg = (new FinancialReceiptPdfGenerator())->build(array_merge($branding, [
        'logo_path' => $svgLogo,
        'logo_mime' => 'image/svg+xml',
    ]), $receivable, $receipt);
    assertContainsText($pdfWithSvg, 'RECIBO DE PAGAMENTO', 'PDF com logo SVG nao deveria quebrar a geracao.');
    if (class_exists(Imagick::class)) {
        assertTrue(strpos($pdfWithSvg, '/Subtype /Image') !== false, 'Logo SVG deveria ser rasterizado quando Imagick estiver disponivel.');
    }

    @unlink($svgLogo);

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
