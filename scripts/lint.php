<?php

declare(strict_types=1);

// Lint de sintaxis sobre app/ y tests/. Uso: composer lint
// Sale con 1 si algún archivo no parsea; no ejecuta la suite.

$roots = [__DIR__ . '/../app', __DIR__ . '/../tests'];
$files = [];

foreach ($roots as $root) {
    if (!is_dir($root)) {
        fwrite(STDERR, "No existe el directorio '{$root}'.\n");
        exit(1);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

sort($files);

$failed = [];
foreach ($files as $file) {
    $process = proc_open(
        [PHP_BINARY, '-l', $file],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );

    if (!is_resource($process)) {
        fwrite(STDERR, "No se pudo ejecutar php -l para '{$file}'.\n");
        exit(1);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);

    if ($exit !== 0) {
        $failed[] = trim($stdout . PHP_EOL . $stderr);
    }
}

if ($failed !== []) {
    fwrite(STDERR, "Lint falló:\n" . implode(PHP_EOL, $failed) . PHP_EOL);
    exit(1);
}

echo 'Lint OK (' . count($files) . " archivos)." . PHP_EOL;
