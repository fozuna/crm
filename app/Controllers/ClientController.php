<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\ClientRepository;
use App\Repositories\ClientDetailsRepository;
use App\Repositories\ClientInteractionRepository;
use App\Services\ClientValidator;

final class ClientController
{
    public function index(Request $request): void
    {
        $repo = new ClientRepository();
        $clients = $repo->all();
        $toastType = strtolower((string) $request->input('toast', ''));
        $toastMessage = trim((string) $request->input('msg', ''));
        if (!in_array($toastType, ['success', 'error', 'warning', 'info'], true)) {
            $toastType = '';
        }
        if ($toastMessage !== '' && strlen($toastMessage) > 220) {
            $toastMessage = substr($toastMessage, 0, 220);
        }

        View::render('clients/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'clients' => $clients,
            'toastType' => $toastType,
            'toastMessage' => $toastMessage,
        ]);
    }

    public function create(Request $request): void
    {
        View::render('clients/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'client' => $this->prepareFormClient(null),
            'errors' => [],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = (new ClientRepository())->find($id);
        if ($client === null) {
            http_response_code(404);
            echo 'Cliente não encontrado.';
            return;
        }

        $details = new ClientDetailsRepository();
        $projects = $details->projects($id);
        $proposals = $details->proposals($id);
        $interactions = (new ClientInteractionRepository())->listByClient($id);

        $logoPath = (string) ($client['logo_path'] ?? '');
        $hasLogo = $logoPath !== '' && is_file($logoPath);

        View::render('clients/show', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'client' => $client,
            'hasLogo' => $hasLogo,
            'projects' => $projects,
            'proposals' => $proposals,
            'interactions' => $interactions,
        ]);
    }

    public function addInteraction(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = (new ClientRepository())->find($id);
        if ($client === null) {
            http_response_code(404);
            return;
        }

        $kind = trim((string) $request->input('kind', 'nota'));
        $note = trim((string) $request->input('note', ''));
        $allowed = ['nota', 'email', 'call', 'meeting'];
        if (!in_array($kind, $allowed, true) || $note === '') {
            Response::redirect($request->basePath() . '/clientes/' . $id);
        }

        (new ClientInteractionRepository())->create($id, $kind, $note);
        Response::redirect($request->basePath() . '/clientes/' . $id);
    }

    public function store(Request $request): void
    {
        $validation = $this->validate($request);
        if (!($validation['ok'] ?? false)) {
            $errors = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            View::render('clients/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'client' => $this->prepareFormClient($request->allPost()),
                'errors' => $errors,
                'error' => (string) reset($errors),
            ]);
            return;
        }

        $repo = new ClientRepository();
        $id = $repo->create((array) ($validation['data'] ?? []));
        $this->handleLogoUpload($id, $repo);
        Response::redirect($request->basePath() . '/clientes/' . $id);
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = (new ClientRepository())->find($id);
        if ($client === null) {
            http_response_code(404);
            echo 'Cliente não encontrado.';
            return;
        }

        View::render('clients/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'client' => $this->prepareFormClient($client),
            'errors' => [],
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $validation = $this->validate($request);
        if (!($validation['ok'] ?? false)) {
            $errors = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
            View::render('clients/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'client' => $this->prepareFormClient(array_merge($request->allPost(), ['id' => $id])),
                'errors' => $errors,
                'error' => (string) reset($errors),
            ]);
            return;
        }

        $repo = new ClientRepository();
        $repo->update($id, (array) ($validation['data'] ?? []));
        $this->handleLogoUpload($id, $repo);
        Response::redirect($request->basePath() . '/clientes/' . $id);
    }

    public function logo(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $client = (new ClientRepository())->find($id);
        if ($client === null) {
            http_response_code(404);
            return;
        }

        $path = (string) ($client['logo_path'] ?? '');
        $mime = (string) ($client['logo_mime'] ?? '');
        if ($path === '' || !is_file($path)) {
            http_response_code(404);
            return;
        }

        $mime = $mime !== '' ? $mime : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cache-Control: private, max-age=3600');
        readfile($path);
        exit;
    }

    public function destroy(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0) {
            Response::redirect($request->basePath() . '/clientes?toast=error&msg=' . rawurlencode('Cliente inválido.'));
        }

        $repo = new ClientRepository();
        $client = $repo->find($id);
        if ($client === null) {
            Response::redirect($request->basePath() . '/clientes?toast=error&msg=' . rawurlencode('Cliente não encontrado.'));
        }

        $deps = $repo->dependencyCounts($id);
        $blocked = array_sum(array_map('intval', $deps)) > 0;
        if ($blocked) {
            $msg = 'Não foi possível excluir: cliente possui vínculos em outros módulos.';
            Response::redirect($request->basePath() . '/clientes?toast=warning&msg=' . rawurlencode($msg));
        }

        $logoPath = (string) ($client['logo_path'] ?? '');
        try {
            $repo->delete($id);
        } catch (\PDOException $e) {
            $code = (string) $e->getCode();
            if ($code === '23000') {
                $msg = 'Não foi possível excluir: cliente possui vínculos em outros módulos.';
                Response::redirect($request->basePath() . '/clientes?toast=warning&msg=' . rawurlencode($msg));
            }
            throw $e;
        }

        if ($logoPath !== '' && is_file($logoPath)) {
            @unlink($logoPath);
        }

        Response::redirect($request->basePath() . '/clientes?toast=success&msg=' . rawurlencode('Cliente excluído com sucesso.'));
    }

    private function validate(Request $request): array
    {
        return (new ClientValidator())->validate($request->allPost());
    }

    private function handleLogoUpload(int $clientId, ClientRepository $repo): void
    {
        if (!isset($_FILES['logo']) || !is_array($_FILES['logo'])) {
            return;
        }

        $file = $_FILES['logo'];
        if (!isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
            return;
        }

        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return;
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            return;
        }

        $maxBytes = 5 * 1024 * 1024;
        if ((int) $file['size'] > $maxBytes) {
            return;
        }

        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || !isset($info['mime'])) {
            return;
        }

        $mime = (string) $info['mime'];
        if (!str_starts_with($mime, 'image/')) {
            return;
        }

        $ext = $this->extFromMime($mime);
        if ($ext === null) {
            $ext = 'img';
        }

        $dir = __DIR__ . '/../../storage/uploads/clients/' . $clientId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir)) {
            return;
        }

        $target = $dir . '/logo.' . $ext;
        if (!@move_uploaded_file((string) $file['tmp_name'], $target)) {
            return;
        }

        @chmod($target, 0644);

        $repo->updateLogo($clientId, $target, $mime, (string) $file['name']);
    }

    private function extFromMime(string $mime): ?string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/tiff' => 'tiff',
            'image/svg+xml' => 'svg',
            default => null,
        };
    }

    private function prepareFormClient(?array $client): array
    {
        $client = is_array($client) ? $client : [];

        return array_merge([
            'name' => '',
            'company' => '',
            'email' => '',
            'phone' => '',
            'contact_person' => '',
            'status' => 'lead',
            'project_reference' => '',
            'has_hosting_contract' => '0',
            'hosting_contract_amount' => '',
            'hosting_due_date' => '',
            'hosting_renewal_days' => '45',
            'hosting_renewal_suggested_date' => '',
            'manages_domain' => '0',
            'domain_due_date' => '',
            'domain_amount' => '',
        ], $client, [
            'has_hosting_contract' => $this->toCheckedValue($client['has_hosting_contract'] ?? null),
            'hosting_contract_amount' => $this->formatMoneyForForm($client['hosting_contract_amount'] ?? ''),
            'hosting_due_date' => $this->formatDateForForm($client['hosting_due_date'] ?? ''),
            'hosting_renewal_days' => $this->formatRenewalDays($client['hosting_renewal_days'] ?? null),
            'hosting_renewal_suggested_date' => $this->formatDateForForm($client['hosting_renewal_suggested_date'] ?? $this->calculateRenewalSuggestion($client)),
            'manages_domain' => $this->toCheckedValue($client['manages_domain'] ?? null),
            'domain_due_date' => $this->formatDateForForm($client['domain_due_date'] ?? ''),
            'domain_amount' => $this->formatMoneyForForm($client['domain_amount'] ?? ''),
        ]);
    }

    private function toCheckedValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'on', 'yes', 'sim'], true) ? '1' : '0';
    }

    private function formatMoneyForForm(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, ',', '.');
        }

        return trim((string) $value);
    }

    private function formatDateForForm(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
            if ($date instanceof \DateTimeImmutable && $date->format($format) === $raw) {
                return $date->format('Y-m-d');
            }
        }

        return $raw;
    }

    private function formatRenewalDays(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '45';
        }

        return (string) (int) $value;
    }

    private function calculateRenewalSuggestion(array $client): string
    {
        $dueRaw = trim((string) ($client['hosting_due_date'] ?? ''));
        $days = (int) ($client['hosting_renewal_days'] ?? 45);
        if ($dueRaw === '' || $days < 1) {
            return '';
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dueRaw);
        if (!$date instanceof \DateTimeImmutable) {
            return '';
        }

        return $date->modify('+' . $days . ' days')->format('Y-m-d');
    }
}
