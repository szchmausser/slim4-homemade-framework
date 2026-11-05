<?php

declare(strict_types=1);

// U7: evita que vuelvan las cifras de tests hardcodeadas, las menciones de
// CDN en runtime y las afirmaciones de PHP 64-bit. Uso: composer docs:check
//
// Además verifica las COPIAS: cada bloque de código de docs/ que declara un
// archivo del repo (la prosa justo encima, con la ruta entre backticks) tiene
// que ser idéntico a ese archivo. Sin esto la guía se pudre sola: la primera
// medición encontró 27 de 56 copias viejas. Arreglo: `composer docs:sync`.
// Un fragmento se marca con `excerpt` en el info string y queda afuera.
//
// El plan de mejoras queda excluido a propósito: es el artefacto que define
// estos patrones y se autoinmolaría. En Markdown se ignoran los bloques de
// código: la guía de vendorización puede nombrar los CDNs en sus comandos de
// descarga, que no son cargas de runtime.

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Falta 'vendor/': corré composer install antes de docs:check.\n");
    exit(1);
}

require $autoload;

use App\Support\Docs\CodeBlocks;

$root = dirname(__DIR__);
$plan = '.ai/sdd/PLAN-mejoras.md';

$docFiles = array_merge(
    [$root . '/README.md'],
    filesUnder($root . '/.ai', ['md'], $root, [$plan]),
    filesUnder($root . '/docs', ['md'], $root)
);

$codeFiles = [];
foreach (['app', 'bootstrap', 'config', 'resources', 'routes'] as $dir) {
    $codeFiles = array_merge(
        $codeFiles,
        filesUnder($root . '/' . $dir, ['php', 'twig'], $root)
    );
}
if (is_file($root . '/public/index.php')) {
    $codeFiles[] = $root . '/public/index.php';
}

$docFiles = array_values(array_unique($docFiles));
$codeFiles = array_values(array_unique($codeFiles));
sort($docFiles);
sort($codeFiles);

$testCount = [
    'regex'   => '/\b(?:65|66|69)\s+tests\b|\b(?:200|204|221)\s+assertions\b/i',
    'message' => 'cifra hardcodeada de tests; usá la salida de composer test',
];
$cdn = [
    'regex'   => '/\b(?:jsdelivr|unpkg)\b/i',
    'message' => 'CDN en runtime; los assets son locales con SRI',
];
$php64 = [
    'regex'   => '/\b64-bit\s+PHP\b|\bPHP\s+64-bit\b|\bPHP\s+\d+(?:\.\d+)*\s*\(\s*64-bit\b/i',
    'message' => 'afirmación 64-bit; el runtime objetivo es 32-bit (PHP_INT_SIZE=4)',
];

$violations = [];

foreach ($docFiles as $file) {
    $content = readDoc($file, $violations);
    if ($content === null) {
        continue;
    }

    // Las cifras se revisan también dentro de bloques de código: los
    // `# esperado: OK (65 tests...)` de la guía también quedaron viejos.
    checkRule($file, $root, $content, $testCount, $violations);

    // Para CDN/64-bit solo cuenta la prosa: los bloques de código pueden
    // mostrar comandos legítimos de vendorización o el output de check:termux.
    $prose = stripFencedCode($content);
    checkRule($file, $root, $prose, $cdn, $violations);
    checkRule($file, $root, $prose, $php64, $violations);
}

foreach ($codeFiles as $file) {
    $content = readDoc($file, $violations);
    if ($content === null) {
        continue;
    }

    checkRule($file, $root, $content, $cdn, $violations);
    checkRule($file, $root, $content, $php64, $violations);
}

$copies = checkCopies($root . '/docs', $root);
$violations = array_merge($violations, $copies['violations']);

if ($violations !== []) {
    fwrite(STDERR, "Docs check falló:\n" . implode(PHP_EOL, $violations) . PHP_EOL);
    exit(1);
}

echo 'Docs OK (' . count($docFiles) . ' markdown, ' . count($codeFiles) . ' fuentes, '
    . $copies['checked'] . " copias de archivo verificadas)." . PHP_EOL;

/**
 * Verifica que cada copia de archivo en los Markdown de docs/ sea idéntica al
 * archivo real. Un fence cuyo info string lleva `excerpt` es un fragmento y se
 * saltea a propósito (no hay nada que comparar).
 *
 * @return array{violations: string[], checked: int}
 */
function checkCopies(string $docsDir, string $root): array
{
    $violations = [];
    $checked = 0;

    foreach (filesUnder($docsDir, ['md'], $root) as $file) {
        $markdown = file_get_contents($file);
        if ($markdown === false) {
            $violations[] = 'No se pudo leer ' . relativePath($root, $file);
            continue;
        }

        foreach (CodeBlocks::parse($markdown) as $block) {
            $path = CodeBlocks::resolvePath($block['intro'], $root);
            if ($path === null || CodeBlocks::isExcerpt($block['info'])) {
                continue;
            }

            $real = @file_get_contents($root . '/' . $path);
            if ($real === false) {
                $violations[] = sprintf('%s:%d: no se pudo leer el archivo declarado «%s»', relativePath($root, $file), $block['line'], $path);
                continue;
            }

            $checked++;
            if (CodeBlocks::normalize($real) === CodeBlocks::normalize($block['content'])) {
                continue;
            }

            $violations[] = sprintf(
                '%s:%d: la copia de «%s» difiere del archivo real — corré `composer docs:sync` '
                . '(o marcá el fence como `excerpt` si es un fragmento)',
                relativePath($root, $file),
                $block['line'],
                $path
            );
        }
    }

    return ['violations' => $violations, 'checked' => $checked];
}

/**
 * @param string[] $extensions
 * @param string[] $skip
 * @return string[]
 */
function filesUnder(string $dir, array $extensions, string $root, array $skip = []): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }

        $path = $file->getPathname();
        if (in_array(relativePath($root, $path), $skip, true)) {
            continue;
        }

        $files[] = $path;
    }

    return $files;
}

function relativePath(string $root, string $path): string
{
    $prefix = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $normalized = str_replace('\\', '/', $path);

    return str_starts_with($normalized, $prefix)
        ? substr($normalized, strlen($prefix))
        : $normalized;
}

function stripFencedCode(string $content): string
{
    $lines = preg_split('/\R/', $content) ?: [];
    $inFence = false;

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(?:```|~~~)/', $line) === 1) {
            $inFence = !$inFence;
            $lines[$i] = '';
            continue;
        }

        if ($inFence) {
            $lines[$i] = '';
        }
    }

    return implode("\n", $lines);
}

/**
 * @param array{regex: string, message: string} $rule
 * @param string[] $violations
 */
function checkRule(string $file, string $root, string $content, array $rule, array &$violations): void
{
    foreach (preg_split('/\R/', $content) ?: [] as $i => $line) {
        if (preg_match($rule['regex'], $line, $match) !== 1) {
            continue;
        }

        $violations[] = sprintf(
            '%s:%d: %s — «%s»',
            relativePath($root, $file),
            $i + 1,
            $rule['message'],
            trim($match[0])
        );
    }
}

/**
 * @param string[] $violations
 */
function readDoc(string $file, array &$violations): ?string
{
    $content = file_get_contents($file);
    if ($content === false) {
        $violations[] = 'No se pudo leer ' . $file;
        return null;
    }

    return $content;
}
