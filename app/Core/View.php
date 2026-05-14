<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $viewsDir = __DIR__ . '/../../resources/views';
        $viewFile = $viewsDir . '/' . trim($view, '/') . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View não encontrada: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $viewsDir . '/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException('Layout não encontrado: ' . $layout);
        }

        require $layoutFile;
    }

    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

