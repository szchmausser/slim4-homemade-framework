<?php

declare(strict_types=1);

// Backup consistente de SQLite con VACUUM INTO.
// Uso: composer db:backup
// Destino: storage/backups/YYYYMMDD-HHMMSS.sqlite (gitignored: son datos reales).
//
// VACUUM INTO hace una lectura transaccional y escribe un archivo nuevo: la
// copia sale consistente aunque la app esté escribiendo, sin copiar -wal/-shm/
// -journal en caliente.
//
// Retención: KEEP (env, default 10) conserva los últimos archivos .sqlite.
// Los directorios de backups viejos no se tocan.

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

/**
 * GitHub Actions arranca PHP con variables_order=GPCS: $_ENV queda vacío
 * aunque la variable exista. getenv() es el fallback que también usa
 * phinx.php para que DB_DATABASE no dependa de la config del SAPI.
 */
function envOrDefault(string $name, string $default): string
{
    $value = $_ENV[$name] ?? null;
    if (!is_string($value) || $value === '') {
        $value = getenv($name);
    }

    return is_string($value) && $value !== '' ? $value : $default;
}

$db = envOrDefault('DB_DATABASE', __DIR__ . '/../database/database.sqlite');

if ($db === ':memory:' || !is_file($db)) {
    fwrite(STDERR, "No hay base para respaldar en '{$db}'.\n");
    exit(1);
}

$storage = __DIR__ . '/../storage';
if (!is_dir($storage) && !mkdir($storage, 0777, true) && !is_dir($storage)) {
    fwrite(STDERR, "No se pudo crear '{$storage}'.\n");
    exit(1);
}

$backupDir = $storage . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "No se pudo crear '{$backupDir}'.\n");
    exit(1);
}
$backupDir = realpath($backupDir);

$base = $backupDir . '/' . date('Ymd-His');
$dest = $base . '.sqlite';
for ($suffix = 2; file_exists($dest); $suffix++) {
    $dest = $base . '-' . $suffix . '.sqlite';
}

try {
    $pdo = new PDO('sqlite:' . $db, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    // SQLite no admite parámetros en VACUUM INTO: se escapa la ruta como
    // literal SQL duplicando las comillas simples.
    $target = str_replace("'", "''", $dest);
    $pdo->exec("VACUUM INTO '{$target}'");
} catch (PDOException $e) {
    if (file_exists($dest)) {
        @unlink($dest);
    }
    fwrite(STDERR, "Falló el backup de '{$db}': {$e->getMessage()}\n");
    exit(1);
}

$keep = max(1, (int) envOrDefault('KEEP', '10'));
$backups = [];
foreach (glob($backupDir . '/*.sqlite') ?: [] as $file) {
    if (is_file($file) && preg_match('/^\d{8}-\d{6}(?:-\d+)?\.sqlite$/', basename($file))) {
        $backups[] = $file;
    }
}
// strnatcmp ordena timestamps y sufijos (-2, -3, ... -10) como números.
usort($backups, static fn (string $a, string $b): int => strnatcmp(basename($b), basename($a)));
foreach (array_slice($backups, $keep) as $old) {
    @unlink($old);
}

$size = filesize($dest);
echo "Backup en {$dest} ({$size} bytes)." . PHP_EOL;
