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

// Los demos son opcionales y se descubren por convención: cada uno aporta su
// subdirectorio en database/migrations/<demo>/ y database/seeds/<demo>/ al
// instalarse. Agregar un demo es crear esos directorios, sin editar este
// archivo. Phinx ordena las migraciones por versión, así que el orden de
// descubrimiento no importa.
foreach (glob(__DIR__ . '/database/migrations/*', GLOB_ONLYDIR) ?: [] as $demoMigrations) {
    $migrations[] = '%%PHINX_CONFIG_DIR%%/database/migrations/' . basename($demoMigrations);
}

foreach (glob(__DIR__ . '/database/seeds/*', GLOB_ONLYDIR) ?: [] as $demoSeeds) {
    $seeds[] = '%%PHINX_CONFIG_DIR%%/database/seeds/' . basename($demoSeeds);
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
