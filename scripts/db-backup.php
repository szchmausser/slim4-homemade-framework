<?php

declare(strict_types=1);

// Backup timestamped de SQLite + sidecars WAL (-wal/-shm/-journal) si existen.
// Uso: composer db:backup
// Destino: storage/backups/YYYYMMDD-HHMMSS/ (gitignored: son datos reales).
// Hacelo con la app quieta: en caliente la copia puede ir a medias (WAL).

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

$db = $_ENV['DB_DATABASE'] ?? __DIR__ . '/../database/database.sqlite';

if ($db === ':memory:' || !file_exists($db)) {
    fwrite(STDERR, "No hay base para respaldar en '{$db}'.\n");
    exit(1);
}

$storage = __DIR__ . '/../storage';
if (!is_dir($storage)) {
    mkdir($storage, 0777, true);
}
$dest = realpath($storage) . '/backups/' . date('Ymd-His');
mkdir($dest, 0777, true);

$n = 0;
foreach ([$db, $db . '-wal', $db . '-shm', $db . '-journal'] as $file) {
    if (file_exists($file)) {
        copy($file, $dest . '/' . basename($file));
        $n++;
    }
}

echo "Backup en {$dest} ({$n} archivos)." . PHP_EOL;
