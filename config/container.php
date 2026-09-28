<?php

declare(strict_types=1);

use App\Http\Middleware\RememberMeMiddleware;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Csrf\Guard;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\Twig;

return [
    ResponseFactoryInterface::class => \DI\autowire(ResponseFactory::class),

    LoggerInterface::class => function () {
        $logger = new Logger('app');
        $logger->pushHandler(new StreamHandler(
            __DIR__ . '/../storage/logs/app.log',
            filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? Level::Debug
                : Level::Warning
        ));
        return $logger;
    },

    Capsule::class => function () {
        // Default relativo al proyecto; .env solo lo overridea. Sin esto un
        // clon fresco o un path absoluto de otro teléfono revienta en el
        // PRAGMA antes del error middleware (ver 20.3): QueryException cruda.
        $db = $_ENV['DB_DATABASE'] ?? __DIR__ . '/../database/database.sqlite';

        if ($db !== ':memory:') {
            $dir = dirname($db);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if (!file_exists($db) && !touch($db)) {
                throw new \RuntimeException(
                    "No se pudo crear la base SQLite en '{$db}'. Corré `pwd` en la raíz y corregí DB_DATABASE en .env (guía cap. 5/20.3)."
                );
            }
            if (file_exists($db) && !is_writable($db)) {
                throw new \RuntimeException(
                    "La base SQLite en '{$db}' no es escribible. Revisá permisos o corregí DB_DATABASE en .env (guía cap. 5/20.3)."
                );
            }
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => $db,
            'prefix'   => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Mejora la concurrencia lectura/escritura de SQLite
        $capsule->getConnection()->statement('PRAGMA journal_mode=WAL;');

        return $capsule;
    },

    Twig::class => function () {
        return Twig::create(__DIR__ . '/../resources/views', [
            // Con 'cache' => false, Twig re-parsea cada .twig en cada request.
            // En un ARM de 32 bits eso se nota. El caché va a disco, así que
            // remember crear el directorio y agregarlo al .gitignore.
            'cache'       => __DIR__ . '/../storage/cache/twig',
            'auto_reload' => true, // en dev recompila al cambiar una plantilla
        ]);
    },

    Guard::class => function (ContainerInterface $c) {
        $responseFactory = $c->get(ResponseFactoryInterface::class);

        // Tres trampas acá. Las tres producen el mismo síntoma — un 500
        // desde adentro del middleware, es decir el error que estamos
        // tratando de evitar.
        //
        // 1. `Slim\Csrf\Exception\RequestTokenException` NO EXISTE. El paquete
        //    solo tiene Guard.php y nunca lanza: llama a
        //    handleFailure($request, $handler).
        // 2. Ese handler recibe DOS argumentos: el ServerRequestInterface y un
        //    RequestHandlerInterface. No una Response, y no una excepción.
        //    Firma de más = ArgumentCountError.
        // 3. El constructor es (ResponseFactoryInterface, string $prefix,
        //    &$storage, ?callable $failureHandler, ...). Si el closure va en la
        //    posición 3, cae en $storage, queda descartado en silencio y
        //    $failureHandler queda null. Por eso el prefix va explícito y el
        //    handler va NOMBRADO.
        return new Guard(
            $responseFactory,
            'csrf',
            // Por defecto este parámetro es false: el token se valida una vez
            // y se BORRA. Eso está bien para un form que se repinta entero,
            // pero acá no es lo que pasa: el submit exitoso devuelve
            // `_panel.twig`, que no incluye los hidden del CSRF, y el form
            // queda con el token ya consumido. Resultado: el primer alta anda
            // (200) y el segundo tira 303 con la página vieja — la tarea se
            // pierde sin ningún error visible.
            //
            // Con true el token vive toda la sesión. El trade-off es real: la
            // protección CSRF sigue siendo la misma (el atacante no puede
            // leerlo), pero perdés la protección contra replay de un token
            // único. Si preferís el token de un solo uso, la alternativa es
            // devolver los hidden frescos en cada partial.
            persistentTokenMode: true,
            failureHandler: function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ) use ($responseFactory): ResponseInterface {
                // Sin handler, slim/php-csrf responde 400 "Failed CSRF check!"
                // en text/plain. Este handler lo convierte en algo que la app
                // sabe manejar: redirect para formularios normales, y 400 +
                // HX-Trigger para HTMX (un redirect ahí rompería el swap).
                $response = $responseFactory->createResponse();

                if ($request->getHeaderLine('HX-Request') !== '') {
                    return $response
                        ->withStatus(400)
                        ->withHeader('HX-Trigger', json_encode([
                            'csrf' => 'Tu sesión expiró. Recargá la página.',
                        ]));
                }

                return $response->withStatus(303)->withHeader('Location', '/');
            },
        );
    },

    SecurityHeadersMiddleware::class => \DI\autowire(SecurityHeadersMiddleware::class),

    RememberMeMiddleware::class => \DI\autowire(RememberMeMiddleware::class),
    RequireAuthMiddleware::class => \DI\autowire(RequireAuthMiddleware::class),
];

