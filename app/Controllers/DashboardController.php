<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\DashboardRepository;
use App\Repositories\ClientRepository;

final class DashboardController
{
    public function index(Request $request): void
    {
        $repo = new DashboardRepository();
        $stats = $repo->stats();
        $clients = (new ClientRepository())->options();

        View::render('dashboard/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'userName' => (string) Session::get('user_name', ''),
            'stats' => $stats,
            'clients' => $clients,
        ]);
    }
}
