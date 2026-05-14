<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\FinanceDashboardRepository;

final class DashboardFinanceApiController
{
    public function show(Request $request): void
    {
        $filters = [
            'from' => trim((string) $request->input('from', '')),
            'to' => trim((string) $request->input('to', '')),
            'client_id' => (int) $request->input('client_id', 0),
            'status' => trim((string) $request->input('status', '')),
        ];

        $data = (new FinanceDashboardRepository())->dashboard($filters);
        Response::json(['ok' => true, 'filters' => $filters, 'data' => $data]);
    }
}

