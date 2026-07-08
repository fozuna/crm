<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI apenas.\n";
    exit(1);
}

require __DIR__ . '/../app/bootstrap.php';

use App\Repositories\ProposalBrandingRepository;
use App\Repositories\ProposalDocumentRepository;
use App\Repositories\ProposalRepository;
use App\Services\CompanyProfileService;
use App\Services\ProposalPdfGenerator;

$proposalId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($proposalId <= 0) {
    fwrite(STDERR, "Uso: php tools/regenerate_proposal_pdf.php <proposal_id>\n");
    exit(2);
}

$proposalRepo = new ProposalRepository();
$proposal = $proposalRepo->find($proposalId);
if (!is_array($proposal)) {
    fwrite(STDERR, "Proposta nao encontrada.\n");
    exit(3);
}

$items = $proposalRepo->items($proposalId);
$milestones = $proposalRepo->milestones($proposalId);
$branding = (new ProposalBrandingRepository())->get();
$company = (new CompanyProfileService())->getCached();

if (is_array($company)) {
    $companyName = trim((string) ($branding['company_name'] ?? ''));
    if ($companyName === '' || strtoupper($companyName) === 'TRAXTER') {
        $fallbackName = trim((string) ($company['trade_name'] ?? ''));
        if ($fallbackName === '') {
            $fallbackName = trim((string) ($company['legal_name'] ?? ''));
        }
        if ($fallbackName !== '') {
            $branding['company_name'] = $fallbackName;
        }
    }

    $logoPath = (string) ($branding['logo_path'] ?? '');
    if ($logoPath === '' || !is_file($logoPath)) {
        $fallbackLogo = (string) ($company['logo_light_path'] ?? '');
        if ($fallbackLogo !== '' && is_file($fallbackLogo)) {
            $branding['logo_path'] = $fallbackLogo;
        }
    }

    $branding['company_cnpj'] = (string) ($company['cnpj'] ?? '');
    $branding['company_email'] = (string) ($company['email'] ?? '');
    $branding['company_whatsapp'] = (string) ($company['whatsapp'] ?? '');
    $branding['company_website'] = (string) ($company['website'] ?? '');
}

$paymentOptions = [];
$paymentOptionsRaw = (string) ($proposal['payment_options'] ?? '');
$paymentOptionsDecoded = $paymentOptionsRaw !== '' ? json_decode($paymentOptionsRaw, true) : null;
if (is_array($paymentOptionsDecoded)) {
    $paymentOptions = $paymentOptionsDecoded;
}

$selectedIndex = (int) ($proposal['payment_selected_index'] ?? 0);
$bytes = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, $selectedIndex);

$docRepo = new ProposalDocumentRepository();
$version = $docRepo->nextVersion($proposalId);
$dir = __DIR__ . '/../storage/pdfs/proposals/' . $proposalId;
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
if (!is_dir($dir)) {
    fwrite(STDERR, "Falha ao preparar diretório de PDFs.\n");
    exit(4);
}

$path = $dir . '/proposta-v' . $version . '.pdf';
if (@file_put_contents($path, $bytes) === false) {
    fwrite(STDERR, "Falha ao salvar PDF.\n");
    exit(5);
}

$brandingSnapshot = json_encode($branding, JSON_UNESCAPED_UNICODE);
$totalsSnapshot = json_encode([
    'subtotal' => (float) ($proposal['subtotal'] ?? 0),
    'discount_percent' => (float) ($proposal['discount_percent'] ?? 0),
    'discount_amount' => (float) ($proposal['discount_amount'] ?? 0),
    'total' => (float) ($proposal['total'] ?? 0),
    'payment' => null,
], JSON_UNESCAPED_UNICODE);

$docId = $docRepo->create($proposalId, $version, $path, $brandingSnapshot, $totalsSnapshot);

echo json_encode([
    'proposal_id' => $proposalId,
    'doc_id' => $docId,
    'version' => $version,
    'path' => $path,
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
