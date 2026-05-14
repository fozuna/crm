<?php
declare(strict_types=1);

$base = dirname(__DIR__);

function requireText(string $file, string $needle, string $message): void
{
    $contents = file_get_contents($file);
    if (!is_string($contents) || strpos($contents, $needle) === false) {
        throw new RuntimeException($message . ' Arquivo: ' . $file . ' Texto: ' . $needle);
    }
}

try {
    $head = $base . '/resources/views/partials/head.php';
    requireText($head, '.tr-btn--icon-only', 'Padrao visual de icone-only nao encontrado.');
    requireText($head, 'window.trIconify = run;', 'Camada global de iconificacao nao encontrada.');
    requireText($head, "svg.setAttribute('aria-hidden', 'true')", 'Ocultacao de SVG decorativo nao encontrada.');
    requireText($head, "setAttribute('aria-label', label)", 'Atribuicao global de aria-label nao encontrada.');

    $preview = $base . '/resources/views/financial/receipts/preview.php';
    requireText($preview, 'aria-label="Voltar"', 'Preview do recibo sem aria-label no botao voltar.');
    requireText($preview, 'aria-label="Baixar PDF"', 'Preview do recibo sem aria-label no download.');
    requireText($preview, 'aria-label="Imprimir"', 'Preview do recibo sem aria-label na impressao.');

    $print = $base . '/resources/views/financial/receivables/print.php';
    requireText($print, 'aria-label="Imprimir"', 'Tela de impressao sem aria-label no botao de impressao.');
    requireText($print, "UI::icon('print'", 'Tela de impressao sem icone padronizado de impressao.');

    $ui = $base . '/app/Core/UI.php';
    foreach (['print', 'download', 'filter', 'search', 'chart', 'list'] as $icon) {
        requireText($ui, "'" . $icon . "' =>", 'Icone obrigatorio ausente no catalogo central.');
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
