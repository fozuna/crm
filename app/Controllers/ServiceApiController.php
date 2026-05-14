<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ServiceRepository;

final class ServiceApiController
{
    public function index(Request $request): void
    {
        $onlyActive = (string) $request->input('active', '');
        $includeBonus = (string) $request->input('include_bonus', '1');
        $q = trim((string) $request->input('q', ''));

        $repo = new ServiceRepository();
        if ($onlyActive === '1') {
            $rows = $repo->activeList($includeBonus === '1' || $includeBonus === 'true');
            if ($q !== '') {
                $qLower = mb_strtolower($q);
                $rows = array_values(array_filter($rows, static function ($r) use ($qLower) {
                    $name = mb_strtolower((string) ($r['name'] ?? ''));
                    $desc = mb_strtolower((string) ($r['description'] ?? ''));
                    return str_contains($name, $qLower) || str_contains($desc, $qLower);
                }));
            }
            Response::json(['ok' => true, 'rows' => $rows]);
        }

        $filters = [
            'q' => $q,
            'status' => (string) $request->input('status', ''),
            'type' => (string) $request->input('type', ''),
            'sort' => (string) $request->input('sort', 'name_asc'),
        ];
        $page = (int) $request->input('page', 1);
        $per = (int) $request->input('per_page', 20);
        $data = $repo->paginated($filters, $page, $per);
        Response::json(['ok' => true, 'filters' => $filters, 'data' => $data]);
    }

    public function show(Request $request, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $row = (new ServiceRepository())->find($id);
        if ($row === null) {
            Response::json(['ok' => false, 'message' => 'Serviço não encontrado.'], 404);
        }
        Response::json(['ok' => true, 'service' => $row]);
    }
}

