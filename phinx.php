<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

// La app lee DB_DATABASE (config/container.php); las migraciones tienen que
// apuntar al mismo archivo o el esquema y la app terminan en bases distintas.
// Sin la variable se conserva el default historico database/database.sqlite.
$databaseFile = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: null);
$databaseFile = $databaseFile === '' ? null : $databaseFile;

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeds',
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
