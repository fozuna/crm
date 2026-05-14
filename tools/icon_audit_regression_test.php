<?php
declare(strict_types=1);

$base = dirname(__DIR__);
$uiFile = $base . '/app/Core/UI.php';
$viewsRoot = $base . '/resources/views';

function listPhpFiles(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function definedIcons(string $uiFile): array
{
    $contents = file_get_contents($uiFile);
    if (!is_string($contents)) {
        throw new RuntimeException('Nao foi possivel ler o catalogo de icones.');
    }

    preg_match_all("/'([^']+)'\\s*=>\\s*'/", $contents, $matches);
    return array_values(array_unique($matches[1] ?? []));
}

function relativePath(string $path, string $base): string
{
    $normalizedPath = str_replace('\\', '/', $path);
    $normalizedBase = rtrim(str_replace('\\', '/', $base), '/');
    if (strpos($normalizedPath, $normalizedBase . '/') === 0) {
        return substr($normalizedPath, strlen($normalizedBase) + 1);
    }
    return $normalizedPath;
}

try {
    $knownIcons = array_flip(definedIcons($uiFile));
    $errors = [];

    foreach (listPhpFiles($viewsRoot) as $file) {
        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            $errors[] = 'Falha ao ler ' . relativePath($file, $base);
            continue;
        }

        if (preg_match_all("/UI::icon\\('([^']+)'/", $contents, $matches) === 1 || !empty($matches[1])) {
            foreach ($matches[1] as $icon) {
                if (!isset($knownIcons[$icon])) {
                    $errors[] = 'Icone nao catalogado em ' . relativePath($file, $base) . ': ' . $icon;
                }
            }
        }

        $lines = preg_split('/\\R/', $contents) ?: [];
        foreach ($lines as $line) {
            if (stripos($line, '<img') !== false && stripos($line, 'alt=') === false) {
                $errors[] = 'Imagem sem alt em ' . relativePath($file, $base);
            }

            if (
                (stripos($line, '<a ') !== false || stripos($line, '<button') !== false)
                &&
                preg_match('/class\\s*=\\s*["\'][^"\']*\\btr-icon-btn(?!-)[^"\']*["\']/i', $line) === 1
                && stripos($line, 'aria-label=') === false
                && stripos($line, 'title=') === false
                && stripos($line, 'sr-only') === false
            ) {
                $errors[] = 'Controle iconico sem rotulo acessivel em ' . relativePath($file, $base) . ': ' . trim($line);
            }
        }
    }

    if ($errors !== []) {
        throw new RuntimeException(implode(PHP_EOL, $errors));
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
