<?php

declare(strict_types=1);

use Framework\Http\Flash;
use Framework\Http\Middleware\RememberMeMiddleware;
use Framework\Http\Middleware\RequireAuthMiddleware;
use Framework\Http\Middleware\SecurityHeadersMiddleware;
use Framework\Http\Url;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Handler\RotatingFileHandler;
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
use Twig\TwigFunction;

return [
    ResponseFactoryInterface::class => \DI\autowire(ResponseFactory::class),

    LoggerInterface::class => function () {
        $logger = new Logger('app');
        // RotatingFileHandler escribe app-YYYY-MM-DD.log y conserva
        // LOG_MAX_FILES (default 14). Un StreamHandler único crecía sin límite.
        $logger->pushHandler(new RotatingFileHandler(
            __DIR__ . '/../storage/logs/app.log',
            (int) ($_ENV['LOG_MAX_FILES'] ?? 14),
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
            // SQLite deja las FK apagadas por conexión. Sin este flag el
            // ON DELETE CASCADE de remember_tokens nunca corre y el esquema
            // miente: las migraciones declaran la FK pero la base la ignora.
            'foreign_key_constraints' => true,
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Mejora la concurrencia lectura/escritura de SQLite
        $capsule->getConnection()->statement('PRAGMA journal_mode=WAL;');

        return $capsule;
    },

    Twig::class => function () {
        $twig = Twig::create(__DIR__ . '/../resources/views', [
            // Con 'cache' => false, Twig re-parsea cada .twig en cada request.
            // En un ARM de 32 bits eso se nota. El caché va a disco, así que
            // remember crear el directorio y agregarlo al .gitignore.
            'cache'       => __DIR__ . '/../storage/cache/twig',
            'auto_reload' => true, // en dev recompila al cambiar una plantilla
        ]);

        // `url('/tareas')` global para las plantillas (S13): es la ÚNICA vía
        // por la que una URL llega al HTML, así que no puede quedar una
        // raíz-absoluta olvidada en un href. Con APP_BASE_PATH vacío devuelve
        // la misma cadena que estaba escrita a mano y nada cambia.
        $twig->getEnvironment()->addFunction(new TwigFunction('url', [Url::class, 'path']));

        // Los scaffolds dejan su archivo de rutas al instalarse. Esa es la
        // única fuente: si no hay rutas, las vistas base no muestran enlaces.
        $twig->getEnvironment()->addGlobal('tasks_enabled', is_file(__DIR__ . '/../routes/tasks.php'));
        $twig->getEnvironment()->addGlobal('contacts_enabled', is_file(__DIR__ . '/../routes/contacts.php'));

        // El menú se arma por convención: Inicio siempre está, y cada módulo
        // aporta su entrada en config/navigation/<modulo>.php. El archivo lo
        // instala y lo quita el demo, así que su presencia es el interruptor y
        // el sidebar no tiene que saber de ningún demo en particular.
        $navigation = [
            ['label' => 'Inicio', 'href' => '/', 'icon' => 'home', 'key' => 'home', 'position' => 0],
        ];

        foreach (glob(__DIR__ . '/navigation/*.php') ?: [] as $navigationFile) {
            $item = require $navigationFile;

            if (!is_array($item) || !isset($item['label'], $item['href'], $item['key'])) {
                continue;
            }

            $navigation[] = $item + ['icon' => 'list', 'position' => 100];
        }

        usort(
            $navigation,
            static fn (array $a, array $b): int => [$a['position'], $a['label']] <=> [$b['position'], $b['label']]
        );

        $twig->getEnvironment()->addGlobal('navigation_items', $navigation);

        // Datos de ejecución para el pie del shell. No son configuración de
        // producto: describen dónde está corriendo la app ahora mismo.
        $twig->getEnvironment()->addGlobal('php_version', PHP_VERSION);
        $twig->getEnvironment()->addGlobal('app_env', $_ENV['APP_ENV'] ?? 'local');

        return $twig;
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

                // Sin htmx no hay toast que escuchar, así que el aviso tiene
                // que viajar en el flash y aparecer donde el usuario cae: el
                // login, que es la vista que renderiza `flash_error`. Antes el
                // redirect era a '/' y sin flash: el usuario perdía lo tipeado
                // sin enterarse de por qué.
                Flash::set('error', 'Tu sesión expiró. Volvé a iniciar sesión.');

                // Prefijado como todo redirect: un 303 a '/login' desde /app
                // deja al usuario en la raíz del dominio, que no es esta app.
                return $response->withStatus(303)->withHeader('Location', Url::path('/login'));
            },
        );
    },

    SecurityHeadersMiddleware::class => \DI\autowire(SecurityHeadersMiddleware::class),

    RememberMeMiddleware::class => \DI\autowire(RememberMeMiddleware::class),
    RequireAuthMiddleware::class => \DI\autowire(RequireAuthMiddleware::class),
];

