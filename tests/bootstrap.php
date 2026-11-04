<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

// Los tests corren contra un SQLite real, no contra :memory:. Asi el esquema
// lo define Phinx (las mismas migraciones que usa la app): si una migracion
// se rompe, pierde una FK o diverge del modelo, la suite lo ve. Un :memory:
// propio por conexion obligaba a duplicar el esquema a mano, justo lo que
// este cambio elimina. El archivo se recrea en cada corrida.
$db = dirname(__DIR__) . '/storage/cache/tests/app.sqlite';
@mkdir(dirname($db), 0777, true);
foreach ([$db, $db . '-wal', $db . '-shm'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

// bootstrap/app.php lee $_ENV (Dotenv inmutable no lo pisa).
putenv('DB_DATABASE=' . $db);
$_ENV['DB_DATABASE'] = $db;
$_SERVER['DB_DATABASE'] = $db;

// El segundo argumento es la RUTA AL ARCHIVO de config: Phinx resuelve
// %%PHINX_CONFIG_DIR%% como dirname(esa ruta). Pasar el directorio del
// proyecto apuntaba a la carpeta padre y no encontraba migraciones.
$config = new Config(require __DIR__ . '/../phinx.php', dirname(__DIR__) . '/phinx.php');
$manager = new Manager($config, new ArrayInput([]), new NullOutput());
$manager->migrate($config->getDefaultEnvironment());

Tests\Support\AppFactory::make();

register_shutdown_function(static function () use ($db): void {
    foreach ([$db, $db . '-wal', $db . '-shm'] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
});
