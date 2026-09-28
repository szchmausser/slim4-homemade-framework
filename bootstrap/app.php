<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

// Un clon fresco no trae storage/ ni .env: sin estos dirs Monolog y Twig
// fallan en silencio o con paths inexistentes. Se crean acá, antes del
// container, para que el primer boot ya sea usable.
foreach (['storage/logs', 'storage/cache/twig', 'storage/sessions', 'database'] as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(__DIR__ . '/../config/container.php');
$container = $containerBuilder->build();

// Forzamos la construcción del Capsule ahora: php-di es "lazy" y si nadie
// lo pide explícitamente por DI, Eloquent nunca queda conectado.
$container->get(Capsule::class);

AppFactory::setContainer($container);
$app = AppFactory::create();

(require __DIR__ . '/../config/middleware.php')($app);
(require __DIR__ . '/../routes/web.php')($app);

return $app;
