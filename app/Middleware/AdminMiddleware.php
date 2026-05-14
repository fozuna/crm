<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminMiddleware
{
    public function __invoke(Request $request, array $params, callable $next): void
    {
        $isAdmin = (int) Session::get('is_admin', 0);
        if ($isAdmin !== 1) {
            if (str_starts_with($request->path(), '/api/')) {
                Response::json(['ok' => false, 'error' => 'Sem permissão.'], 403);
            }
            Response::redirect($request->basePath() . '/dashboard');
        }
        $next($request, $params);
    }
}
