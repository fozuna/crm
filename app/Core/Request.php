<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private ?string $rawBody = null;
    private ?array $jsonBody = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $qPos = strpos($uri, '?');
        if ($qPos !== false) {
            $uri = substr($uri, 0, $qPos);
        }

        $base = $this->basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . ltrim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    public function basePath(): string
    {
        $configured = (string) Config::get('APP_BASE_PATH', '');
        if ($configured !== '') {
            $configured = trim($configured);
            if (preg_match('#^https?://#i', $configured) === 1) {
                $parts = parse_url($configured);
                if (is_array($parts) && isset($parts['path']) && is_string($parts['path'])) {
                    $path = $parts['path'];
                    $path = '/' . ltrim($path, '/');
                    $path = rtrim($path, '/');
                    return $path === '/' ? '' : $path;
                }
            }
            $configured = rtrim($configured, '/');
            if ($configured !== '' && $configured[0] !== '/') {
                $configured = '/' . $configured;
            }
            return $configured;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $dir === '/' ? '' : $dir;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }
        return $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        return $default;
    }

    public function rawBody(): string
    {
        if ($this->rawBody !== null) {
            return $this->rawBody;
        }
        $body = @file_get_contents('php://input');
        $this->rawBody = is_string($body) ? $body : '';
        return $this->rawBody;
    }

    public function jsonBody(): array
    {
        if (is_array($this->jsonBody)) {
            return $this->jsonBody;
        }

        $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (!str_contains($ct, 'application/json')) {
            $this->jsonBody = [];
            return $this->jsonBody;
        }

        $decoded = json_decode($this->rawBody(), true);
        $this->jsonBody = is_array($decoded) ? $decoded : [];
        return $this->jsonBody;
    }

    public function allPost(): array
    {
        return $_POST;
    }
}
