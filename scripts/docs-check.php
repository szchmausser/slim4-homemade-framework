<?php

declare(strict_types=1);

// U7: evita que vuelvan las cifras de tests hardcodeadas, las menciones de
// CDN en runtime y las afirmaciones de PHP 64-bit. Uso: composer docs:check
//
// El plan de mejoras queda excluido a propósito: es el artefacto que define
// estos patrones y se autoinmolaría. En Markdown se ignoran los bloques de
// código: la guía de vendorización puede nombrar los CDNs en sus comandos de
// descarga, que no son cargas de runtime.

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

if ($violations !== []) {
    fwrite(STDERR, "Docs check falló:\n" . implode(PHP_EOL, $violations) . PHP_EOL);
    exit(1);
}

echo 'Docs OK (' . count($docFiles) . ' markdown, ' . count($codeFiles) . " fuentes)." . PHP_EOL;

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
