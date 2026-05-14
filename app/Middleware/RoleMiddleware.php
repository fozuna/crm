<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RoleMiddleware
{
    private array $roles;

    public function __construct(array $roles)
    {
        $this->roles = array_values(array_filter($roles, static fn($r) => is_string($r) && $r !== ''));
    }

    public function __invoke(Request $request, array $params, callable $next): void
    {
        $role = (string) Session::get('user_role', '');
        if ($role === '') {
            $role = ((int) Session::get('is_admin', 0) === 1) ? 'admin' : '';
        }

        if (!in_array($role, $this->roles, true)) {
            if (str_starts_with($request->path(), '/api/')) {
                Response::json(['ok' => false, 'error' => 'Sem permissão.'], 403);
            }
            Response::redirect($request->basePath() . '/dashboard');
        }

        $next($request, $params);
    }
}

