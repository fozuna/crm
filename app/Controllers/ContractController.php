<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ContractNotificationRepository;
use App\Repositories\ContractRepository;
use App\Repositories\ContractTemplateRepository;
use App\Repositories\ContractVersionRepository;
use App\Services\ContractService;

final class ContractController
{
    public function index(Request $request): void
    {
        $filters = [
            'status' => trim((string) $request->input('status', '')),
            'proposal_id' => (int) $request->input('proposal_id', 0),
            'client_id' => (int) $request->input('client_id', 0),
        ];

        View::render('contracts/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'contracts' => (new ContractRepository())->all($filters),
            'summary' => (new ContractRepository())->summary(),
            'templates' => (new ContractTemplateRepository())->all(),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $contract = (new ContractRepository())->find($id);
        if ($contract === null) {
            http_response_code(404);
            echo 'Contrato nao encontrado.';
            return;
        }

        View::render('contracts/show', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'contract' => $contract,
            'versions' => (new ContractVersionRepository())->listByContract($id),
            'notifications' => (new ContractNotificationRepository())->listByContract($id),
        ]);
    }

    public function generateFromProposal(Request $request, array $params): void
    {
        $proposalId = (int) ($params['proposalId'] ?? 0);
        $actorId = (int) Session::get('user_id', 0);
        $signatureMode = (string) $request->input('signature_mode', 'print');
        $contractId = (new ContractService())->syncApprovedProposal($proposalId, $actorId, true, $signatureMode);
        if ($contractId === null) {
            Response::redirect($request->basePath() . '/propostas/' . $proposalId);
        }
        Response::redirect($request->basePath() . '/contratos/' . $contractId);
    }

    public function printable(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $contract = (new ContractRepository())->find($id);
        if ($contract === null) {
            http_response_code(404);
            echo 'Contrato nao encontrado.';
            return;
        }

        View::render('contracts/print', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'contract' => $contract,
        ], null);
    }

    public function pdf(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $contract = (new ContractRepository())->find($id);
        if ($contract === null) {
            http_response_code(404);
            return;
        }

        $path = (string) ($contract['current_file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="contrato-' . (int) $id . '-v' . (int) ($contract['current_version'] ?? 1) . '.pdf"');
        readfile($path);
        exit;
    }

    public function versionPdf(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $versionId = (int) ($params['versionId'] ?? 0);
        $version = (new ContractVersionRepository())->find($id, $versionId);
        if ($version === null) {
            http_response_code(404);
            return;
        }

        $path = (string) ($version['file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="contrato-' . (int) $id . '-v' . (int) ($version['version'] ?? 1) . '.pdf"');
        readfile($path);
        exit;
    }

    public function sendForSignature(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        (new ContractService())->sendForSignature($id, (int) Session::get('user_id', 0), $request->basePath());
        Response::redirect($request->basePath() . '/contratos/' . $id);
    }

    public function markSigned(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        (new ContractService())->markSigned($id, (int) Session::get('user_id', 0));
        Response::redirect($request->basePath() . '/contratos/' . $id);
    }

    public function markEffective(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        (new ContractService())->markEffective($id, (int) Session::get('user_id', 0));
        Response::redirect($request->basePath() . '/contratos/' . $id);
    }

    public function templates(Request $request): void
    {
        $service = new ContractService();
        View::render('contracts/templates', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'templates' => (new ContractTemplateRepository())->all(),
            'placeholders' => (new \App\Services\ContractTemplateEngine())->availablePlaceholders(),
            'service' => $service,
        ]);
    }

    public function updateTemplate(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ContractTemplateRepository();
        $template = $repo->find($id);
        if ($template === null) {
            http_response_code(404);
            echo 'Template nao encontrado.';
            return;
        }

        $payload = (new ContractService())->normalizeTemplateInput($request->allPost());
        $repo->update($id, $payload);
        Response::redirect($request->basePath() . '/contratos/templates');
    }

    public function reports(Request $request): void
    {
        $repo = new ContractRepository();
        $contracts = $repo->all([
            'status' => trim((string) $request->input('status', '')),
        ]);
        View::render('contracts/reports', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'summary' => $repo->summary(),
            'contracts' => $contracts,
        ]);
    }
}
