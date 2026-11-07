<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

// La app lee DB_DATABASE (config/container.php); las migraciones tienen que
// apuntar al mismo archivo o el esquema y la app terminan en bases distintas.
// Sin la variable se conserva el default historico database/database.sqlite.
$databaseFile = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: null);
$databaseFile = $databaseFile === '' ? null : $databaseFile;

$migrations = ['%%PHINX_CONFIG_DIR%%/database/migrations'];
$seeds = ['%%PHINX_CONFIG_DIR%%/database/seeds'];

// El demo es opcional: sus migraciones/seeds solo existen tras demo:tasks:install.
if (is_dir(__DIR__ . '/database/migrations/tasks')) {
    $migrations[] = '%%PHINX_CONFIG_DIR%%/database/migrations/tasks';
}
if (is_dir(__DIR__ . '/database/seeds/tasks')) {
    $seeds[] = '%%PHINX_CONFIG_DIR%%/database/seeds/tasks';
}
if (is_dir(__DIR__ . '/database/migrations/contacts')) {
    $migrations[] = '%%PHINX_CONFIG_DIR%%/database/migrations/contacts';
}
if (is_dir(__DIR__ . '/database/seeds/contacts')) {
    $seeds[] = '%%PHINX_CONFIG_DIR%%/database/seeds/contacts';
}

return [
    'paths' => [
        'migrations' => $migrations,
        'seeds'      => $seeds,
    ],
    'environments' => [
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name'    => $databaseFile ?? __DIR__ . '/database/database',
            'suffix'  => $databaseFile === null ? '.sqlite' : '',
        ],
    ],
];
