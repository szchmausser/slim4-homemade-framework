<?php

declare(strict_types=1);

// PHPStan dev-only como PHAR: no agrega dependencias de Composer ni necesita
// compilación en Termux. Uso: composer phpstan
//
// El phar se cachea en storage/cache/ (gitignored). Para usar uno propio o
// trabajar sin red: PHPSTAN_PHAR=/ruta/al/phpstan.phar composer phpstan

const PHPSTAN_VERSION = '2.3.5';
const PHPSTAN_URL = 'https://github.com/phpstan/phpstan/releases/download/' . PHPSTAN_VERSION . '/phpstan.phar';
const PHPSTAN_SHA256 = '4b3ed7850a20b092331bd1040e4f54a1d3c0559b4cf006431073997ebcb4324b';

$root = dirname(__DIR__);
$override = $_ENV['PHPSTAN_PHAR'] ?? (getenv('PHPSTAN_PHAR') ?: null);

if ($override !== null) {
    $phar = (string) $override;
    if (!is_file($phar)) {
        fwrite(STDERR, "PHPSTAN_PHAR apunta a un archivo inexistente: '{$phar}'.\n");
        exit(1);
    }
} else {
    $phar = $root . '/storage/cache/phpstan.phar';
    try {
        ensurePhar($phar);
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

$config = $root . '/phpstan.neon';
if (!is_file($config)) {
    fwrite(STDERR, "No existe '{$config}'.\n");
    exit(1);
}

$process = proc_open(
    [PHP_BINARY, $phar, 'analyse', '--configuration=' . $config, '--no-progress'],
    [0 => STDIN, 1 => STDOUT, 2 => STDERR],
    $pipes
);

if (!is_resource($process)) {
    fwrite(STDERR, "No se pudo ejecutar PHPStan.\n");
    exit(1);
}

exit(proc_close($process));

function ensurePhar(string $target): void
{
    if (is_file($target)) {
        $hash = hash_file('sha256', $target);
        if ($hash === PHPSTAN_SHA256) {
            return;
        }

        fwrite(STDERR, "El PHPStan cacheado no coincide con la versión fijada; se descarga de nuevo.\n");
        @unlink($target);
    }

    $dir = dirname($target);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("No se pudo crear '{$dir}'.");
    }

    $part = $target . '.part';
    @unlink($part);

    fwrite(STDERR, 'Descargando PHPStan ' . PHPSTAN_VERSION . " (~30 MB)..." . PHP_EOL);

    $context = stream_context_create([
        'http' => [
            'timeout'         => 600,
            'follow_location' => 1,
            'user_agent'      => 'slim4-homemade-framework-verify',
        ],
    ]);

    if (!@copy(PHPSTAN_URL, $part, $context) || !is_file($part)) {
        @unlink($part);
        throw new RuntimeException(
            'No se pudo descargar PHPStan. Revisá la conexión o descargalo a mano y usá '
            . 'PHPSTAN_PHAR=/ruta/al/phpstan.phar composer phpstan.'
        );
    }

    $hash = hash_file('sha256', $part);
    if ($hash !== PHPSTAN_SHA256) {
        @unlink($part);
        throw new RuntimeException(
            'El SHA256 de PHPStan no coincide. Se descartó el archivo; no se ejecuta código no verificado.'
        );
    }

    if (!rename($part, $target)) {
        @unlink($part);
        throw new RuntimeException("No se pudo mover el phar a '{$target}'.");
    }

    fwrite(STDERR, "PHPStan listo en '{$target}'." . PHP_EOL);
}
