<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AuthMiddleware
{
    public function __invoke(Request $request, array $params, callable $next): void
    {
        $userId = Session::get('user_id');
        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            if (str_starts_with($request->path(), '/api/')) {
                Response::json(['ok' => false, 'error' => 'Não autenticado.'], 401);
            }
            Response::redirect($request->basePath() . '/login');
        }
        $next($request, $params);
    }
}
