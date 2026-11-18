<?php

declare(strict_types=1);

// Instala o elimina un scaffold demo. Uso:
//   php scripts/demo.php install [tasks|contacts|notas]
//   php scripts/demo.php remove  [tasks|contacts|notas]
// Comandos Composer: demo:tasks:install/remove, demo:contacts:install/remove y demo:notas:install/remove.
// demo:install/remove quedan como aliases compatibles de tasks.
//
// Cada demo vive en framework/scaffolds/<nombre>/ con la MISMA estructura
// relativa que tendrá en el repo: instalar es copiar cada archivo a su destino;
// eliminar es borrar esos destinos. La fuente nunca se toca.

// DEMO_ROOT permite correr el instalador contra una copia temporal (tests).
// En uso normal queda vacio y el root es el del proyecto.
$root = getenv('DEMO_ROOT') ?: dirname(__DIR__);
$root = rtrim((string) $root, '/');

if (!is_dir($root)) {
    fwrite(STDERR, "DEMO_ROOT no existe: {$root}\n");
    exit(1);
}

// `$argv` sólo existe en CLI. PHPStan lo marca como indefinido en el ámbito
// global, y tiene razón: este script puede incluirse desde otro contexto. Se
// lee de `$_SERVER`, que es el superglobal que el SAPI siempre puebla, y se cae
// a un array vacío para que el script explique su uso en vez de romper.
$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? '';
$name = 'tasks';
foreach (array_slice($argv, 2) as $argument) {
    if (!str_starts_with($argument, '--')) {
        $name = $argument;
        break;
    }
}
$force = in_array('--force', $argv, true);
$noDb = in_array('--no-db', $argv, true);
$noFiles = in_array('--no-files', $argv, true);

$demos = [
    'tasks' => [
        'tables' => ['tasks'],
        'migrations' => 'database/migrations/tasks',
        'seeder' => 'TaskSeeder',
    ],
    'contacts' => [
        'tables' => ['contacts'],
        'migrations' => 'database/migrations/contacts',
        'seeder' => 'ContactSeeder',
    ],
    'notas' => [
        'tables' => ['notas'],
        'migrations' => 'database/migrations/notas',
        'seeder' => 'NotaSeeder',
    ],
];

$meta = $demos[$name] ?? null;
$scaffold = $root . '/framework/scaffolds/' . $name;
$commandPrefix = 'demo:' . $name;

// El runtime de tests/CI puede pasar DB_DATABASE por entorno. Se replica en
// $_ENV para que Dotenv immutable y config/container.php usen la misma base.
$envDb = getenv('DB_DATABASE');
if (is_string($envDb) && $envDb !== '') {
    $_ENV['DB_DATABASE'] = $envDb;
    $_SERVER['DB_DATABASE'] = $envDb;
}

if ($meta === null) {
    fwrite(STDERR, "Demo desconocido: '{$name}'. Usa tasks, contacts o notas.\n");
    exit(1);
}

if (!is_dir($scaffold)) {
    fwrite(STDERR, "No existe '{$scaffold}'.\n");
    exit(1);
}

if ($command !== 'install' && $command !== 'remove') {
    fwrite(STDERR, "Uso: php scripts/demo.php install|remove [tasks|contacts] [--force] [--no-db] [--no-files]\n");
    exit(1);
}

$files = scaffoldFiles($scaffold);

if ($command === 'install') {
    if (!$noDb) {
        $autoload = $root . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            fwrite(STDERR, "Falta 'vendor/': ejecuta composer install antes de {$commandPrefix}:install.\n");
            exit(1);
        }

        require_once $autoload;

        if (!class_exists(\Phinx\Config\Config::class)) {
            fwrite(STDERR, "Falta Phinx en 'vendor/': instala dependencias de desarrollo antes de {$commandPrefix}:install.\n");
            exit(1);
        }

        $configuredDb = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: null);
        if ($configuredDb === ':memory:') {
            fwrite(STDERR, "El demo necesita una base persistente; DB_DATABASE=:memory: no sirve para {$commandPrefix}:install.\n");
            exit(1);
        }

        assertNoMigrationCollisions($root, $scaffold, $meta['migrations'], $name);
    }

    $conflicts = [];
    foreach ($files as $relative => $source) {
        $destination = $root . '/' . $relative;
        if (is_file($destination)) {
            $conflicts[] = $relative;
        }
    }

    if ($conflicts !== [] && !$force) {
        fwrite(STDERR, "El scaffold {$name} ya tiene archivos instalados:" . PHP_EOL);
        foreach ($conflicts as $relative) {
            fwrite(STDERR, "  - {$relative}" . PHP_EOL);
        }
        fwrite(STDERR, "Usa `composer {$commandPrefix}:install -- --force` para sobrescribirlos, o `composer {$commandPrefix}:remove` primero." . PHP_EOL);
        exit(1);
    }

    $created = [];
    $backups = [];
    $backupDir = null;

    try {
        foreach ($files as $relative => $source) {
            $destination = $root . '/' . $relative;
            $dir = dirname($destination);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("No se pudo crear '{$dir}'.");
            }

            if (is_file($destination)) {
                // --force no debe destruir la version del usuario: se guarda
                // una copia y el rollback la restaura si la DB falla.
                $backupDir ??= $root . '/storage/backups/demo-install-'
                    . $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
                $backup = $backupDir . '/' . $relative;
                $backupParent = dirname($backup);
                if (!is_dir($backupParent) && !mkdir($backupParent, 0700, true) && !is_dir($backupParent)) {
                    throw new RuntimeException("No se pudo crear el backup '{$backupParent}'.");
                }
                if (!copy($destination, $backup)) {
                    throw new RuntimeException("No se pudo respaldar '{$relative}'.");
                }
                $backups[$relative] = $backup;
            } else {
                $created[] = $relative;
            }

            if (!copy($source, $destination)) {
                throw new RuntimeException("No se pudo copiar '{$relative}'.");
            }
        }
    } catch (Throwable $e) {
        $rolledBack = rollbackInstall($root, $created, $backups);
        clearTwigCache($root);
        fwrite(STDERR, "Install revertido: {$rolledBack} archivos restaurados/borrados ({$e->getMessage()})." . PHP_EOL);
        exit(1);
    }

    clearTwigCache($root);
    echo 'Scaffold ' . $name . ' instalado (' . count($files) . " archivos)." . PHP_EOL;
    if ($backupDir !== null) {
        echo "Backup de archivos sobrescritos: {$backupDir}" . PHP_EOL;
    }

    if ($noDb) {
        echo "DB: omitida por --no-db." . PHP_EOL;
    } else {
        try {
            migrateAndSeed($root, $meta['seeder']);
        } catch (Throwable $e) {
            // Install atomico: o queda todo, o no queda nada. Un install a
            // medias deja archivos que chocan con el reintento y una DB en un
            // estado que nadie sabe.
            fwrite(STDERR, "La DB fallo: {$e->getMessage()}" . PHP_EOL);

            try {
                removeDemoDatabase($root, $scaffold, $meta);
            } catch (Throwable $dbError) {
                fwrite(STDERR, "Tampoco se pudo limpiar la DB del demo: {$dbError->getMessage()}" . PHP_EOL);
                fwrite(STDERR, "Revisa la base y, si hace falta, ejecuta composer {$commandPrefix}:remove." . PHP_EOL);
            }

            $rolledBack = rollbackInstall($root, $created, $backups);
            clearTwigCache($root);
            fwrite(STDERR, "Install revertido: {$rolledBack} archivos restaurados/borrados." . PHP_EOL);
            fwrite(STDERR, "Corrige el error y vuelve a ejecutar composer {$commandPrefix}:install." . PHP_EOL);
            exit(1);
        }
    }

    echo "Para quitarlo: composer {$commandPrefix}:remove" . PHP_EOL;
    exit(0);
}

if (!$noFiles) {
    $edited = [];
    foreach ($files as $relative => $source) {
        $destination = $root . '/' . $relative;
        if (is_file($destination) && hash_file('sha256', $destination) !== hash_file('sha256', $source)) {
            $edited[] = $relative;
        }
    }

    if ($edited !== [] && !$force) {
        fwrite(STDERR, "El scaffold {$name} tiene archivos editados:" . PHP_EOL);
        foreach ($edited as $relative) {
            fwrite(STDERR, "  - {$relative}" . PHP_EOL);
        }
        fwrite(STDERR, "Usa `composer {$commandPrefix}:remove -- --force` para respaldarlos y borrarlos, o `--no-files` para tocar solo la DB." . PHP_EOL);
        exit(1);
    }
}

if (!$noDb) {
    try {
        removeDemoDatabase($root, $scaffold, $meta);
    } catch (Throwable $e) {
        fwrite(STDERR, "No se pudo limpiar la DB; no se borraron archivos: {$e->getMessage()}\n");
        exit(1);
    }
}

$removed = 0;
if (!$noFiles) {
    $removed = removeDemoFiles($root, $files, $force, $name);
    // Sin esto quedan plantillas compiladas de vistas que ya no existen.
    clearTwigCache($root);
    if ($removed === 0) {
        echo "No había archivos del scaffold {$name} instalados.\n";
    } else {
        echo "Scaffold {$name} removido ({$removed} archivos).\n";
    }
}

if ($noDb) {
    echo "DB: omitida por --no-db.\n";
}

if ($noFiles) {
    echo "Archivos: omitidos por --no-files.\n";
}

echo "framework/scaffolds/{$name}/ sigue disponible para reinstalar.\n";

/**
 * Borra los archivos instalados del scaffold y los directorios que quedan
 * vacíos. Devuelve cuántos archivos existían y se borraron.
 *
 * @param array<string, string> $files relativo => origen en el scaffold
 */
function removeDemoFiles(string $root, array $files, bool $force = false, string $name = 'demo'): int
{
    $removed = 0;
    $backupDir = null;

    foreach ($files as $relative => $source) {
        $destination = $root . '/' . $relative;
        if (!is_file($destination)) {
            continue;
        }

        $edited = hash_file('sha256', $destination) !== hash_file('sha256', $source);
        if ($edited) {
            if (!$force) {
                fwrite(STDERR, "Archivo editado, no se borra: '{$relative}'." . PHP_EOL);
                continue;
            }

            $backupDir ??= $root . '/storage/backups/demo-remove-'
                . $name . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
            $backup = $backupDir . '/' . $relative;
            $backupParent = dirname($backup);
            if (!is_dir($backupParent) && !mkdir($backupParent, 0700, true) && !is_dir($backupParent)) {
                fwrite(STDERR, "No se pudo crear el backup '{$backupParent}'." . PHP_EOL);
                continue;
            }
            if (!copy($destination, $backup)) {
                fwrite(STDERR, "No se pudo respaldar '{$relative}'; se conserva el original." . PHP_EOL);
                continue;
            }
            echo "Backup de archivo editado: {$backup}" . PHP_EOL;
        }

        if (!unlink($destination)) {
            fwrite(STDERR, "No se pudo borrar '{$relative}'." . PHP_EOL);
            continue;
        }
        $removed++;
        removeEmptyParents(dirname($destination), $root);
    }

    return $removed;
}

function rollbackInstall(string $root, array $created, array $backups): int
{
    $restored = 0;

    foreach ($backups as $relative => $backup) {
        $destination = $root . '/' . $relative;
        if (!is_file($backup)) {
            continue;
        }
        if (!copy($backup, $destination)) {
            fwrite(STDERR, "No se pudo restaurar '{$relative}' desde '{$backup}'." . PHP_EOL);
            continue;
        }
        @unlink($backup);
        removeEmptyParents(dirname($backup), $root);
        $restored++;
    }

    foreach ($created as $relative) {
        $destination = $root . '/' . $relative;
        if (is_file($destination) && unlink($destination)) {
            $restored++;
        }
        removeEmptyParents(dirname($destination), $root);
    }

    return $restored;
}

function migrateAndSeed(string $root, string $seeder): void
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
    $manager->seed($environment, $seeder);

    echo "DB: migraciones aplicadas y {$seeder} ejecutado.\n";
}

/**
 * @param array{tables: list<string>, migrations: string, seeder: string} $meta
 */
function removeDemoDatabase(string $root, string $scaffold, array $meta): void
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

    $migrations = demoMigrations($scaffold . '/' . $meta['migrations']);
    if ($migrations === []) {
        echo "DB: no se encontraron migraciones del scaffold; nada que limpiar.\n";
        return;
    }

    // Se compara versión Y nombre: un 004 del usuario no es la migración 004
    // del demo y no debe borrarse.
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

    foreach ($meta['tables'] as $table) {
        $pdo->exec('DROP TABLE IF EXISTS ' . $table);
    }

    $placeholders = implode(',', array_fill(0, count($applied), '?'));
    $delete = $pdo->prepare("DELETE FROM phinxlog WHERE version IN ({$placeholders})");
    $delete->execute($applied);

    echo 'DB: tabla ' . implode(', ', $meta['tables']) . ' eliminada y '
        . count($applied) . " migraciones del demo borradas de phinxlog.\n";
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
function demoMigrations(string $directory): array
{
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

function assertNoMigrationCollisions(string $root, string $scaffold, string $migrationDirectory, string $demoName): void
{
    $migrations = demoMigrations($scaffold . '/' . $migrationDirectory);
    if ($migrations === []) {
        fwrite(STDERR, "El scaffold no tiene migraciones.\n");
        exit(1);
    }

    $existing = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/database/migrations', FilesystemIterator::SKIP_DOTS)
    );

    // Se ignora la copia ya instalada de ESTE demo: si quedó de una corrida
    // interrumpida, se sobrescribe con --force en vez de bloquear el reintento
    // contra su propia migración.
    $ownDirectory = str_replace('\\', '/', $root . '/database/migrations/' . $demoName . '/');

    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        if (str_starts_with(str_replace('\\', '/', $file->getPathname()), $ownDirectory)) {
            continue;
        }
        if (preg_match('/^(\d+)_/', $file->getFilename(), $match) === 1) {
            $existing[(int) $match[1]] = $file->getFilename();
        }
    }

    foreach ($migrations as $version => $migrationName) {
        if (!isset($existing[$version])) {
            continue;
        }

        fwrite(STDERR, sprintf(
            "Colisión de migraciones: la versión %d del demo (%s) ya está usada por %s.\n",
            $version,
            $migrationName,
            $existing[$version]
        ));
        fwrite(STDERR, "Renumera tu migración o el scaffold antes de instalar el demo.\n");
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
        $root . '/public',
        $root . '/public/assets',
        $root . '/resources',
        $root . '/resources/views',
        $root . '/routes',
        $root . '/tests',
        $root . '/storage',
        $root . '/storage/backups',
        $root . '/storage/cache',
        $root . '/storage/logs',
        $root . '/storage/sessions',
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
