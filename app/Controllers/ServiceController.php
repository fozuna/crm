<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\AuditLogRepository;
use App\Repositories\ServiceRepository;
use App\Services\Money;

final class ServiceController
{
    public function index(Request $request): void
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'type' => (string) $request->input('type', ''),
            'sort' => (string) $request->input('sort', 'name_asc'),
        ];
        $page = (int) $request->input('page', 1);
        $data = (new ServiceRepository())->paginated($filters, $page, 20);
        View::render('services/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'filters' => $filters,
            'data' => $data,
        ]);
    }

    public function create(Request $request): void
    {
        View::render('services/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'service' => null,
        ]);
    }

    public function store(Request $request): void
    {
        $repo = new ServiceRepository();
        $data = $this->validate($request, $repo);
        if ($data === null) {
            View::render('services/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'service' => $request->allPost(),
                'error' => 'Revise nome (único), preço e descrição (mínimo 50 caracteres).',
            ]);
            return;
        }

        $id = $repo->create($data);
        $actorId = (int) Session::get('user_id', 0);
        (new AuditLogRepository())->create('service', $id, 'create', $actorId > 0 ? $actorId : null, ['after' => $data]);
        Response::redirect($request->basePath() . '/servicos');
    }

    public function edit(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $service = (new ServiceRepository())->find($id);
        if ($service === null) {
            http_response_code(404);
            echo 'Serviço não encontrado.';
            return;
        }

        View::render('services/form', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'service' => $service,
        ]);
    }

    public function update(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $repo = new ServiceRepository();
        $existing = $repo->find($id);
        if ($existing === null) {
            http_response_code(404);
            echo 'Serviço não encontrado.';
            return;
        }

        $data = $this->validate($request, $repo, $id);
        if ($data === null) {
            View::render('services/form', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'service' => array_merge($request->allPost(), ['id' => $id]),
                'error' => 'Revise nome (único), preço e descrição (mínimo 50 caracteres).',
            ]);
            return;
        }

        $repo->update($id, $data);
        $actorId = (int) Session::get('user_id', 0);
        (new AuditLogRepository())->create('service', $id, 'update', $actorId > 0 ? $actorId : null, ['before' => $existing, 'after' => $data]);
        Response::redirect($request->basePath() . '/servicos');
    }

    private function validate(Request $request, ServiceRepository $repo, ?int $id = null): ?array
    {
        $name = trim((string) $request->input('name', ''));
        $priceRaw = (string) $request->input('default_price', '');
        $price = Money::parseBRL($priceRaw);
        $active = (string) $request->input('active', '1');
        $isBonus = (string) $request->input('is_bonus', '0');
        $description = trim((string) $request->input('description', ''));

        if ($name === '' || mb_strlen($name) > 100) {
            return null;
        }
        if ($description === '' || mb_strlen($description) < 50) {
            return null;
        }
        if (!is_finite($price) || $price < 0) {
            return null;
        }

        $activeInt = ($active === '1' || $active === 'on') ? 1 : 0;
        $bonusInt = ($isBonus === '1' || $isBonus === 'on') ? 1 : 0;

        if ($repo->existsByName($name, $id)) {
            return null;
        }

        return [
            'name' => $name,
            'default_price' => round($price, 2),
            'active' => $activeInt,
            'description' => $description,
            'is_bonus' => $bonusInt,
        ];
    }
}
