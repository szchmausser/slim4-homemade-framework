<?php

declare(strict_types=1);

// Instala o elimina el scaffold tasks. Uso:
//   composer demo:install
//   composer demo:remove
//
// El scaffold vive en framework/scaffolds/tasks/ con la MISMA estructura relativa que
// tendrá en el repo: instalar es copiar cada archivo a su destino; eliminar es
// borrar esos destinos. La fuente (framework/scaffolds/tasks) nunca se toca.
//
// Instalar también corre las migraciones pendientes y el TaskSeeder.
// Eliminar también borra la tabla tasks y las filas de sus migraciones en
// phinxlog. --no-db permite operar solo sobre archivos.

$root = dirname(__DIR__);
$scaffold = $root . '/framework/scaffolds/tasks';
$command = $argv[1] ?? '';
$force = in_array('--force', $argv, true);
$noDb = in_array('--no-db', $argv, true);
$noFiles = in_array('--no-files', $argv, true);

// El runtime de tests/CI puede pasar DB_DATABASE por entorno. Se replica en
// $_ENV para que Dotenv immutable y config/container.php usen la misma base.
$envDb = getenv('DB_DATABASE');
if (is_string($envDb) && $envDb !== '') {
    $_ENV['DB_DATABASE'] = $envDb;
    $_SERVER['DB_DATABASE'] = $envDb;
}

if (!is_dir($scaffold)) {
    fwrite(STDERR, "No existe '{$scaffold}'.\n");
    exit(1);
}

if ($command !== 'install' && $command !== 'remove') {
    fwrite(STDERR, "Uso: php scripts/demo.php install|remove [--force] [--no-db] [--no-files]\n");
    exit(1);
}

$files = scaffoldFiles($scaffold);

if ($command === 'install') {
    if (!$noDb) {
        $autoload = $root . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            fwrite(STDERR, "Falta 'vendor/': corré composer install antes de demo:install.\n");
            exit(1);
        }

        require_once $autoload;

        if (!class_exists(\Phinx\Config\Config::class)) {
            fwrite(STDERR, "Falta Phinx en 'vendor/': instalá dependencias de desarrollo antes de demo:install.\n");
            exit(1);
        }

        $configuredDb = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: null);
        if ($configuredDb === ':memory:') {
            fwrite(STDERR, "El demo necesita una base persistente; DB_DATABASE=:memory: no sirve para demo:install.\n");
            exit(1);
        }

        assertNoMigrationCollisions($root, $scaffold);
    }

    $conflicts = [];
    foreach ($files as $relative => $source) {
        $destination = $root . '/' . $relative;
        if (is_file($destination)) {
            $conflicts[] = $relative;
        }
    }

    if ($conflicts !== [] && !$force) {
        fwrite(STDERR, "El scaffold tasks ya tiene archivos instalados:\n");
        foreach ($conflicts as $relative) {
            fwrite(STDERR, "  - {$relative}\n");
        }
        fwrite(STDERR, "Usá --force para sobrescribirlos o composer demo:remove primero.\n");
        exit(1);
    }

    foreach ($files as $relative => $source) {
        $destination = $root . '/' . $relative;
        $dir = dirname($destination);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            fwrite(STDERR, "No se pudo crear '{$dir}'.\n");
            exit(1);
        }
        if (!copy($source, $destination)) {
            fwrite(STDERR, "No se pudo copiar '{$relative}'.\n");
            exit(1);
        }
    }

    clearTwigCache($root);
    echo 'Scaffold tasks instalado (' . count($files) . " archivos).\n";

    if ($noDb) {
        echo "DB: omitida por --no-db.\n";
    } else {
        try {
            migrateAndSeed($root);
        } catch (Throwable $e) {
            fwrite(STDERR, "Los archivos quedaron instalados, pero la DB falló: {$e->getMessage()}\n");
            fwrite(STDERR, "Corregí el error y corré composer migrate && vendor/bin/phinx seed:run -s TaskSeeder.\n");
            exit(1);
        }
    }

    echo "Para quitarlo: composer demo:remove\n";
    exit(0);
}

if (!$noDb) {
    try {
        removeDemoDatabase($root, $scaffold);
    } catch (Throwable $e) {
        fwrite(STDERR, "No se pudo limpiar la DB; no se borraron archivos: {$e->getMessage()}\n");
        exit(1);
    }
}

$removed = 0;
if (!$noFiles) {
    foreach ($files as $relative => $source) {
        $destination = $root . '/' . $relative;
        if (!is_file($destination)) {
            continue;
        }
        if (!unlink($destination)) {
            fwrite(STDERR, "No se pudo borrar '{$relative}'.\n");
            exit(1);
        }
        $removed++;
        removeEmptyParents(dirname($destination), $root);
    }
}

clearTwigCache($root);
if ($removed === 0) {
    echo "No había archivos del scaffold tasks instalados.\n";
} else {
    echo "Scaffold tasks removido ({$removed} archivos).\n";
}

if ($noDb) {
    echo "DB: omitida por --no-db.\n";
}

if ($noFiles) {
    echo "Archivos: omitidos por --no-files.\n";
}

echo "framework/scaffolds/tasks/ sigue disponible para reinstalar.\n";

function migrateAndSeed(string $root): void
{
    require_once $root . '/vendor/autoload.php';

    $config = new \Phinx\Config\Config(require $root . '/phinx.php', $root . '/phinx.php');
    $manager = new \Phinx\Migration\Manager(
        $config,
        new \Symfony\Component\Console\Input\ArrayInput([]),
        new \Symfony\Component\Console\Output\NullOutput()
    );

    $environment = $config->getDefaultEnvironment();
    $manager->migrate($environment);
    $manager->seed($environment, 'TaskSeeder');

    echo "DB: migraciones aplicadas y TaskSeeder ejecutado.\n";
}

function removeDemoDatabase(string $root, string $scaffold): void
{
    $database = demoDatabasePath($root);
    if ($database === null) {
        echo "DB: no hay base persistente; nada que limpiar.\n";
        return;
    }

    $pdo = new \PDO('sqlite:' . $database, null, null, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
    ]);

    $hasLog = (int) $pdo->query(
        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'phinxlog'"
    )->fetchColumn();

    if ($hasLog === 0) {
        echo "DB: no hay phinxlog; nada que limpiar.\n";
        return;
    }

    $migrations = demoMigrations($scaffold);
    if ($migrations === []) {
        echo "DB: no se encontraron migraciones del scaffold; nada que limpiar.\n";
        return;
    }

    // Se compara versión Y nombre: un 004 del usuario (por ejemplo
    // CreateRecetasTable) no es una migración del demo y no debe borrarse.
    $applied = [];
    foreach ($pdo->query('SELECT version, migration_name FROM phinxlog') as $row) {
        $version = (int) $row['version'];
        if (($migrations[$version] ?? null) === $row['migration_name']) {
            $applied[] = $version;
        }
    }

    if ($applied === []) {
        echo "DB: no hay migraciones del demo aplicadas.\n";
        return;
    }

    $placeholders = implode(',', array_fill(0, count($applied), '?'));

    // El scaffold tasks solo posee la tabla tasks. Si en el futuro agrega más,
    // esta lista es el punto único a actualizar.
    $pdo->exec('DROP TABLE IF EXISTS tasks');

    $delete = $pdo->prepare("DELETE FROM phinxlog WHERE version IN ({$placeholders})");
    $delete->execute($applied);

    echo 'DB: tabla tasks eliminada y ' . count($applied)
        . " migraciones del demo borradas de phinxlog.\n";
}

function demoDatabasePath(string $root): ?string
{
    $autoload = $root . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists(\Dotenv\Dotenv::class)) {
            \Dotenv\Dotenv::createImmutable($root)->safeLoad();
        }
    }

    $database = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: null);
    $database = is_string($database) ? trim($database) : null;

    if ($database === null || $database === '') {
        $database = $root . '/database/database.sqlite';
    }

    if ($database === ':memory:' || !is_file($database)) {
        return null;
    }

    return $database;
}

/**
 * @return array<int, string> versión => nombre de clase de la migración
 */
function demoMigrations(string $scaffold): array
{
    $directory = $scaffold . '/database/migrations/tasks';
    $migrations = [];

    foreach (glob($directory . '/*.php') ?: [] as $file) {
        if (preg_match('/^(\d+)_(.+)\.php$/', basename($file), $match) !== 1) {
            continue;
        }

        $name = str_replace(' ', '', ucwords(str_replace('_', ' ', $match[2])));
        $migrations[(int) $match[1]] = $name;
    }

    ksort($migrations);

    return $migrations;
}

function assertNoMigrationCollisions(string $root, string $scaffold): void
{
    $migrations = demoMigrations($scaffold);
    if ($migrations === []) {
        fwrite(STDERR, "El scaffold no tiene migraciones.\n");
        exit(1);
    }

    $existing = [];
    foreach (glob($root . '/database/migrations/*.php') ?: [] as $file) {
        if (preg_match('/^(\d+)_/', basename($file), $match) === 1) {
            $existing[(int) $match[1]] = basename($file);
        }
    }

    foreach ($migrations as $version => $name) {
        if (!isset($existing[$version])) {
            continue;
        }

        fwrite(STDERR, sprintf(
            "Colisión de migraciones: la versión %d del demo (%s) ya está usada por %s.\n",
            $version,
            $name,
            $existing[$version]
        ));
        fwrite(STDERR, "Renumerá tu migración o el scaffold antes de instalar el demo.\n");
        exit(1);
    }
}

function scaffoldFiles(string $scaffold): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scaffold, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $source = $file->getPathname();
        $relative = str_replace('\\', '/', substr($source, strlen($scaffold) + 1));
        $files[$relative] = $source;
    }

    ksort($files);

    return $files;
}

/**
 * Borra los directorios que quedaron vacíos, sin salir de los roots del repo
 * que existen siempre aunque el demo no esté instalado.
 */
function removeEmptyParents(string $dir, string $root): void
{
    $keep = [
        $root,
        $root . '/app',
        $root . '/config',
        $root . '/database',
        $root . '/database/migrations',
        $root . '/database/seeds',
        $root . '/resources',
        $root . '/resources/views',
        $root . '/routes',
        $root . '/tests',
    ];

    while (!in_array($dir, $keep, true) && is_dir($dir)) {
        $entries = array_diff(scandir($dir) ?: [], ['.', '..']);
        if ($entries !== []) {
            return;
        }
        if (!rmdir($dir)) {
            return;
        }
        $dir = dirname($dir);
    }
}

function clearTwigCache(string $root): void
{
    $cache = $root . '/storage/cache/twig';
    if (!is_dir($cache)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
}
