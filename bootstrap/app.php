<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Framework\Auth\UserProvider;
use Framework\Http\Url;
use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

/** @var array{model: class-string<\Illuminate\Database\Eloquent\Model>} $auth */
$auth = require __DIR__ . '/../config/auth.php';
UserProvider::configure($auth['model']);

// Un clon fresco no trae storage/ ni .env: sin estos dirs Monolog y Twig
// fallan en silencio o con paths inexistentes. Se crean aquí, antes del
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

// S13: el prefijo de ruta es parte de la RUTA registrada, no un detalle de las
// plantillas. Con APP_BASE_PATH=/app, una request a /app/login matchea la ruta
// /login (y /login, sin el prefijo, deja de existir). Sin prefijo es '' y el
// router se comporta exactamente como antes.
$app->setBasePath(Url::base());

(require __DIR__ . '/../config/middleware.php')($app);

// Las rutas se registran por convención: web.php primero (es el arranque del
// framework) y después el resto por orden alfabético. Agregar una entidad es
// dejar su archivo en routes/, sin editar este archivo: este archivo está
// copiado en docs/, y editarlo obligaba a correr docs:sync cada vez.
$routeFiles = glob(__DIR__ . '/../routes/*.php') ?: [];
sort($routeFiles);

$web = __DIR__ . '/../routes/web.php';
if (is_file($web)) {
    (require $web)($app);
    $routeFiles = array_values(array_filter($routeFiles, static fn (string $f): bool => $f !== $web));
}

foreach ($routeFiles as $routeFile) {
    (require $routeFile)($app);
}

return $app;
