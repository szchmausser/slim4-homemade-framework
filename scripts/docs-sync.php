<?php

declare(strict_types=1);

// Re-sincroniza las copias de archivos del repo que viven en los Markdown de
// docs/. Uso: composer docs:sync
//
// La guía paso a paso copia archivos enteros (resources/views/..., tests/...).
// Copiar a mano se pudre: cuando el archivo cambia, la copia queda vieja y
// nadie se entera. Este script reescribe cada fence que declara un archivo
// con el contenido real del repo; docs:check verifica lo mismo sin escribir.
//
// Un fence marcado `excerpt` (fragmento: un método, tres líneas de un array)
// no se toca: no es una copia.

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$files = docsMarkdown($root . '/docs');
$touched = 0;

foreach ($files as $file) {
    $markdown = (string) file_get_contents($file);
    $lines = preg_split('/\R/', $markdown) ?: [];
    $blocks = Framework\Docs\CodeBlocks::parse($markdown);
    $changed = false;

    // De abajo hacia arriba: reemplazar no corre los índices de lo que falta.
    foreach (array_reverse($blocks) as $block) {
        $path = Framework\Docs\CodeBlocks::resolvePath($block['intro'], $root);
        if ($path === null || Framework\Docs\CodeBlocks::isExcerpt($block['info'])) {
            continue;
        }

        $real = @file_get_contents($root . '/' . $path);
        if ($real === false) {
            fwrite(STDERR, "No se pudo leer '{$path}'.\n");
            exit(1);
        }

        $normalized = Framework\Docs\CodeBlocks::normalize($real);
        if (Framework\Docs\CodeBlocks::normalize($block['content']) === $normalized) {
            continue;
        }

        $replacement = array_merge(
            ['```' . $block['info']],
            $normalized === '' ? [] : explode("\n", $normalized),
            ['```']
        );

        array_splice($lines, $block['line'] - 1, $block['end_line'] - $block['line'] + 1, $replacement);
        $changed = true;
        $touched++;
        fwrite(STDOUT, sprintf("%s:%d sincronizado con %s\n", relative($root, $file), $block['line'], $path));
    }

    if ($changed) {
        $tmp = $file . '.tmp';
        $content = implode("\n", $lines);

        if (file_put_contents($tmp, $content) === false || !rename($tmp, $file)) {
            @unlink($tmp);
            fwrite(STDERR, "No se pudo escribir '{$file}'.\n");
            exit(1);
        }
    }
}

fwrite(STDOUT, $touched === 0
    ? "Docs sync: nada que sincronizar." . PHP_EOL
    : "Docs sync OK ({$touched} bloque(s))." . PHP_EOL);

/**
 * @return list<string>
 */
function docsMarkdown(string $dir): array
{
    if (!is_dir($dir)) {
        fwrite(STDERR, "No existe '{$dir}'.\n");
        exit(1);
    }

    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

function relative(string $root, string $path): string
{
    $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalized = str_replace('\\', '/', $path);

    return str_starts_with($normalized, $prefix) ? substr($normalized, strlen($prefix)) : $normalized;
}
