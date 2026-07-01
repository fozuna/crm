<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\ClientRepository;
use App\Repositories\LeadInteractionRepository;
use App\Repositories\PaymentMethodRepository;
use App\Repositories\ProposalBrandingRepository;
use App\Repositories\ProposalDocumentRepository;
use App\Repositories\ProposalRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ServiceRepository;
use App\Services\LeadProposalPrefillService;
use App\Services\ProposalPdfGenerator;
use App\Services\ProposalService;
use App\Services\CompanyProfileService;
use App\Services\ContractService;
use App\Core\Session;

final class ProposalController
{
    public function index(Request $request): void
    {
        $proposals = (new ProposalRepository())->allWithClient();
        View::render('proposals/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'proposals' => $proposals,
        ]);
    }

    public function create(Request $request): void
    {
        $clients = (new ClientRepository())->all();
        $paymentMethods = (new PaymentMethodRepository())->active();
        $services = (new ServiceRepository())->activeList(true);
        $prefill = $this->resolveLeadPrefill($request);
        View::render('proposals/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'clients' => $clients,
            'paymentMethods' => $paymentMethods,
            'services' => $services,
            'proposal' => $prefill['proposal'] ?? null,
            'items' => $prefill['items'] ?? [],
            'milestones' => $prefill['milestones'] ?? [],
            'leadPrefill' => $prefill['leadPrefill'] ?? null,
            'toastType' => $prefill['toastType'] ?? '',
            'toastMessage' => $prefill['toastMessage'] ?? '',
            'error' => $prefill['error'] ?? null,
        ]);
    }

    public function store(Request $request): void
    {
        $service = new ProposalService();
        $payload = $service->validatePayload($request);
        if ($payload === null) {
            $clients = (new ClientRepository())->all();
            $paymentMethods = (new PaymentMethodRepository())->active();
            $servicesList = (new ServiceRepository())->activeList(true);
            $leadPrefill = $this->resolveLeadPrefillFromPost($request);
            View::render('proposals/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'clients' => $clients,
                'paymentMethods' => $paymentMethods,
                'services' => $servicesList,
                'proposal' => $request->allPost(),
                'items' => $service->itemsFromRequest($request),
                'milestones' => $service->milestonesFromRequest($request),
                'leadPrefill' => $leadPrefill,
                'error' => 'Preencha cliente, título, forma de pagamento e ao menos 1 serviço válido.',
            ]);
            return;
        }

        $id = (new ProposalRepository())->create($payload);
        $sourceLeadId = (int) $request->input('source_lead_id', 0);
        if ($sourceLeadId > 0) {
            try {
                (new LeadInteractionRepository())->create(
                    $sourceLeadId,
                    'nota',
                    'Proposta #' . $id . ' iniciada a partir do pipeline comercial.',
                    (int) Session::get('user_id', 0)
                );
            } catch (\Throwable) {
            }
        }
        Response::redirect($request->basePath() . '/propostas/' . $id);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ProposalRepository();
        $proposal = $repo->find($id);
        if ($proposal === null) {
            http_response_code(404);
            echo 'Proposta não encontrada.';
            return;
        }

        $items = $repo->items($id);
        $milestones = $repo->milestones($id);
        $docs = (new ProposalDocumentRepository())->listByProposal($id);
        $project = (new ProjectRepository())->findByProposal($id);
        $projectId = is_array($project) ? (int) ($project['id'] ?? 0) : 0;

        $paymentSnapshot = [];
        $snap = (string) ($proposal['payment_snapshot'] ?? '');
        $decoded = $snap !== '' ? json_decode($snap, true) : null;
        if (is_array($decoded)) {
            $paymentSnapshot = $decoded;
        }

        $contractSuggestion = (new ContractService())->suggestionForProposal($id);

        View::render('proposals/show', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'proposal' => $proposal,
            'items' => $items,
            'milestones' => $milestones,
            'docs' => $docs,
            'paymentSnapshot' => $paymentSnapshot,
            'projectId' => $projectId,
            'contractSuggestion' => $contractSuggestion,
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ProposalRepository();
        $proposal = $repo->find($id);
        if ($proposal === null) {
            http_response_code(404);
            echo 'Proposta não encontrada.';
            return;
        }

        $clients = (new ClientRepository())->all();
        $paymentMethods = (new PaymentMethodRepository())->active();
        $items = $repo->items($id);
        $milestones = $repo->milestones($id);

        $serviceRepo = new ServiceRepository();
        $services = $serviceRepo->activeList(true);
        $existingIds = [];
        foreach ($services as $s) {
            $existingIds[(int) ($s['id'] ?? 0)] = true;
        }
        foreach ($items as $it) {
            $sid = (int) ($it['service_id'] ?? 0);
            if ($sid > 0 && !isset($existingIds[$sid])) {
                $row = $serviceRepo->find($sid);
                if (is_array($row)) {
                    $services[] = $row;
                    $existingIds[$sid] = true;
                }
            }
        }

        View::render('proposals/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'clients' => $clients,
            'paymentMethods' => $paymentMethods,
            'services' => $services,
            'proposal' => $proposal,
            'items' => $items,
            'milestones' => $milestones,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ProposalRepository();
        $existing = $repo->find($id);
        if ($existing === null) {
            http_response_code(404);
            echo 'Proposta não encontrada.';
            return;
        }

        $service = new ProposalService();
        $payload = $service->validatePayload($request, $existing);
        if ($payload === null) {
            $clients = (new ClientRepository())->all();
            $paymentMethods = (new PaymentMethodRepository())->active();
            $servicesList = (new ServiceRepository())->activeList(true);
            View::render('proposals/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'clients' => $clients,
                'paymentMethods' => $paymentMethods,
                'services' => $servicesList,
                'proposal' => array_merge($request->allPost(), ['id' => $id]),
                'items' => $service->itemsFromRequest($request),
                'milestones' => $service->milestonesFromRequest($request),
                'error' => 'Preencha cliente, título, forma de pagamento e ao menos 1 serviço válido.',
            ]);
            return;
        }

        $payload['status'] = (string) $existing['status'];

        $repo->update($id, $payload);
        Response::redirect($request->basePath() . '/propostas/' . $id);
    }

    public function updateStatus(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $status = (string) $request->input('status', '');
        $repo = new ProposalRepository();
        $repo->updateStatus($id, $status);
        if ($status === 'aprovada') {
            $requiresContract = (string) $request->input('requires_contract', '') === '1';
            $signatureMode = (string) $request->input('contract_signature_mode', 'digital');
            (new ContractService())->syncApprovedProposal($id, (int) Session::get('user_id', 0), $requiresContract, $signatureMode);
        }
        Response::redirect($request->basePath() . '/propostas/' . $id);
    }

    public function convertToProject(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        (new ProposalService())->convertToProject($id, $actorId);
        Response::redirect($request->basePath() . '/propostas/' . $id);
    }

    private function resolveLeadPrefill(Request $request): array
    {
        $leadId = (int) $request->input('lead_id', 0);
        if ($leadId <= 0) {
            return [];
        }

        try {
            $context = (new LeadProposalPrefillService())->build($leadId, $request->basePath());
            return [
                'proposal' => $context['proposal'],
                'items' => $context['items'],
                'milestones' => $context['milestones'],
                'leadPrefill' => $context,
                'toastType' => 'info',
                'toastMessage' => 'Dados do lead carregados automaticamente para a proposta.',
            ];
        } catch (\Throwable $e) {
            return [
                'proposal' => ['source_lead_id' => $leadId],
                'items' => [],
                'milestones' => [],
                'leadPrefill' => [
                    'retry_url' => $request->basePath() . '/propostas/nova?lead_id=' . $leadId,
                    'back_url' => $request->basePath() . '/leads/' . $leadId . '/editar',
                ],
                'toastType' => 'error',
                'toastMessage' => 'Nao foi possivel carregar automaticamente os dados do lead.',
                'error' => $e->getMessage() . ' Clique em "Tentar novamente" para recarregar os dados do lead.',
            ];
        }
    }

    private function resolveLeadPrefillFromPost(Request $request): ?array
    {
        $leadId = (int) $request->input('source_lead_id', 0);
        if ($leadId <= 0) {
            return null;
        }

        try {
            return (new LeadProposalPrefillService())->build($leadId, $request->basePath());
        } catch (\Throwable) {
            return [
                'retry_url' => $request->basePath() . '/propostas/nova?lead_id=' . $leadId,
                'back_url' => $request->basePath() . '/leads/' . $leadId . '/editar',
            ];
        }
    }


    public function pdf(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ProposalDocumentRepository();
        $docs = $repo->listByProposal($id);
        if (count($docs) === 0) {
            Response::redirect($request->basePath() . '/propostas/' . $id . '/preview');
        }
        $doc = $docs[0];
        Response::redirect($request->basePath() . '/propostas/' . $id . '/docs/' . $doc['id']);
    }

    public function preview(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ProposalRepository();
        $proposal = $repo->find($id);
        if ($proposal === null) {
            http_response_code(404);
            echo 'Proposta não encontrada.';
            return;
        }

        $items = $repo->items($id);
        $milestones = $repo->milestones($id);
        $docs = (new ProposalDocumentRepository())->listByProposal($id);
        $branding = (new ProposalBrandingRepository())->get();

        $paymentOptions = [];
        $po = (string) ($proposal['payment_options'] ?? '');
        $poDecoded = $po !== '' ? json_decode($po, true) : null;
        if (is_array($poDecoded)) {
            $paymentOptions = $poDecoded;
        }

        $paymentSnapshot = [];
        $snap = (string) ($proposal['payment_snapshot'] ?? '');
        $decoded = $snap !== '' ? json_decode($snap, true) : null;
        if (is_array($decoded)) {
            $paymentSnapshot = $decoded;
        }

        View::render('proposals/preview', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'proposal' => $proposal,
            'items' => $items,
            'milestones' => $milestones,
            'docs' => $docs,
            'branding' => $branding,
            'paymentSnapshot' => $paymentSnapshot,
            'paymentOptions' => $paymentOptions,
            'paymentSelectedIndex' => (int) ($proposal['payment_selected_index'] ?? 0),
        ]);
    }

    public function generatePdf(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $proposalRepo = new ProposalRepository();
        $proposal = $proposalRepo->find($id);
        if ($proposal === null) {
            http_response_code(404);
            echo 'Proposta não encontrada.';
            return;
        }

        $items = $proposalRepo->items($id);
        $milestones = $proposalRepo->milestones($id);
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
        $po = (string) ($proposal['payment_options'] ?? '');
        $poDecoded = $po !== '' ? json_decode($po, true) : null;
        if (is_array($poDecoded)) {
            $paymentOptions = $poDecoded;
        }

        $selectedIndex = (int) ($proposal['payment_selected_index'] ?? 0);

        $bytes = (new ProposalPdfGenerator())->build($branding, $proposal, $items, $milestones, $paymentOptions, $selectedIndex);

        $docRepo = new ProposalDocumentRepository();
        $version = $docRepo->nextVersion($id);
        $dir = __DIR__ . '/../../storage/pdfs/proposals/' . $id;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            http_response_code(500);
            echo 'Falha ao preparar diretório de PDF.';
            return;
        }

        $path = $dir . '/proposta-v' . $version . '.pdf';
        if (@file_put_contents($path, $bytes) === false) {
            http_response_code(500);
            echo 'Falha ao salvar PDF.';
            return;
        }

        $brandingSnapshot = json_encode($branding, JSON_UNESCAPED_UNICODE);
        $paymentSnapshot = null;
        $totalsSnapshot = json_encode([
            'subtotal' => (float) ($proposal['subtotal'] ?? 0),
            'discount_percent' => (float) ($proposal['discount_percent'] ?? 0),
            'discount_amount' => (float) ($proposal['discount_amount'] ?? 0),
            'total' => (float) ($proposal['total'] ?? 0),
            'payment' => $paymentSnapshot,
        ], JSON_UNESCAPED_UNICODE);

        $docId = $docRepo->create($id, $version, $path, $brandingSnapshot, $totalsSnapshot);

        Response::redirect($request->basePath() . '/propostas/' . $id . '/docs/' . $docId);
    }

    public function doc(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $docId = (int) ($params['docId'] ?? 0);
        $doc = (new ProposalDocumentRepository())->find($id, $docId);
        if ($doc === null) {
            http_response_code(404);
            return;
        }

        $path = (string) ($doc['file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        $filename = 'proposta-' . $id . '-v' . (int) $doc['version'] . '.pdf';
        header('Content-Disposition: inline; filename="' . $filename . '"');
        readfile($path);
        exit;
    }
}
