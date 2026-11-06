<?php

declare(strict_types=1);

// Migración fresh sobre una SQLite temporal. Uso: composer migrate:fresh
// No toca database/database.sqlite ni la DB de .env: borra el archivo temporal,
// corre TODAS las migraciones con Phinx y verifica que existan las tablas base.

require __DIR__ . '/../vendor/autoload.php';

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

$root = dirname(__DIR__);
$dir = $root . '/storage/cache/verify';

if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    fwrite(STDERR, "No se pudo crear '{$dir}'.\n");
    exit(1);
}

$db = $dir . '/fresh.sqlite';
$files = [$db, $db . '-wal', $db . '-shm', $db . '-journal'];
foreach ($files as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}

// phinx.php lee DB_DATABASE de $_ENV/getenv. Se setea ANTES de requerirlo
// para que Dotenv no pise el path temporal con el .env real.
$_ENV['DB_DATABASE'] = $db;
$_SERVER['DB_DATABASE'] = $db;
putenv('DB_DATABASE=' . $db);

try {
    $config = new Config(require $root . '/phinx.php', $root . '/phinx.php');
    $manager = new Manager($config, new ArrayInput([]), new NullOutput());
    $manager->migrate($config->getDefaultEnvironment());

    $pdo = new PDO('sqlite:' . $db, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $pdo = null;

    $required = ['users', 'remember_tokens', 'login_attempts'];
    if (is_dir($root . '/database/migrations/tasks')) {
        $required[] = 'tasks';
    }
    $missing = array_values(array_diff($required, $tables));
    if ($missing !== []) {
        throw new RuntimeException(
            'La migración fresh no creó: ' . implode(', ', $missing) . '.'
        );
    }

    echo 'Migración fresh OK (' . count($required) . ' tablas base en DB temporal).' . PHP_EOL;
} catch (Throwable $e) {
    $error = $e;
} finally {
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (isset($error)) {
    fwrite(STDERR, 'Migración fresh falló: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
