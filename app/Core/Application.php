<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\DbStartupGuard;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\CsrfMiddleware;

final class Application
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function run(): void
    {
        $request = new Request();

        if ($this->shouldEnforceDatabaseGuard($request)) {
            (new DbStartupGuard())->enforce();
        }

        if (!$this->isInstalled() && !$this->hasMinimumConfig() && $request->path() !== '/install') {
            Response::redirect($this->url('/install', $request));
        }

        $routes = require __DIR__ . '/../../config/routes.php';
        $routes($this->router, [
            'auth' => new AuthMiddleware(),
            'admin' => new AdminMiddleware(),
            'csrf' => new CsrfMiddleware(),
        ]);

        $this->router->dispatch($request);
    }

    private function isInstalled(): bool
    {
        if (!$this->hasMinimumConfig()) {
            return false;
        }

        try {
            $pdo = DB::pdo();
            $stmt = $pdo->query('SHOW TABLES LIKE "users"');
            $exists = $stmt->fetchColumn() !== false;
            if (!$exists) {
                return false;
            }

            $countStmt = $pdo->query('SELECT COUNT(*) FROM users');
            return ((int) $countStmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasMinimumConfig(): bool
    {
        return trim((string) Config::get('APP_URL', '')) !== ''
            && trim((string) Config::get('DB_HOST', '')) !== ''
            && trim((string) Config::get('DB_NAME', '')) !== ''
            && trim((string) Config::get('DB_USER', '')) !== '';
    }

    private function url(string $path, Request $request): string
    {
        $base = $request->basePath();
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    private function shouldEnforceDatabaseGuard(Request $request): bool
    {
        if (!(bool) Config::get('DB_REQUIRE_SYNC_BEFORE_RUN', true)) {
            return false;
        }

        if (!$this->hasMinimumConfig()) {
            return false;
        }

        return $request->path() !== '/install';
    }
}
