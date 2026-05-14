<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\View;

final class ManualController
{
    public function index(Request $request): void
    {
        View::render('manual/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
        ]);
    }
}

