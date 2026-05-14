<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Config;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repositories\UserRepository;

final class InstallController
{
    public function show(Request $request): void
    {
        View::render('install/index', [
            'csrf' => Csrf::token(),
            'base' => $request->basePath(),
            'defaults' => [
                'app_url' => (string) Config::get('APP_URL', ''),
                'db_host' => (string) Config::get('DB_HOST', '127.0.0.1'),
                'db_port' => (string) Config::get('DB_PORT', 3306),
                'db_name' => (string) Config::get('DB_NAME', ''),
                'db_user' => (string) Config::get('DB_USER', ''),
            ],
        ], null);
    }

    public function store(Request $request): void
    {
        $appUrl = trim((string) $request->input('app_url', ''));
        $dbHost = trim((string) $request->input('db_host', ''));
        $dbPort = (int) $request->input('db_port', 3306);
        $dbName = trim((string) $request->input('db_name', ''));
        $dbUser = trim((string) $request->input('db_user', ''));
        $dbPass = (string) $request->input('db_pass', '');
        $adminName = trim((string) $request->input('admin_name', ''));
        $adminEmail = trim((string) $request->input('admin_email', ''));
        $adminPass = (string) $request->input('admin_pass', '');

        if ($appUrl === '' || $dbHost === '' || $dbName === '' || $dbUser === '' || $adminName === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminPass) < 8) {
            View::render('install/index', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'error' => 'Preencha URL, dados do banco e uma senha de admin (mín. 8).',
                'defaults' => $request->allPost(),
            ], null);
            return;
        }

        $env = $this->buildEnv([
            'APP_NAME' => 'TRAXTER CRM',
            'APP_URL' => $appUrl,
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => (string) Config::get('APP_TIMEZONE', 'America/Sao_Paulo'),
            'APP_KEY' => $this->randomKey(),
            'DB_HOST' => $dbHost,
            'DB_PORT' => (string) $dbPort,
            'DB_NAME' => $dbName,
            'DB_USER' => $dbUser,
            'DB_PASS' => $dbPass,
            'DB_CHARSET' => 'utf8mb4',
        ]);

        $envPath = __DIR__ . '/../../.env';
        if (@file_put_contents($envPath, $env) === false) {
            View::render('install/index', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'error' => 'Não foi possível gravar o .env. Verifique permissões.',
                'defaults' => $request->allPost(),
            ], null);
            return;
        }

        $config = Config::load($envPath, __DIR__ . '/../../config/config.php');
        Config::setAll($config);

        try {
            $pdo = DB::pdo();
            $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
            if (!is_string($schema) || trim($schema) === '') {
                throw new \RuntimeException('Schema vazio.');
            }
            foreach ($this->splitSqlStatements($schema) as $sql) {
                $pdo->exec($sql);
            }

            $userRepo = new UserRepository();
            $userRepo->createAdmin($adminName, $adminEmail, $adminPass);
        } catch (\Throwable $e) {
            @unlink($envPath);
            View::render('install/index', [
                'csrf' => Csrf::token(),
                'base' => $request->basePath(),
                'error' => 'Falha no banco. Confira credenciais e tente novamente.',
                'defaults' => $request->allPost(),
            ], null);
            return;
        }

        Response::redirect($request->basePath() . '/login');
    }

    private function buildEnv(array $vars): string
    {
        $lines = [];
        foreach ($vars as $k => $v) {
            $value = (string) $v;
            if (preg_match('/\s|\"/', $value)) {
                $value = '"' . str_replace('"', '\\"', $value) . '"';
            }
            $lines[] = $k . '=' . $value;
        }
        return implode("\n", $lines) . "\n";
    }

    private function splitSqlStatements(string $schema): array
    {
        $schema = preg_replace('/^\s*--.*$/m', '', $schema);
        $parts = preg_split('/;\s*\n/', (string) $schema);
        $sql = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $sql[] = $p;
            }
        }
        return $sql;
    }

    private function randomKey(): string
    {
        try {
            return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        } catch (\Throwable) {
            return sha1((string) microtime(true) . (string) mt_rand());
        }
    }
}
