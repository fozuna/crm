<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;

final class CsrfMiddleware
{
    public function __invoke(Request $request, array $params, callable $next): void
    {
        $m = $request->method();
        if (in_array($m, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_csrf');
            if (!is_string($token) || $token === '') {
                $hdr = $request->header('X-CSRF-Token');
                $token = is_string($hdr) ? $hdr : null;
            }
            if (!Csrf::verify(is_string($token) ? $token : null)) {
                http_response_code(419);
                echo 'CSRF inválido.';
                return;
            }
        }

        $next($request, $params);
    }
}
