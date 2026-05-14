<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            $pipeline = $this->buildMiddlewarePipeline($route['middleware'], $route['handler']);
            $pipeline($request, $params);
            return;
        }

        http_response_code(404);
        echo '404';
    }

    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path) {
            return [];
        }

        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $k => $v) {
            if (is_string($k)) {
                $params[$k] = $v;
            }
        }
        return $params;
    }

    private function buildMiddlewarePipeline(array $middleware, callable $handler): callable
    {
        $next = static function (Request $request, array $params) use ($handler): void {
            $handler($request, $params);
        };

        foreach (array_reverse($middleware) as $mw) {
            $next = static function (Request $request, array $params) use ($mw, $next): void {
                $mw($request, $params, $next);
            };
        }

        return $next;
    }
}

