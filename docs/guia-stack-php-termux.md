# Guía paso a paso — Framework base en Termux (Slim 4 + Eloquent + Twig + HTMX/Alpine/Tailwind)

Stack confirmado: Slim 4, php-di, phpdotenv, Eloquent (Capsule standalone), Phinx, **respect/validation**, Twig, slim/csrf, **monolog/monolog**, whoops (solo dev), SQLite3, HTMX + Alpine.js + Tailwind CDN, estructura de carpetas al estilo Laravel.

> Nota sobre `respect/validation`: buena decisión — su repo tiene actividad reciente, a diferencia de `illuminate/validation` standalone (pesado) o alternativas abandonadas.

## Índice
1. Preparar Termux
2. Instalar Composer
3. Estructura de carpetas (Laravel-way)
4. Dependencias del backend
5. Variables de entorno
6. Contenedor DI
7. Bootstrap de la app y pipeline de middlewares
8. Sesiones nativas (middleware propio)
9. Rutas y controladores
10. Modelos Eloquent
11. Migraciones con Phinx
12. Validación estilo Laravel sobre respect/validation
13. CSRF en formularios Twig
14. Frontend: Tailwind v4 + Alpine + HTMX (CDNs actuales)
15. Ejemplo funcional de punta a punta (lista de tareas con HTMX)
16. Levantar el servidor y mantenerlo vivo
17. El capítulo que de verdad importa: 32 bits
18. Antes de exponer la app fuera de tu teléfono
19. Testing: testear el esqueleto, no la demo
20. Troubleshooting: síntomas, no theory
21. Cómo verificar esta guía vos mismo

---

## 1. Preparar Termux

Verificá primero tu arquitectura y actualizá los repos:

```bash
uname -m
# armv7l -> 32-bit ARM | aarch64 -> 64-bit ARM

pkg update -y && pkg upgrade -y
termux-setup-storage
```

Si `pkg update` trae paquetes muy viejos para tu arquitectura, cambiá de mirror antes de seguir:

```bash
termux-change-repo
```

Instalá lo básico:

```bash
pkg install php git curl wget unzip nano -y
```

Verificá PHP y que traiga soporte de SQLite (en Termux normalmente viene incluido en el mismo paquete `php`, no como paquete aparte):

```bash
php -v
php -m | grep -i sqlite
# deberías ver: pdo_sqlite y sqlite3
```

Si no aparecen, buscá el módulo suelto con `pkg search php` — pero en la gran mayoría de instalaciones de Termux ya viene compilado adentro.

## 2. Instalar Composer

```bash
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir="$PREFIX/bin" --filename=composer
rm composer-setup.php
composer --version
```

Para evitar que el solver de dependencias se quede sin memoria (el problema que ya detectaste con Eloquent), dejalo seteado de forma permanente en tu shell:

```bash
echo 'export COMPOSER_MEMORY_LIMIT=-1' >> ~/.bashrc
source ~/.bashrc
```

## 3. Estructura de carpetas (Laravel-way)

```bash
mkdir -p ~/proyectos && cd ~/proyectos
mkdir mi-app && cd mi-app

mkdir -p app/Http/Controllers app/Http/Middleware app/Models app/Support
mkdir -p bootstrap config database/migrations
mkdir -p public resources/views/layouts resources/views/tasks resources/views/partials resources/views/errors
mkdir -p routes storage/logs storage/cache/twig
mkdir -p tests/Http tests/Support
touch database/database.sqlite
```

Árbol final:

```
mi-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   └── Support/
├── bootstrap/
│   └── app.php
├── config/
│   ├── container.php
│   └── middleware.php
├── database/
│   ├── migrations/
│   └── database.sqlite
├── public/
│   └── index.php
├── resources/
│   └── views/
│       ├── layouts/app.twig
│       ├── errors/error.twig
│       ├── home.twig
│       ├── partials/_csrf.twig
│       └── tasks/{index.twig, _panel.twig, _list.twig}
├── routes/
│   └── web.php
├── storage/
│   ├── cache/twig/
│   └── logs/
├── tests/
│   ├── bootstrap.php
│   ├── Http/{CsrfFlowTest, DeleteTaskTest, EditTaskTest, ErrorHandlingTest, PartialRenderTest, RoutingTest}.php
│   └── Support/{AppFactory, ValidatorTest, FlashTest}.php
├── .env
├── .gitignore
├── composer.json
├── phinx.php
└── phpunit.xml
```

## 4. Dependencias del backend

```bash
composer init --name="tuusuario/mi-app" --type=project --require="php:>=8.2" --no-interaction

composer require slim/slim slim/psr7
composer require php-di/php-di
composer require vlucas/phpdotenv
composer require illuminate/database
composer require twig/twig slim/twig-view
composer require slim/csrf
# Sin ^ el instalador te trae respect/validation 3.x, donde Validator pasó a
# ser una interface: V::create(), assert() y las excepciones anidadas que usa
# este código ya no existen. Capítulo 12 roto en la primera request.
composer require "respect/validation:^2.0"
composer require monolog/monolog

composer require --dev robmorgan/phinx
composer require --dev filp/whoops
```

> Si tu `php -v` muestra algo menor a 8.2, fijá una major vieja de Eloquent compatible: `composer require illuminate/database:^10.0`.
>
> **Trade-off de pinear `respect/validation:^2.0`:** hoy resuelve a **2.5.0**, que
> corre limpio sobre PHP 8.5 (cero deprecations). Las versiones 2.3/2.4 sí tiraban
> `ReflectionProperty::setAccessible()` deprecado unas 13-16 veces por test que
> valida: si `composer.lock` te deja atado a una de esas, te llena el log. Subí
> `error_reporting` en `tests/bootstrap.php` o fijá `^2.5`.
>
> Lo que el pin **sí** te cuesta de verdad: migrar a 3.x no es un bump. En 3.x
> `Respect\Validation\Validator` pasó a ser una interface, `V::create()` y las
> excepciones anidadas desaparecen, y el capítulo 12 hay que reescribirlo. Mientras
> no lo hagas, `^2.0` es lo correcto.

Agregá el autoload PSR-4 en `composer.json` (dentro de la clave raíz, junto a `require`):

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Tests\\": "tests/"
    }
},
"scripts": {
    "serve": "php -S 0.0.0.0:8080 -t public",
    "migrate": "phinx migrate",
    "test": "phpunit"
}
```

```bash
composer dump-autoload -o
```

## 5. Variables de entorno

`.env`:

```bash
APP_ENV=local
APP_DEBUG=true

DB_DATABASE=/data/data/com.termux/files/home/proyectos/mi-app/database/database.sqlite

SESSION_NAME=mi_app_session
```

> **Si el proyecto se muda, editá `DB_DATABASE`.** Es la causa N.º 1 de que la app
> "no arranque y ni siquiera muestre el error bonito": la conexión a SQLite se hace
> dentro de `config/container.php`, que corre en `bootstrap/app.php` — **antes** de
> que exista el error middleware. Un path inexistente revienta con un
> `Illuminate\Database\QueryException` (envolviendo un `SQLiteDatabaseDoesNotExistException`)
> tirado desde la línea del `PRAGMA` en `config/container.php` — el manejador de
> errores del capítulo 7 todavía no está en el pipeline, así que ves el stack trace
> crudo.

Generá una clave random si más adelante la necesitás para firmar cookies:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

`.gitignore`:

```
/vendor/
/database/database.sqlite
/storage/logs/*.log
/storage/cache/
/.phpunit.cache/
/tests/.phpunit.result.cache
.env
```

`composer.lock` **sí** se commitea (no está en el `.gitignore` a propósito). Es lo que hace que un `composer install` en el teléfono sea determinista y no tenga que resolver dependencias — que en 32-bit es la diferencia entre 10 segundos y un OOM.

## 6. Contenedor DI

`config/container.php`:

```php
<?php

declare(strict_types=1);

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
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => $_ENV['DB_DATABASE'],
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
            // (200) y el segundo tira 400 + HX-Trigger con la página vieja — la
            // tarea se
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
];
```

## 7. Bootstrap de la app y pipeline de middlewares

`bootstrap/app.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\Factory\AppFactory;

Dotenv::createImmutable(__DIR__ . '/..')->load();

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
```

`config/middleware.php` — respeta el pipeline LIFO que ya habías definido, con un agregado necesario: **Body Parsing Middleware**, que Slim tampoco trae activado por defecto y que necesitás para leer `$_POST`/JSON antes de que el Guard de CSRF pueda validar el token del formulario:

```php
<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\SessionMiddleware;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Exception\HttpException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

return function (App $app) {
    $container = $app->getContainer();
    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // 6to (el más cercano al controlador): valida el token CSRF
    $app->add(Guard::class);

    // 5to: parsea form-urlencoded/JSON antes de que Guard lo necesite
    $app->addBodyParsingMiddleware();

    // 4to: inyecta helpers de ruta a Twig
    $app->add(TwigMiddleware::create($app, $container->get(Twig::class)));

    // 3ro: resuelve la ruta solicitada
    $app->addRoutingMiddleware();

    // 2do: arranca la sesión nativa para toda la app
    $app->add(SessionMiddleware::class);

    // 1ro: captura errores de todas las capas de abajo
    $errorMiddleware = $app->addErrorMiddleware($debug, true, true);

    // 0ro (el más externo): los headers tienen que ENVOLVER al error middleware,
    // porque es él quien genera las respuestas 404 y 500 — y esas también
    // necesitan los headers. Como add() es LIFO, lo que se agrega ÚLTIMO es lo
    // más externo: por eso esta línea va acá abajo y no arriba del todo.
    $app->add(SecurityHeadersMiddleware::class);

    // Whoops solo en desarrollo. En producción el mensaje va al log y la
    // respuesta es genérica: sin registro, un error es indistinguible de que
    // la app simplemente no funcionara.
    $whoops = null;
    if ($debug && class_exists(\Whoops\Run::class)) {
        $whoops = new \Whoops\Run();

        // Dos líneas que NO son opcionales, y que sin tests en web no vas a
        // ver nunca: `new Whoops\Run()` arranca con allowQuit=true y
        // sendHttpCode=500. Con los defaults, quien maneja la excepción es
        // Whoops y no el error handler: manda el código 500 para TODA la
        // excepción (incluido un 404) y sale del proceso con exit(1)
        // justo ahí — SecurityHeadersMiddleware queda sin ejecutar, así que
        // la página de error se sirve SIN X-Content-Type-Options. Los tests
        // pasan igual porque PrettyPageHandler no renderiza en SAPI cli.
        $whoops->allowQuit(false);
        $whoops->sendHttpCode(false);

        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
    }

    $errorMiddleware->setDefaultErrorHandler(
        function ($request, Throwable $exception) use ($app, $container, $whoops) {
            // El status sale de la excepción, no está hardcodeado. Un 404 tiene
            // que seguir siendo 404: si devolvés 500, los navegadores, los
            // proxies y los buscadores lo interpretan como "el servidor se cayó".
            $status = $exception instanceof HttpException ? $exception->getCode() : 500;
            if ($status < 400 || $status > 599) {
                $status = 500; // un PDOException puede traer un errno cualquiera
            }

            $logger = $container->get(LoggerInterface::class);
            $context = ['exception' => $exception, 'uri' => (string) $request->getUri()];

            // 5xx es un error tuyo: se registra como error. 4xx es el cliente
            // pidiendo algo que no existe — un 404 no merece una línea de error
            // en el log, o cualquier url que alguien pruebe te lo inunda.
            $status >= 500
                ? $logger->error($exception->getMessage(), $context)
                : $logger->info($exception->getMessage(), $context);

            // Whoops solo para 5xx. Un 404 o un 405 no son defectos: son la
            // respuesta correcta a una URL que no existe, y no tienen stack
            // trace que valga la pena. Medido: `curl -s http://localhost:8080/
            // no-existe` devuelve 154.490 bytes con Whoops y 507 bytes sin él.
            // Son ~150 KB por request de HTML de debugger, un pico de memoria
            // que en 32 bits (cap. 17) se paga caro, y además entrenás a
            // ignorar la página de error real, que queda en errors/error.twig.
            if ($whoops !== null && $status >= 500) {
                ob_start();
                $whoops->handleException($exception);
                $body = ob_get_clean();
            } else {
                $body = null;
                try {
                    $rendered = $container->get(Twig::class)->render(
                        $app->getResponseFactory()->createResponse($status),
                        'errors/error.twig',
                        [
                            'code'    => $status,
                            'message' => $status >= 500
                                ? 'Error interno del servidor.'
                                : $exception->getMessage(),
                        ]
                    );

                    // Render() escribe en el stream SIN rebobinar, así que con
                    // el cursor al final getContents() devuelve SIEMPRE ''.
                    // Con APP_DEBUG=false (producción) eso significa un 404 o
                    // un 500 con body vacío y sin ningún error que te avise.
                    $stream = $rendered->getBody();
                    $stream->rewind();
                    $body = $stream->getContents();
                } catch (Throwable $renderFailure) {
                    // Si la plantilla de error también falla, no querés otra
                    // excepción desde acá: entrás en loop. Degradamos a texto.
                    $logger->critical('No se pudo renderizar la vista de error', [
                        'exception' => $renderFailure,
                    ]);
                    $body = 'Error ' . $status . '.';
                }
            }

            $response = $app->getResponseFactory()->createResponse($status);
            $response->getBody()->write($body);
            return $response;
        }
    );
};
```

### 7.1 La vista de error (404, 500 y compañía)

El error handler del arriba renderiza `errors/error.twig`, que recibe `code` y `message`:

`resources/views/errors/error.twig`:

```twig
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ code }}</title>
</head>
<body class="bg-slate-50 text-slate-800">
    <div class="max-w-2xl mx-auto p-6 text-center py-20">
        <p class="text-6xl font-bold text-slate-300">{{ code }}</p>
        <p class="mt-4 text-slate-600">{{ message }}</p>
        <a href="/" class="inline-block mt-6 text-slate-800 underline">Volver al inicio</a>
    </div>
</body>
</html>
```

**Deliberadamente NO extiende `layouts/app.twig`.** Si el layout es justamente lo que se rompió (una variable indefinida, un filtro que no existe, un `{% include %}` de un archivo borrado), una página de error que extiende el mismo layout vuelve a fallar — y como el error handler se ejecuta dentro del error handler, entrás en un loop hasta que se te queda la memoria. Esta plantilla no depende de nada: ni del layout, ni de la sesión, ni de Alpine, ni de los CDNs. Por eso el handler la envuelve en `try/catch` y degrada a texto plano si algo se rompe igual.

Los 404 y 405 salen gratis de este mismo handler: Slim lanza `HttpNotFoundException` y `HttpMethodNotAllowedException`, el `ErrorMiddleware` las captura y nos llegan con su código correcto. No hace falta una ruta catch-all.

### 7.2 Headers de seguridad

`app/Http/Middleware/SecurityHeadersMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Deliberadamente NO incluye Content-Security-Policy. En este stack
 * (HTMX + Alpine + Tailwind browser CDN) una CSP estricta no es alcanzable
 * sin abandonar varias cosas; el capítulo 18 tiene el costo exacto y el
 * momento en que conviene. Lo que sí va acá es lo que suma sin romper nada.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);

        return $response
            // El navegador deja de adivinar el tipo de contenido. Sin esto, un
            // archivo served como octet-stream que en realidad es HTML se
            // ejecuta. Es el mejor valor por byte de todo el set.
            ->withHeader('X-Content-Type-Options', 'nosniff')

            // Nadie puede embeber la app en un iframe y hacerte click sin
            // querer (clickjacking). Alternativa moderna: `frame-ancestors 'none'`
            // dentro de una CSP, pero eso implicaría adoptar CSP entera.
            ->withHeader('X-Frame-Options', 'DENY')

            // La app carga recursos de jsdelivr y unpkg. Sin esto, cada request
            // a esos CDNs manda tu URL completa (con paths y query string)
            // en el header Referer.
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
```

Registralo en `config/container.php` (`SecurityHeadersMiddleware::class => \DI\autowire(...)`) y agregalo al pipeline **después** del error middleware, como en el capítulo 7.

El orden importa: `addErrorMiddleware` es el que *genera* las respuestas 404/500, así que los headers tienen que agregarse **por fuera** de él o las páginas de error salen limpias.

## 8. Sesiones nativas (middleware propio)

Slim no trae middleware de sesión — lo escribimos nosotros:

`app/Http/Middleware/SessionMiddleware.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class SessionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($_ENV['SESSION_NAME'] ?? 'mi_app_session');

            // Sin esto PHP aplica sus propios defaults: cookie accesible desde
            // JavaScript y sin SameSite. En un dispositivo propio la diferencia
            // parece chica; el día que la app escuche en la red, no lo es.
            session_set_cookie_params([
                'lifetime' => 0,      // 0 = cookie de sesión, expira al cerrar el navegador
                'path'     => '/',
                'httponly' => true,   // inaccesible desde document.cookie
                'samesite' => 'Lax',  // capa extra; el token CSRF sigue siendo la defensa real
                'secure'   => false,  // poné true SOLO si servís por HTTPS (ver capítulo 18)
            ]);

            // Rechaza IDs de sesión que nunca se inicializaron (session fixation).
            ini_set('session.use_strict_mode', '1');

            session_start();
        }

        return $handler->handle($request);
    }
}
```

De paso, un helper de flash al estilo `session()->flash()` de Laravel:

`app/Support/Flash.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

class Flash
{
    public static function set(string $key, string $message): void
    {
        $_SESSION['flash'][$key] = $message;
    }

    public static function get(string $key): ?string
    {
        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $message;
    }
}
```

## 9. Rutas y controladores

`routes/web.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\TaskController;
use Slim\App;
use Slim\Views\Twig;

return function (App $app) {
    // Ruta raiz: pagina de presentacion del stack, con status 200. Sin ella
    // GET / devuelve 404 y el boton "Volver al inicio" de errors/error.twig
    // vuelve a ser un link muerto. El formulario de altas vive solo en /tareas.
    $twig = $app->getContainer()->get(Twig::class);

    $app->get('/', function ($request, $response) use ($twig) {
        return $twig->render($response, 'home.twig');
    });

    $app->group('/tareas', function ($group) {
        $group->get('', [TaskController::class, 'index']);
        $group->post('', [TaskController::class, 'store']);
        $group->get('/{id}/edit', [TaskController::class, 'edit']);
        $group->put('/{id}', [TaskController::class, 'update']);
        $group->delete('/{id}', [TaskController::class, 'destroy']);
    });
};
```

**Por qué la raíz renderiza una página y no una redirección.** `errors/error.twig` (cap. 7.1)
cierra con `<a href="/">Volver al inicio</a>`. Sin una ruta en `/`, ese botón es un link muerto:
se llega a un 404, se pulsa "volver al inicio" y se recibe **el mismo 404**. Es la forma más
chiquita de un loop, y es invisible en los tests porque el test del 404 comprueba el status
de `/no-existe` y nada más: nadie sigue el link.

La ruta responde `200` con `home.twig`, la página de presentación del stack, y no redirige.
El formulario de altas vive en `/tareas`, así que la raíz queda libre para mostrar de qué está
hecha la app. De paso es el health check más barato que existe: `curl -sI http://localhost:8080/`
devuelve `200` si el bootstrap, el pipeline y el router están vivos, sin tocar base de datos ni
sesión. Es la primera request que conviene hacer cuando algo no levanta. Sin ella, "la app no
abre" y "la app está rota" son el mismo síntoma.

Es también el test de humo más económico que se puede dejar en el proyecto: comprobar que `/`
responde `200` y que su body muestra el stack cubre el router entero en dos aserciones.

`app/Http/Controllers/TaskController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\Flash;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

class TaskController
{
    public function __construct(private Twig $view, private Guard $guard) {}

    public function index(Request $request, Response $response): Response
    {
        // "Cancelar" apunta a /tareas con hx-target="#tareas-panel". Si
        // devolvemos index.twig (que extiende el layout) HTMX recibe un
        // <html> completo, se queda con el <body> y lo inserta dentro del
        // panel: dos <h1>, dos #alta-tarea y padding doble. Con HX-Request
        // devolvemos solo la región, igual que hace edit().
        $template = $request->hasHeader('HX-Request')
            ? 'tasks/_panel.twig'
            : 'tasks/index.twig';

        return $this->view->render($response, $template, [
            'tasks'       => Task::orderByDesc('id')->get(),
            'flash_error' => Flash::get('error'),
            ...$this->csrf($request),
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
        } else {
            Task::create(['title' => $data['title']]);
        }

        // Devolvemos el panel completo, no solo la lista: el flash vive en el
        // mismo swap, así se ve sin recargar.
        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'flash_error' => Flash::get('error'),
            ...$this->csrf($request),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        Task::destroy((int) $args['id']);

        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'flash_error' => null,
            ...$this->csrf($request),
        ]);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        // La edición es inline: el panel vuelve entero con una sola fila en
        // modo formulario. Sin `editing_id` la lista no sabe cuál editar.
        $task = Task::find((int) $args['id']);

        // Un id que no existe no responde 404: htmx metería la página de
        // error dentro de #tareas-panel. Devolvemos el panel con un flash,
        // que es donde la app ya comunica todo lo que el usuario tiene que
        // ver sin recargar.
        $flash = null;
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');
            $flash = Flash::get('error');
        }

        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'editing_id'  => $task?->id,
            'flash_error' => $flash,
            ...$this->csrf($request),
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en edit(): sin fila no hay nada que validar ni
        // que guardar, y un 404 llegaría al panel como HTML ajeno.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->view->render($response, 'tasks/_panel.twig', [
                'tasks'       => Task::orderByDesc('id')->get(),
                'editing_id'  => null,
                'flash_error' => Flash::get('error'),
                ...$this->csrf($request),
            ]);
        }

        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
            // La fila sigue en modo formulario: si volviera a la lista, el
            // usuario pierde el lugar que estaba editando junto con el error.
            $editingId = $task->id;
        } else {
            $task->update(['title' => $data['title']]);
            $editingId = null;
        }

        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'editing_id'  => $editingId,
            'flash_error' => Flash::get('error'),
            ...$this->csrf($request),
        ]);
    }

    private function csrf(Request $request): array
    {
        $nameKey  = $this->guard->getTokenNameKey();
        $valueKey = $this->guard->getTokenValueKey();

        return [
            'csrf_name_key'  => $nameKey,
            'csrf_name'      => $request->getAttribute($nameKey),
            'csrf_value_key' => $valueKey,
            'csrf_value'     => $request->getAttribute($valueKey),
        ];
    }
}
```

**`index()` tiene dos respuestas y la elige el header `HX-Request`.** El botón "Cancelar"
(del cap. 15) hace `hx-get="/tareas"` con `hx-target="#tareas-panel"` y `hx-swap="outerHTML"`:
pide la misma URL que pide el navegador con F5, pero espera la región, no la página. Si el
controlador devolviera `index.twig` — que extiende el layout —, htmx recibiría un
`<!DOCTYPE html>`, se quedaría con el `<body>` y lo insertaría dentro del panel: dos
`<h1>Mis tareas`, dos `id="alta-tarea"` (HTML inválido) y el padding del layout aplicado dos
veces. Con el header, `index()` devuelve `tasks/_panel.twig`, la misma región que ya devuelven
`store()`, `destroy()`, `edit()` y `update()`; sin el header, devuelve la página completa. Es
la misma convención que ya usa `edit()`.

**Un id que no existe responde 200 con un flash, no 404.** `edit()` y `update()` buscan la fila
con `Task::find()` antes de cualquier otra cosa. Si no existe, escriben
`La tarea no existe o ya fue eliminada.` en el flash y devuelven el panel. Un 404 sería peor:
`errors/error.twig` es una página completa y htmx la metería dentro de `#tareas-panel`, así que
el usuario vería la página de error tragada por la lista en vez de un mensaje. De paso esto
cierra un silencio anterior: `Task::find((int) $args['id'])?->update([...])` terminaba en el
operador null-safe, devolvía `null` sin actualizar nada y la respuesta salía 200 sin flash y
sin cambio — el usuario pulsaba "Guardar" y no pasaba nada.

**Cuando la validación falla, la fila se queda en modo formulario.** `update()` devuelve
`editing_id` con el id de la fila solo cuando el validator rechazó, y `null` cuando la
actualización se completó. Con `null` también en el error, la fila saldría del modo formulario
y el usuario perdería el lugar que estaba editando justo cuando aparece el mensaje. Aclaración
honestamente: lo que se conserva es el modo, no el texto tipeado — el input se repinta con
`task.title`, que es el valor guardado, porque `_list.twig` lee el modelo y no el request.

## 10. Modelos Eloquent

`app/Models/Task.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'done'];

    protected $casts = [
        'done' => 'boolean',
    ];
}
```

## 11. Migraciones con Phinx

`phinx.php` (en la raíz del proyecto):

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->load();

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
    ],
    'environments' => [
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name'    => __DIR__ . '/database/database',
            'suffix'  => '.sqlite',
        ],
    ],
];
```

Crear y correr una migración:

```bash
vendor/bin/phinx create CreateTasksTable
```

Completá el archivo generado en `database/migrations/`:

```php
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTasksTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tasks')
            ->addColumn('title', 'string', ['limit' => 120])
            ->addColumn('done', 'boolean', ['default' => false])
            ->addTimestamps()
            ->create();
    }
}
```

```bash
vendor/bin/phinx migrate
# o: composer migrate
```

## 12. Validación estilo Laravel sobre respect/validation

`app/Support/Validator.php` — te da la sintaxis `'campo' => 'required|max:120'` de Laravel, corriendo por debajo sobre `respect/validation`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as V;

class Validator
{
    private array $errors = [];

    public static function make(array $data, array $rules): self
    {
        $instance = new self();
        $instance->run($data, $rules);
        return $instance;
    }

    private function run(array $data, array $rules): void
    {
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;

            try {
                $this->buildChain($ruleString)->assert($value);
            } catch (NestedValidationException $e) {
                $this->errors[$field] = $e->getMessages();
            }
        }
    }

    private function buildChain(string $ruleString): V
    {
        $chain = V::create();

        foreach (explode('|', $ruleString) as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

            $chain = match ($name) {
                'required' => $chain->notEmpty(),
                'string'   => $chain->stringType(),
                'email'    => $chain->email(),
                'numeric'  => $chain->numeric(),
                'max'      => $chain->length(null, (int) $param),
                'min'      => $chain->length((int) $param, null),
                default    => $chain,
            };
        }

        return $chain;
    }

    public function fails(): bool
    {
        return count($this->errors) > 0;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            return array_values($messages)[0] ?? null;
        }
        return null;
    }
}
```

Sumá reglas al `match` a medida que las necesites (`alpha`, `date`, `in:`, etc.) — cada una es una línea nueva mapeando a un método de `respect/validation`.

> **Por qué `required` mapea a `notEmpty()` y no a `notOptional()`.** Los dos suenan
> igual y se comportan distinto en un solo caso, pero ese caso es justamente el que
> importa en un formulario.
>
> - `NotEmpty::validate()` **trimea primero** y después chequea `!empty()`. O sea
>   que rechaza `null`, rechaza `''` y también rechaza `"   "` (solo espacios).
> - `NotOptional::validate()` chequea `isUndefined()`, que es `in_array($v, [null, ''], true)`.
>   Rechaza `null` y `''`, pero **deja pasar `"   "`**.
>
> Lo que querés es el comportamiento de `required` de Laravel, y Laravel trimea
> antes de decidir: un campo con solo espacios **no pasa**. Por eso `notEmpty()`.
> Con `notOptional()` mandarías `"   "` a la base de datos como si fuera contenido.
>
> (Nota vieja que circula por ahí: "notOptional solo rechaza null, así que un campo
> en blanco pasaría". Eso era cierto antes; desde que `isUndefined()` incluye `''`
> ya no lo es — el campo vacío lo rechaza también. La razón para preferir
> `notEmpty()` es el trim, no el string vacío.)

## 13. CSRF en formularios Twig

`resources/views/partials/_csrf.twig` (parcial reusable):

```twig
<input type="hidden" name="{{ csrf_name_key }}" value="{{ csrf_name }}">
<input type="hidden" name="{{ csrf_value_key }}" value="{{ csrf_value }}">
```

Se incluye en cualquier formulario con `{% include 'partials/_csrf.twig' %}`, siempre que el controlador le haya pasado esas cuatro variables a la vista (como hace `TaskController::csrf()` arriba).

## 14. Frontend: Tailwind v4 + Alpine + HTMX (CDNs actuales)

Ojo con esto: el ejemplo que traías (`cdn.tailwindcss.com`) apunta a la Play CDN **de Tailwind v3**, que ya quedó como legado. La CDN de navegador vigente para Tailwind v4 se sirve desde jsDelivr:

`resources/views/layouts/app.twig`:

```twig
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}Mi App{% endblock %}</title>

    {# Versiones EXACTAS + SRI. Ver la nota de supply chain más abajo. #}
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3/dist/index.global.js"
            integrity="sha384-2ql948lIdLcGEE0/qxNiudyTjgauA3RDJERu5xW75kFCvSl5a9odyQYCb6tEjnmB"
            crossorigin="anonymous"></script>
    <script src="https://unpkg.com/htmx.org@2.0.11/dist/htmx.min.js"
            integrity="sha384-2OatzQy1H+Zd/IIrjr1TcuDGqLXeHhbooAyJY1KdQMKnr4LZ22k31GBLdYKHmVjg"
            crossorigin="anonymous"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.17.4/dist/cdn.min.js"
            integrity="sha384-5/joNqFnRyVWzXp99bHot6RHG+EksGp+USSgZwPar7T9SD9PKKER37n/8bXBAZGd"
            crossorigin="anonymous"></script>

    {# x-cloak es obligatorio acá: Alpine carga con `defer`, así que entre el
       parseo y el arranque hay unos ms donde el elemento abajo todavía tiene
       `x-show` sin resolver. Sin esta regla se vería un rectángulo rojo vacío
       en toda página. Si el CDN de Alpine no carga, el atributo queda puesto
       para siempre y el aviso simplemente no aparece - que es exactamente el
       comportamiento anterior, sin regresión visual. #}
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-50 text-slate-800">
    {# RECEPCIÓN del HX-Trigger:{csrf} que devuelve el failureHandler de
       slim/csrf (cap. 6). Este nodo es el receptor, y por eso vive en el
       layout y NO en tasks/_panel.twig: htmx dispara el evento en cuanto
       llega la respuesta 400, pero el panel es el elemento que ese mismo
       swap reemplaza - si el listener estuviera ahí, llegaría a un nodo que
       ya no está.

       Sin este bloque el header se emite al vacío: la sesión expira, el POST
       devuelve 400, HTMX no hace swap (no es 2xx) y en pantalla NO pasa
       nada. La tarea que intentabas crear o borrar desaparece sin ningún
       error visible. Es el caso exacto de "test verde, producción rota": el
        CsrfFlowTest verifica que el header salga, no que algo lo reciba. #}
    {# htmx envuelve el string del header HX-Trigger en { value: "..." } y triggerEvent agrega elt; por eso se lee .value. #}
    <div x-cloak
         x-data="{ aviso: '' }"
         @csrf.window="aviso = String($event.detail.value ?? ''); setTimeout(() => aviso = '', 6000)"
         x-show="aviso"
         class="fixed bottom-4 right-4 z-50 max-w-sm rounded-lg bg-red-600 text-white px-4 py-3 shadow-lg"
         role="alert"
         aria-live="assertive">
        <span x-text="aviso"></span>
    </div>

    <div class="max-w-2xl mx-auto p-6">
        {# El flash NO se renderiza acá: vive en tasks/_panel.twig, que es la
           región que HTMX reemplaza en los swaps parciales. Si lo pusiéramos en
           el layout, en un full page load aparecería duplicado y en un swap no
           aparecería nunca. #}
        {% block content %}{% endblock %}
    </div>
</body>
</html>
```

> **Por qué `.value`.** `handleTriggerHeader()` de htmx 2.0.11 no entrega el string del header tal cual: lo envuelve en `{ value: "..." }` y `triggerEvent()` agrega además `detail.elt`. El listener recibe un objeto, así que convertir el detail completo a string imprime `[object Object]` en el aviso. Leer `detail.value` (con `?? ''` por si el evento llega sin payload) es lo que hace que el mensaje se vea.

Como referencia, tanto la CDN de v3 como la de v4 siguen siendo "solo para desarrollo/prototipado" según la propia documentación de Tailwind — para tu caso (app personal corriendo en tu teléfono) ese trade-off es totalmente razonable, no hace falta resolverlo ahora. Si en el futuro querés purga real de clases sin depender de Node, existe el CLI standalone de Tailwind (binario nativo), aunque al estar compilado contra glibc probablemente necesites instalar la capa de compatibilidad de Termux (`pkg install glibc-repo glibc`) para poder correrlo — quedate con la CDN mientras tanto.

### Por qué versiones pineadas con SRI

Las URLs originales eran rangos flotantes: `@tailwindcss/browser@4`, `htmx.org@2`, `alpinejs@3`. Eso significa que **el día que el mantenedor de cualquiera de los tres publique una versión nueva, tu app ejecuta código distinto sin que vos toques nada ni te des cuenta.** Con htmx es peor: el tag `next` ya está en la 4.x, así que la frontera entre "actualización" y "cambio de major" es una decisión de ellos, no tuya.

`integrity` con un hash SHA-384 le dice al navegador: si un solo byte del archivo no coincide, **no lo ejecuta**. Con `crossorigin="anonymous"` (obligatorio para SRI cross-origin, porque si no el navegador lo trata como opaco y la verificación falla siempre) cerrás el ataque de supply chain: nadie te mete código por la puerta de atrás en un CDN comprometido.

Los tres hashes de arriba están **verificados**: los bytes que sirven jsDelivr y unpkg son idénticos a los archivos dentro del tarball publicado en npm. Cuando actualices una versión, regeneralos:

```bash
curl -sL "https://unpkg.com/htmx.org@NUEVA/dist/htmx.min.js" \
  | openssl dgst -sha384 -binary | openssl base64 -A
# -> prePendé "sha384-"
```

El costo de esto es que vos tenés que bumpear las versiones a mano cuando querés actualizar. Es un costo real y es intencional: es tu decisión, no la de un CDN.

## 15. Ejemplo funcional de punta a punta (lista de tareas con HTMX)

La raíz (`GET /`) renderiza `resources/views/home.twig`, la página de presentación del stack.
El formulario de altas no está en esa página: vive en `resources/views/tasks/index.twig`,
que es la pantalla a la que enlaza.

`resources/views/home.twig`:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Mi App{% endblock %}

{% block content %}
    <h1 class="text-2xl font-bold mb-4">Mi App</h1>

    <p class="mb-6 text-slate-600">
        Listado de tareas con altas, edición y borrado, servido por un esqueleto
        PHP en capas. El formulario de altas está en la pantalla de tareas.
    </p>

    <h2 class="text-lg font-semibold mb-3">Stack</h2>

    <ul class="space-y-2 mb-8">
        <li class="flex justify-between items-center border rounded p-3">
            <span>PHP</span>
            <span class="text-slate-500 text-sm">lenguaje</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>Slim 4</span>
            <span class="text-slate-500 text-sm">router y middleware</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>Eloquent ORM</span>
            <span class="text-slate-500 text-sm">modelos</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>Twig</span>
            <span class="text-slate-500 text-sm">plantillas</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>HTMX</span>
            <span class="text-slate-500 text-sm">intercambios parciales</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>Alpine.js</span>
            <span class="text-slate-500 text-sm">estado en el cliente</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>Tailwind CSS</span>
            <span class="text-slate-500 text-sm">estilos</span>
        </li>
        <li class="flex justify-between items-center border rounded p-3">
            <span>SQLite</span>
            <span class="text-slate-500 text-sm">base de datos</span>
        </li>
    </ul>

    <a href="/tareas" class="inline-block bg-slate-800 text-white px-4 py-2 rounded">
        Ir a tareas
    </a>
{% endblock %}
```

`resources/views/tasks/index.twig`:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Tareas{% endblock %}

{% block content %}
    <h1 class="text-2xl font-bold mb-4">Mis tareas</h1>

    <form id="alta-tarea" hx-post="/tareas" hx-target="#tareas-panel" hx-swap="outerHTML"
          class="flex gap-2 mb-6">
        {% include 'partials/_csrf.twig' %}
        <input type="text" name="title" placeholder="Nueva tarea" required
               class="flex-1 border rounded px-3 py-2">
        <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded">
            Agregar
        </button>
    </form>

    {% include 'tasks/_panel.twig' %}
{% endblock %}
```

`resources/views/tasks/_panel.twig` — **la unidad de intercambio de HTMX**:

```twig
<div id="tareas-panel">
    {% if flash_error %}
        <div class="bg-red-100 text-red-700 p-3 rounded mb-4">{{ flash_error }}</div>
    {% endif %}

    <div id="lista-tareas">
        {% include 'tasks/_list.twig' %}
    </div>
</div>
```

> **Por qué el panel y no la lista pelada.** El flash se escribe en la sesión durante el POST, pero `flash_error` se renderiza acá, no en el layout. Si el POST devolviera `_list.twig` y HTMX lo inyectara dentro de `#lista-tareas`, el mensaje se escribiría en la sesión y nunca se vería — y peor, `Flash::get('error')` en `index()` lo consumiría igual en el siguiente full page load, así que tampoco aparecería después. Devolviendo el panel completo con `hx-swap="outerHTML"`, el flash y la lista viajan en el mismo swap.
>
> **Un solo mecanismo de notificación, pero uno por ámbito.** Las validaciones de negocio (campo vacío, más de 120 caracteres) viajan por el servidor: `store()` escribe el flash en la sesión y el mensaje sale dentro del mismo swap, en el panel. No depende de JS. En cambio el fallo CSRF **no puede** usar ese camino, porque el POST se corta antes de llegar al controlador y HTMX no hace swap con un 400 - el mensaje viviría en la sesión y recién se vería en la siguiente carga completa, cuando el usuario ya ni se acuerda qué hizo mal.
>
> Por eso ese caso concreto usa el header `HX-Trigger` del que hablábamos: el `failureHandler` del capítulo 6 lo emite y `layouts/app.twig` lo escucha desde Alpine. Si borrás el listener quedás con el fallo silencioso original: 400, sin swap, sin mensaje, tarea perdida. Los dos mecanismos no conviven en el mismo ámbito, así que no hay duplicación: uno maneja lo que el servidor sí alcanzó a procesar, el otro lo que ni siquiera llegó.

`resources/views/tasks/_list.twig`:

```twig
<ul class="space-y-2">
    {% for task in tasks %}
        {% if editing_id is defined and editing_id == task.id %}
            <li class="border rounded p-3">
                <form hx-put="/tareas/{{ task.id }}"
                      hx-target="#tareas-panel"
                      hx-swap="outerHTML"
                      class="flex gap-2 items-center">
                    {% include 'partials/_csrf.twig' %}
                    <input type="text"
                           name="title"
                           value="{{ task.title }}"
                           required
                           maxlength="120"
                           class="flex-1 border rounded px-3 py-2">
                    <button type="submit" class="bg-slate-800 text-white px-4 py-2 rounded">
                        Guardar
                    </button>
                    <button type="button"
                            hx-get="/tareas"
                            hx-target="#tareas-panel"
                            hx-swap="outerHTML"
                            class="text-slate-600 text-sm">
                        Cancelar
                    </button>
                </form>
            </li>
        {% else %}
            <li class="flex justify-between items-center border rounded p-3">
                <span>{{ task.title }}</span>
                <div class="flex gap-3">
                    <button
                        hx-get="/tareas/{{ task.id }}/edit"
                        hx-target="#tareas-panel"
                        hx-swap="outerHTML"
                        class="text-slate-600 text-sm">
                        Editar
                    </button>
                    <button
                        hx-delete="/tareas/{{ task.id }}"
                        hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"
                        hx-target="#tareas-panel"
                        hx-swap="outerHTML"
                        hx-confirm="¿Borrar esta tarea?"
                        class="text-red-600 text-sm">
                        Eliminar
                    </button>
                </div>
            </li>
        {% endif %}
    {% else %}
        <li class="text-slate-400">No hay tareas todavía.</li>
    {% endfor %}
</ul>
```

> **`editing_id` gobierna la fila, no la lista.** `edit()` reenvía el panel completo con
> `editing_id` en la fila a editar, y `update()` lo devuelve en el id de la fila cuando la
> validación falla y en `null` cuando la actualización se completó, así que el mismo swap entra
> y sale del modo formulario sin tocar el resto. La condición es
> `editing_id is defined and editing_id == task.id`: `index.twig` incluye `_panel.twig` sin
> declarar esa variable, y en Twig una variable indefinida no es un error, es un `null` en
> silencio. La comparación con `task.id` es la que acota la fila: sin ella, cualquier
> `editing_id` verdadero pintaría todas las filas como formulario.
>
> **El form de edición lleva sus propios tokens.** El botón "Editar" está dentro de
> `#tareas-panel`, la región que el swap reemplaza, pero el form de edición aparece y
> desaparece con cada swap: no puede depender de los hidden de `#alta-tarea`, que queda fuera
> del panel. Por eso el form incluye `partials/_csrf.twig` con los tokens que `edit()` y
> `update()` pasan en cada render. Es el mismo motivo por el que `store()` y `destroy()`
> también renderizan `...$this->csrf($request)`: si no, el form de la fila se pinta con los
> campos CSRF vacíos y el primer guardado cae en el failure handler.
>
> **`hx-put` manda el body como `application/x-www-form-urlencoded`**, que es el default de
> htmx para verbos que no son GET, y `addBodyParsingMiddleware()` lo parsea igual que en un
> POST. No hace falta `_METHOD` oculto: la ruta ya es `PUT`.

> **`hx-headers` en el botón de eliminar: sin él, eliminar no borra nada.** `Guard` valida el
> token también en `DELETE`, y el botón vive dentro de `#tareas-panel`, que está *fuera* del
> `<form>` donde están los hidden del CSRF. Un `hx-delete` a secas manda un DELETE sin token,
> cae en el failure handler y devuelve **400** (el click siempre lleva `HX-Request`, así que va
> por el branch de HTMX, no por el redirect): la tarea sigue ahí y no ves ningún error.
>
> La solución sale de dónde mira `Guard` y de dónde manda htmx. En
> `vendor/slim/csrf/src/Guard.php` el proceso es: leer `getParsedBody()` y, si los dos campos
> están vacíos, leer los **headers** con el nombre de cada campo. El query string no figura en
> ningún punto. Y htmx 2.0.11 trae por defecto `methodsThatUseUrlParams: ['get', 'delete']`,
> así que los params que aportaría `hx-include` en un DELETE van al query string, no al body.
> Medido sobre esta app, con el mismo token válido:
>
> | Dónde van los tokens del DELETE | Resultado medido |
> |---|---|
> | query string (`?csrf_name=…&csrf_value=…`) | **400** + `HX-Trigger: {"csrf":"…"}` |
> | headers `csrf_name` / `csrf_value` | **200**, la tarea desaparece |
> | body urlencoded | **200**, la tarea desaparece |
>
> Por eso el botón lleva `hx-headers` con los dos tokens serializados a JSON y **no**
> `hx-include`: el header viaja por el único camino que `Guard` lee cuando el body está vacío,
> y de paso deja de acoplar el borrado al form de altas.
>
> Los hidden siguen viviendo en el form y el form queda fuera del swap, así que se conservan
> entre requests — eso es lo que hace que el token siga disponible después de cada alta y de
> cada borrado. Si algún día se mueve el form adentro del panel, el token desaparece con cada
> swap.
>
> **`index()` también responde distinto para htmx.** El botón "Cancelar" pide `GET /tareas`
> con `hx-target="#tareas-panel"`: si el servidor devolviera `index.twig`, que extiende el
> layout, htmx se quedaría con el `<body>` y lo metería dentro del panel. `index()` revisa
> `HX-Request` y devuelve `tasks/_panel.twig` en ese caso, la misma región que `edit()`. Es el
> branch que cubre `PartialRenderTest`.
>
> **Un id que no existe tampoco devuelve un 200 silencioso.** `edit()` y `update()` buscan la
> fila con `Task::find()` y, si no existe, escriben `La tarea no existe o ya fue eliminada.` en
> el flash y devuelven el panel. Nunca un 404: la página de error es un documento completo y
> htmx la insertaría dentro de `#tareas-panel`.

`public/index.php`:

```php
<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
```

## 16. Levantar el servidor y mantenerlo vivo

```bash
composer serve
# equivalente a: php -S 0.0.0.0:8080 -t public
```

Abrí `http://localhost:8080/tareas` desde el navegador del teléfono.

### 16.1 Que no se te muera cuando apagás la pantalla

Este es el detalle que casi nadie menciona y el que más te va a frustrar. Android suspende los procesos cuando la pantalla se apaga, y la app deja de responder sin error visible.

```bash
pkg install termux-api
termux-wake-lock
```

`termux-wake-lock` le pide a Android que mantenga el CPU despierto. Es un proceso aparte: corre una vez, queda en background, y se corta solo cuando cerrás la sesión de Termux o hacés `termux-wake-unlock`.

### 16.2 Que sobreviva cerrar la terminal

`php -S` corre en foreground: si cerrás la sesión de Termux, muere. Corrélo dentro de `tmux`:

```bash
pkg install tmux
tmux new -s app
composer serve          # ctrl+b, d para desprender la terminal
```

Volvés cuando quieras con `tmux attach -t app`. Es la diferencia entre tener la app disponible o tener que reencenderla cada vez.

### 16.3 A quién le estás exponiendo el puerto

`0.0.0.0` significa **todas las interfaces**, incluyendo el wifi. Si el teléfono está en un wifi abierto o con gente que no conocés, tu app está abierta a esa red, y sin HTTPS encima.

| Quiero | Uso |
|---|---|
| Solo desde el propio teléfono | `php -S 127.0.0.1:8080 -t public` |
| Desde la red local confiable | `php -S 0.0.0.0:8080 -t public` |
| Desde una red que no controlo | Un túnel SSH, no `0.0.0.0` |

Para el caso "red que no controlo", la opción honesta es un túnel SSH a una máquina tuya con TLS adelante, o `ngrok`/`cloudflared`. No es gratis, pero es la diferencia entre servir una app y publicarla.

### 16.4 El server embebido y las requests paralelas de HTMX

`php -S` es **single-threaded** por defecto: si tu página dispara dos `hx-get` al mismo tiempo, el segundo espera al primero. Para una app personal no suele importar, pero si lo vas a notar:

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public
```

Con workers, dos requests concurrentes sobre la **misma sesión** se van a contendingear por el lock de archivo de PHP, que es lo que `session.use_strict_mode` no resuelve. El flag para eso es `session.lazy_write=1`: la sesión se escribe al cerrarse en vez de en cada `set()`, y el lock se toma menos tiempo. Configuralo en el `php.ini` (capítulo 17).

### 16.5 Comandos frecuentes

```bash
composer migrate                         # corre migraciones pendientes
vendor/bin/phinx create NombreMigracion  # crea una nueva migración
composer dump-autoload -o                # regenera el autoload tras agregar clases
```

## 17. El capítulo que de verdad importa: 32 bits

En `armv7l` el límite no es la CPU, es la **memoria**. Y casi siempre se manifests en Composer, no en tu app. Por eso este capítulo va antes de "notas finales": no es una nota, es el entorno de ejecución.

### 17.1 Composer es el problema, no tu app

`COMPOSER_MEMORY_LIMIT=-1` (que aparece en muchos tutoriales, y también en el cap. 2) es **medio consejo**. Lo que hace es desactivar el límite interno de Composer, no el del sistema: en un proceso de 32 bits el techo sigue siendo el espacio de direcciones, y cuando lo pasás lo mata el OOM killer del kernel. Morís sin mensaje de error de PHP, que es la peor forma de morir.

Lo que sí funciona, en este orden:

1. **Resolver afuera y traer el resultado.** Es la solución real. Resolvé el `composer.lock` en una máquina de 64 bits y pasá `composer.lock` + `vendor/`. El teléfono nunca corre el solver.
2. **`composer install`, nunca `composer update`.** Con el lock commiteado, `install` no resuelve: descarga. Es la razón por la que el `.gitignore` del capítulo 3 excluye `vendor/` pero **no** `composer.lock`.
3. **Si necesitás resolver en el teléfono, poné un límite realista**, no `-1`. Un límite que Composer puede reportar es infinitamente más útil que uno que el kernel te aplica a escondidas:

```bash
export COMPOSER_MEMORY_LIMIT=1024M
composer update --prefer-dist --no-dev --no-scripts
```

`--no-dev` y `--no-scripts` bajan bastante el consumo en la resolución: menos paquetes que considerar y menos plugins que cargar.

### 17.2 OPcache: la mayor ganancia disponible

En un ARM de 32 bits, recompilar los mismos archivos PHP en cada request es el costo dominante. OPcache lo elimina. Verificá dónde está tu `php.ini`:

```bash
php --ini
```

Ahí vas a agregar:

```ini
; En un dispositivo con 2 GB, 64 MB de OPcache es holgado para una app chica
opcache.enable=1
opcache.memory_consumption=64
opcache.interned_strings_buffer=8
; Valida timestamps para no tener que reiniciar el server al editar código
opcache.validate_timestamps=1
opcache.revalidate_freq=0

; Libera la sesión al cerrarse en vez de en cada escritura: menos lockeo
; entre requests concurrentes de HTMX (ver 16.4)
session.lazy_write=1
```

Si tu `php -m` no lista `Zend OPcache`, el paquete de Termux no lo trae compilado y esto no aplica.

### 17.3 `memory_limit` de la app

El default suele ser 128M, que con Eloquent y una consulta de cientos de filas se queda corto. Subilo a 256M y observá:

```bash
php -r "echo ini_get('memory_limit');"
```

Si ves `Allowed memory size exhausted`, el culpable casi siempre es una consulta sin paginación: `Task::all()` o `get()` sobre una tabla que crece. En un teléfono eso se convierte en el OOM killer, y ahí sí morís sin stack trace.

### 17.4 Dónde viven las sesiones

```bash
php -i | grep -E 'session.save_path|session.gc_maxlifetime'
```

El backend de archivos de PHP guarda las sesiones en un directorio del sistema. Si `save_path` apunta a algo que Android puede limpiar (típicamente un directorio temporal dentro del sandbox), **perdés todas las sesiones sin ningún error** — los usuarios simplemente aparecen deslogueados. Si ese es tu caso, setealo a un directorio que vos controles:

```php
// en config/middleware.php, antes de session_start()
ini_set('session.save_path', __DIR__ . '/../storage/sessions');
```

Y agregá `/storage/sessions/` al `.gitignore`.

### 17.5 El techo de enteros

Con PHP compilado a 32 bits, `PHP_INT_MAX` ronda los **2.147 mil millones** en vez de los ~9.2 trillones de 64 bits. Para una app con SQLite y autoincrement no es un problema. Si alguna vez manejás IDs muy grandes o timestamps crudos como enteros, es un techo duro a tener en cuenta.

### 17.6 SQLite en WAL

`PRAGMA journal_mode=WAL` (ya seteado en `config/container.php`) te da lecturas concurrentes sin bloquear escrituras — suficiente para el patrón típico de HTMX con muchos GET parciales y algún POST ocasional. Ojo que WAL deja archivos `-wal` y `-shm` al lado de la base: si alguna vez respaldás `database.sqlite` copiando solo ese archivo, el backup va a estar incompleto.

## 18. Antes de exponer la app fuera de tu teléfono

Checklist corto. La mayoría de estos defaults son seguros **mientras la app esté en `127.0.0.1` con `APP_DEBUG=true`**; dejan de serlo en cuanto la exponés.

- [ ] `APP_DEBUG=false` en `.env`. Con `true`, Whoops muestra stack traces, rutas y variables de entorno a cualquiera que abra la URL.
- [ ] `session_set_cookie_params()` con `httponly` y `samesite` (cap. 8). Con `secure => true` solo si efectivamente servís por HTTPS — si no, el navegador no manda la cookie y la app falla en silencio.
- [ ] `127.0.0.1` en vez de `0.0.0.0`, salvo que sepas exactamente quién está en esa red.
- [ ] `.env` fuera de git. `composer.lock` adentro.
- [ ] HTTPS o un túnel. Sin TLS, el token CSRF y la cookie de sesión viajan en claro.
- [ ] `session.use_strict_mode=1` activo (cap. 8).
- [ ] `storage/logs/app.log` existe y lo mirás de vez en cuando. Con el error handler del capítulo 7, ahora los errores de producción sí quedan registrados; sin mirarlos, no sirve de nada.
- [ ] `APP_DEBUG=false` también apaga Whoops, así que la respuesta 500 es un mensaje genérico: eso es correcto, no un bug.
- [ ] `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy` presentes en las respuestas, incluidas las 404 y 500 (cap. 7.2).
- [ ] Versiones de CDN pineadas con `integrity` SRI (cap. 14). Sin esto, un publish del mantenedor te cambia el runtime sin deploy.

### Sobre Content-Security-Policy (y por qué no está)

No incluimos CSP en el esqueleto, y no es por ignorancia ni por pereza. Es porque en este
stack exacto una CSP estricta **no es alcanzable sin romper cosas que querés**. Esto es
exactamente lo que cuesta al intentarlo:

| Lo que exige CSP estricta | Lo que cuesta en este stack |
|---|---|
| `script-src` sin `'unsafe-eval'` | El build CDN de `alpinejs` usa declaraciones `Function`, que también violan la política. Hay que cambiar a `@alpinejs/csp`, que es un parser distinto: sin `Math`/`JSON`/`Date`, sin arrow functions, sin template literals, y `x-html` es error duro. |
| Lo anterior + `hx-trigger="keyup[x.length>3]"` | Con `htmx.config.allowEval = false` el filtro **no revienta: pasa a `() => true`** y emite un evento que nadie escucha. Escribís el filtro, pasa CI, falla en producción en silencio. |
| `style-src` sin `'unsafe-inline'` | `@tailwindcss/browser` inyecta `<style>` en runtime y **no soporta nonce** (ni setter, ni hash: el CSS cambia con cada clase descubierta). La única salida es compilar Tailwind a un CSS estático — el CLI que en Termux probablemente necesita `glibc`. |
| Todo lo anterior, y encima | `frame-ancestors` sí lo vas a tener, pero recién adoptando la CSP entera. |

Lo que sí hace la lista del capítulo 7.2 es bloquear lo que importa a coste cero:
`nosniff`, clickjacking y fuga de `Referer`. Y lo que de verdad cierra el agujero de
supply chain —tres CDNs, uno con versión flotante— son los `integrity` del capítulo 14.

**Cuándo reconsiderar CSP:** el día que la app sea alcanzable por alguien que no controlás
**y** renderice input de usuario. Ahí XSS deja de ser teórico. Ese día el orden correcto es
compilar Tailwind a CSS estático primero, y recién después adoptar CSP.

## 19. Testing: testear el esqueleto, no la demo

Este es el capítulo que más se saltea una guía de framework, y es el que más cara sale
omitir.

**El reframe que lo ordena todo: testeá el esqueleto, no el ejemplo.** `TaskController`
es descartable: lo vas a borrar el día que hagas tu app real. El pipeline de middlewares,
el `Validator`, el `Flash`, la configuración de sesión y el `Guard` de CSRF se quedan
para siempre. Los tests existen para proteger el *plumbing*, no para verificar que tu
lista de tareas ande.

### 19.1 Instalación

```bash
composer require --dev phpunit/phpunit
```

`phpunit.xml` en la raíz:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         failOnWarning="true"
         failOnRisky="true">
    <testsuites>
        <testsuite name="app">
            <directory>tests</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>

    <php>
        <!-- force="true" + Dotenv::createImmutable() en bootstrap/app.php:
             las variables de acá ganan, el .env real no las pisa. -->
        <env name="APP_ENV"    value="testing" force="true"/>
        <env name="APP_DEBUG"  value="true"    force="true"/>
        <env name="DB_DATABASE" value=":memory:" force="true"/>
    </php>
</phpunit>
```

`tests/bootstrap.php`:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Construye la app y el schema UNA sola vez para toda la suite.
Tests\Support\AppFactory::make();
```

`tests/Support/AppFactory.php` — el helper que evita el bug más tonto de esta configuración:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\App;

/**
 * La app se construye una vez y se comparte entre todos los tests.
 */
final class AppFactory
{
    private static ?App $app = null;

    public static function make(): App
    {
        if (self::$app === null) {
            self::$app = require __DIR__ . '/../../bootstrap/app.php';
            self::defineSchema();
        }

        return self::$app;
    }

    /**
     * El error N.º 1 de este setup: crear un `new Capsule()` propio con
     * `sqlite::memory:`. Cada conexión nueva a `:memory:` es una base vacía
     * INDEPENDIENTE — el schema quedaría en una base que la app nunca toca y
     * todo test fallaría con "no such table: tasks". El schema siempre va
     * sobre el MISMO Capsule que usa la app.
     */
    private static function defineSchema(): void
    {
        // Con SQL plano, no con Phinx: los tests tienen que correr en un
        // segundo, no levantar el migrador.
        // Trade-off real: si cambiás la migración y olvidás el CREATE TABLE
        // de acá, los tests siguen verdes contra un esquema viejo. Si eso te
        // molesta, corré Phinx contra un archivo SQLite temporal en vez de
        // usar :memory:.
        Capsule::schema()->create('tasks', function ($table) {
            $table->increments('id');
            $table->string('title', 120);
            $table->boolean('done')->default(false);
            $table->timestamps();
        });
    }
}
```

> **Un solo `require` de `bootstrap/app.php`, siempre.** Si lo pedís dos veces
> (una desde `bootstrap.php` y otra desde cada test) construís dos containers, dos
> Capsules y dos apps, y volvés al mismo problema. `AppFactory::make()` cachea.
>
> Como el schema vive en memoria y se crea una sola vez, los tests que **escriben**
> filas tienen que limpiar después de sí mismos: `Capsule::table('tasks')->truncate()`
> en `tearDown()`, o un `truncate()` al principio de cada test que inserta. Si no,
> dependés de la orden en que corre la suite, que es el tipo de roja aleatoria que
> aprendés a ignorar.

Agregá `/.phpunit.cache/` y `/tests/.phpunit.result.cache` al `.gitignore`.

Correr:

```bash
vendor/bin/phpunit
composer test     # agregá "test": "phpunit" al section scripts del composer.json
```

### 19.2 El `Validator`, con tabla

Este es el test que más retorno da por línea, y el que habría atrapado el bug de
`required` que arreglamos en el capítulo 12.

`tests/Support/ValidatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /** @return array<string, array{0: array, 1: array, 2: bool}> */
    public static function cases(): array
    {
        return [
            // nombre                       datos                      reglas                    falla
            'required, valor presente'      => [['title' => 'Comprar pan'], ['title' => 'required'], false],
            'required, string vacío'       => [['title' => ''],           ['title' => 'required'], true],
            'required, null'               => [['title' => null],        ['title' => 'required'], true],
            'required, campo ausente'      => [[],                       ['title' => 'required'], true],
            'required, solo espacios'      => [['title' => '   '],       ['title' => 'required'], true],
            'max, en el límite'            => [['title' => str_repeat('a', 120)], ['title' => 'max:120'], false],
            'max, un carácter de más'      => [['title' => str_repeat('a', 121)], ['title' => 'max:120'], true],
            'max, string vacío'            => [['title' => ''],           ['title' => 'max:120'], false],
            'required + max, ambos ok'     => [['title' => 'Pan'],        ['title' => 'required|max:120'], false],
            'required + max, falla max'    => [['title' => str_repeat('a', 200)], ['title' => 'required|max:120'], true],
            'campo sin reglas ni valor'    => [[],                       ['otro' => 'required'], true],
        ];
    }

    #[DataProvider('cases')]
    public function test_valida(array $data, array $rules, bool $shouldFail): void
    {
        $validator = Validator::make($data, $rules);
        self::assertSame($shouldFail, $validator->fails());
    }

    public function test_first_error_devuelve_el_mensaje_del_primer_campo(): void
    {
        $validator = Validator::make(['title' => ''], ['title' => 'required|max:120']);

        self::assertTrue($validator->fails());
        self::assertNotNull($validator->firstError());
    }
}
```

Fijate en la quinta fila: `required` con `'   '` **sí falla**. `NotEmpty::validate()`
trimea el string antes de chequear, así que solo espacios queda como `''` y no pasa.

Esa fila está a propósito: es la que separa `notEmpty()` de `notOptional()`, que con
`'   '` dejaría pasar y terminarías guardando un string de espacios en la base de
datos. Si la regla de negocio no te importa eso, la fila es `false` y listo — pero
antes de cambiarla, mirá qué guardaría realmente el campo. Los tests son la
documentación ejecutable de esa decisión.

### 19.3 El flash: consume-una-vez y cross-request

El contrato de `Flash::get()` es que **borra**. Si mañana alguien "optimiza" el `unset`,
esta suite se da vuelta.

`tests/Support/FlashTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Flash;
use PHPUnit\Framework\TestCase;

final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_get_consume_el_valor(): void
    {
        Flash::set('error', 'Datos inválidos.');

        self::assertSame('Datos inválidos.', Flash::get('error'));
        self::assertNull(Flash::get('error'), 'el segundo get tiene que devolver null');
    }

    public function test_get_de_una_clave_inexistente_devuelve_null(): void
    {
        self::assertNull(Flash::get('nunca-setada'));
    }

    public function test_claves_distintas_no_se_pisan(): void
    {
        Flash::set('error', 'a');
        Flash::set('info', 'b');

        self::assertSame('a', Flash::get('error'));
        self::assertSame('b', Flash::get('info'));
    }
}
```

### 19.4 El flujo CSRF de punta a punta

Este es el test que justifica el capítulo entero. Ejercita, en una sola request real: el
pipeline completo, que la sesión esté abierta **antes** que el `Guard`, el body parsing,
Twig, routing, el controlador, Eloquent y el flash.

Si alguien reordena `config/middleware.php` — el error más fácil de cometer y el más
difícil de detectar — este test falla.

`tests/Http/CsrfFlowTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

final class CsrfFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig.
     *
     * Ojo con una trampa: `csrf_name_key` es una variable de Twig, NO aparece
     * en el HTML renderizado. El template es
     *   <input name="{{ csrf_name_key }}" value="{{ csrf_name }}">
     * y `getTokenNameKey()` devuelve justo el string literal 'csrf_name'. Así
     * que el name real del input es `csrf_name`, y buscar "csrf_name_key" en
     * el HTML no matchea nunca.
     */
    private function scrapeToken(string $html): array
    {
        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    public function test_get_renderiza_el_formulario_con_token(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="csrf_name"', (string) $response->getBody());
    }

    public function test_post_con_token_valido_crea_la_tarea(): void
    {
        $app = self::app();

        // 1. El GET abre la sesión y deja el token en ella.
        $getResponse = $app->handle(self::request('GET', '/tareas'));
        $token = $this->scrapeToken((string) $getResponse->getBody());

        // 2. El POST reutiliza ese token.
        $postResponse = $app->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $postResponse->getStatusCode());
        self::assertStringContainsString('Comprar pan', (string) $postResponse->getBody());
    }

    public function test_post_con_token_invalido_redirige_al_inicio(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => 'basura',
                'csrf_value' => 'basura',
                'title'      => 'No deberia guardarse',
            ])
        );

        // Sin el failureHandler del capítulo 6 esto devolvería 400
        // "Failed CSRF check!" en text/plain. El 303 comprueba que nuestro
        // handler está conectado en la posición correcta del constructor.
        self::assertSame(303, $response->getStatusCode());
        self::assertStringNotContainsString('No deberia guardarse', (string) $response->getBody());
    }

    public function test_post_htmx_con_token_invalido_devuelve_400_y_hx_trigger(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', ['title' => 'x'], htmx: true)
        );

        self::assertSame(400, $response->getStatusCode());
        // El valor es {"csrf":"..."} — asserteamos contra el valor, no contra
        // el nombre del header (getHeader() devuelve los valores).
        $hxTrigger = $response->getHeaderLine('HX-Trigger');
        self::assertStringContainsString('csrf', $hxTrigger);

        // El header tiene que ser JSON con la clave `csrf`: es lo que lee
        // `@csrf.window` del layout. Un texto plano no tiene claves y el
        // listener se queda con undefined.
        $payload = json_decode($hxTrigger, true);
        self::assertIsArray($payload, 'HX-Trigger tiene que ser JSON.');
        self::assertArrayHasKey('csrf', $payload);
    }

    public function test_el_hx_trigger_del_csrf_tiene_alguien_que_lo_escuche(): void
    {
        // Existe por un bug real que ningún otro test veía: el failureHandler
        // emite HX-Trigger:{csrf:...} y no había NINGÚN listener en las
        // plantillas. El test de arriba assertea que el header salga — y sale,
        // perfecto. Pero llegaba al vacío: HTMX recibe el 400, no hace swap
        // (no es 2xx), no dispara nada visible y la tarea desaparece sin
        // error. Test verde, producción rota.
        //
        // Asserteamos el lado de la RECEPCIÓN, que es el que se rompe. Si
        // tocás layouts/app.twig y te llevás el @csrf.window, este test falla
        // antes que alguien pierda una tarea en silencio.
        $layout = file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/layouts/app.twig'
        );

        self::assertNotFalse($layout, 'No se pudo leer resources/views/layouts/app.twig');
        self::assertStringContainsString(
            '@csrf.',
            $layout,
            'El failureHandler emite HX-Trigger:{csrf} pero nada lo escucha: el fallo CSRF queda silencioso.'
        );
    }

    public function test_el_listener_de_csrf_lee_el_valor_del_evento(): void
    {
        // Regresión del bug 12: con `String($event.detail)` el toast mostraba
        // "[object Object]". El texto vive en `.value`, no en el objeto
        // entero, y gretear solo `@csrf.` pasaba con las dos variantes.
        $layout = file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/layouts/app.twig'
        );

        self::assertNotFalse($layout, 'No se pudo leer resources/views/layouts/app.twig');
        self::assertStringContainsString(
            '$event.detail.value',
            $layout,
            'El listener de CSRF tiene que leer $event.detail.value: con $event.detail el aviso dice [object Object].'
        );
        self::assertStringContainsString('@csrf.window=', $layout);
    }

    private static function request(string $method, string $path, array $body = [], bool $htmx = false): ServerRequestInterface
    {
        // URI absoluta: con path-only el host queda vacío y Slim se porta
        // distinto en los casos borde. En producción siempre hay host.
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            // Deliberadamente NO usamos withParsedBody(): mandamos el body crudo
            // con su Content-Type para que pase por el Body Parsing Middleware
            // igual que en producción. Si inyectás el array ya parseado, el test
            // deja de cubrir el body parsing — que es justo lo que rompen los
            // cambios de orden en el pipeline.
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        // Solo lo seteamos cuando corresponde: detectarlo con getHeaderLine()
        // hace que un header presente con valor vacío cuente como "no HTMX".
        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
```

### 19.4.1 Aislamiento: `$_SESSION` es global

Acá hay una trampa de la que hay que ser consciente. `FlashTest` hace `$_SESSION = []` en
`setUp()`, y PHPUnit corre todos los tests en **un solo proceso**: esa línea borra la
sesión que el `CsrfFlowTest` necesita. El orden de ejecución de las clases decide si tu
suite es verde o roja, y eso es exactamente el tipo de test que entrenás a ignorar.

Dos salidas limpias:

- **Aislar por proceso** (lo correcto): en `phpunit.xml`, `<phpunit ... processIsolation="true">`. Más lento, pero cada test tiene su propio `$_SESSION`. En una app chica el costo es despreciable y te saca de cabeza el problema.
- **Guardar y restaurar** en vez de pisar: en `setUp()`, `$this->previous = $_SESSION ?? null;` y en `tearDown()`, `$_SESSION = $this->previous;`.

No dejes el `$_SESSION = []` a secas sin el `tearDown()` que restaura. Es el tipo de
detalle que hace que un equipo odie los tests.

### 19.5 Errores: el 404 tiene que seguir siendo 404

`tests/Http/ErrorHandlingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

final class ErrorHandlingTest extends TestCase
{
    public function test_ruta_inexistente_devuelve_404_y_no_500(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_las_paginas_de_error_tambien_llevan_security_headers(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    /**
     * Un 404 no es un defecto: no hay stack trace que mirar. Si Whoops lo
     * renderiza igual, cada URL mal tipeada cuesta ~154 KB de HTML de debugger
     * (medido) en vez de los ~507 bytes de errors/error.twig. En 32 bits ese
     * pico de memoria se paga caro (cap. 17), y de paso dejás de mirar la
     * página de error real porque todo 404 se ve igual de apocalyptic.
     *
     * Este test corre con APP_DEBUG=true (lo que hay en .env), o sea que
     * Whoops está construido: verifica que la rama correcta sea la elegida.
     */
    public function test_un_404_no_gasta_la_pagina_de_whoops(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );

        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();

        self::assertStringNotContainsString('Whoops', $html);
        self::assertStringContainsString('Not found.', $html);
        self::assertLessThan(4096, strlen($html), 'El 404 deberia pesar bytes, no cientos de KB.');
    }
}
```

Ese segundo test existe por una razón concreta: el `SecurityHeadersMiddleware` tiene que
ir **por fuera** del `ErrorMiddleware`. Si alguien lo mueve adentro, este test falla.

El tercero sí es el que atrapa la regresión de Whoops. Ojo con **cómo** falla, porque
es contraintuitivo: en SAPI `cli`, `PrettyPageHandler` no renderiza, así que si vuelve
el `if ($whoops !== null)` sin la condición de 5xx el body no mide 154 KB — mide **cero**.
Por eso el test asserta que el HTML **contenga** `Not found.`, y no solo que no mentione
Whoops: el `assertStringNotContainsString('Whoops', ...)` pasa solo en el camino trucho.
El `assertLessThan` es el que te protege en el caso real, que es por HTTP.

### 19.6 Routing: la raíz y el link que nadie testeaba

`tests/Http/RoutingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * El router, y sobre todo la ruta raiz.
 *
 * El segundo test existe por un bug concreto: `errors/error.twig` cierra con
 * `<a href="/">Volver al inicio</a>`, y no había ninguna ruta en `/`. El botón
 * de la página de error era, él mismo, un link muerto. Ningún test lo detectó
 * porque todos asserteaban el status de `/no-existe` y nadie seguía el link.
 */
final class RoutingTest extends TestCase
{
    public function test_la_raiz_responde_y_no_un_404(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/')
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_la_raiz_presenta_el_stack_y_enlaza_a_tareas(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/')
        );

        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();

        self::assertStringContainsString('Slim 4', $html, 'La raiz deberia mostrar el stack.');
        self::assertStringContainsString('href="/tareas"', $html, 'La raiz deberia enlazar al listado.');
    }

    public function test_el_boton_de_la_pagina_de_error_no_es_un_link_muerto(): void
    {
        // Si este test falla, volvio el bug: la pagina de error enlaza a una
        // ruta que no existe y el usuario queda atrapado en un 404.
        $app = AppFactory::make();

        $error = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );
        $error->getBody()->rewind();
        $html = $error->getBody()->getContents();

        self::assertStringContainsString('href="/"', $html, 'La pagina de error deberia ofrecer volver al inicio.');

        $target = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/')
        );

        // assertSame(200), no assertNotSame(404): un 500 también pasa
        // "distinto de 404" y dejaba el link muerto cubierto por un test verde.
        self::assertSame(200, $target->getStatusCode(), 'El destino del link de error tiene que responder.');
    }
}
```

ese segundo test es la lección del capítulo, y vale más que todos los demás juntos: **un link
es un contrato**. La página de error promete que `/` existe, y durante toda la vida de esta
guía `/` no existió. El 19.5 te dice que el 404 tiene que seguir siendo 404 y lo verifica;
nadie se preguntó si la página que el 404 muestra es usable.

Y el patrón es reutilizable: cuando tu app muestre una URL en pantalla, un test de que esa
URL **responde** es más barato que cualquier test de que la pantalla se ve bien. Comprobá
el destino, no el origen.

### 19.7 Borrar y recargar el panel: lo que htmx espera recibir

Dos comportamientos que ningún otro test cubría. El primero es el bug que más duele: antes de
escribir este bloque, `grep -r "delete" tests/` no devolvía **ningún** DELETE — el botón más
usado de la pantalla no tenía un solo test, y la falla real (400 silencioso, la tarea queda
donde estaba) estaba justo ahí.

`tests/Http/DeleteTaskTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * La D del CRUD, que es la única que depende de dónde viajan los tokens.
 *
 * htmx 2.0.11 trae `methodsThatUseUrlParams: ['get', 'delete']`, así que los
 * params que aporta `hx-include` en un DELETE van al query string. `Guard`
 * solo mira `getParsedBody()` y, si está vacío, los headers: el query string
 * nunca. Por eso el botón de eliminar manda los tokens con `hx-headers` y no
 * con `hx-include`.
 */
final class DeleteTaskTest extends TestCase
{
    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_delete_con_los_tokens_en_headers_borra_la_tarea(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('DELETE', '/tareas/' . $task->id)
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id), 'La tarea siguió existiendo.');
        self::assertStringNotContainsString('Comprar pan', (string) $response->getBody());
    }

    public function test_delete_con_los_tokens_en_query_string_no_pasa_el_guard(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('DELETE', '/tareas/' . $task->id . '?' . http_build_query($token))
                ->withHeader('HX-Request', 'true')
        );

        // No es el comportamiento deseado: es la medición que explica por qué
        // el botón usa hx-headers. Guard no lee el query string, así que este
        // 400 es el bug del usuario reproducido tal cual.
        self::assertSame(400, $response->getStatusCode());
        self::assertNotNull(Task::find($task->id), 'Guard rechazó: no se borra nada.');
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig. El name real del
     * input es `csrf_name` / `csrf_value`: `csrf_name_key` es variable de Twig
     * y no aparece en el HTML renderizado.
     */
    private function token(): array
    {
        $html = (string) self::app()->handle($this->request('GET', '/tareas'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    private static function request(string $method, string $path): ServerRequestInterface
    {
        // URI absoluta, igual que en el resto de la suite: con path-only el
        // host queda vacío y Slim se porta distinto en los casos borde.
        return (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);
    }
}
```

El segundo test no assertea lo deseado: assertea el bug, y hace falta. Si algún día `Guard`
leyera el query string, o si el botón mandara los tokens por otro lado, este test pasaría con
200 y habría que releerlo. Los dos juntos dejan escrito el contrato: los tokens del DELETE
viajan en headers, y el query string no sirve.

`tests/Http/PartialRenderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * Qué recibe HTMX cuando pide `/tareas` con `HX-Request`.
 *
 * El botón "Cancelar" apunta a `/tareas` con `hx-target="#tareas-panel"` y
 * `hx-swap="outerHTML"`. Si el servidor devuelve `index.twig` (que extiende
 * el layout), HTMX recibe un documento completo, se queda con el `<body>` y lo
 * inserta dentro del panel: quedan dos `<h1>`, dos `id="alta-tarea"` y el
 * padding del layout dos veces.
 */
final class PartialRenderTest extends TestCase
{
    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_get_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $response = self::app()->handle(
            self::request(false)
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('id="alta-tarea"', $html);
    }

    public function test_get_con_hx_request_devuelve_solo_el_panel(): void
    {
        $response = self::app()->handle(
            self::request(true)
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un <html> completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
    }

    private static function request(bool $htmx): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost/tareas');

        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
```

El primer test es el control: sin `HX-Request`, `/tareas` sigue devolviendo la página completa
con el form de altas. El segundo protege el branch de `index()` — si alguien simplifica el
controlador y devuelve `index.twig` siempre, este test falla antes de que aparezca un `<h1>`
duplicado en pantalla.

### 19.8 Edición: la U del CRUD que faltaba

Antes de escribir la ruta, un grep sobre todo el proyecto devolvía **cero** coincidencias de
`edit`, `update`, `put` y `patch`. La U del CRUD nunca se había implementado, así que no
había nada que testear todavía. Este bloque acompaña la funcionalidad: cubre el render del
form, la persistencia del PUT, la validación que rechaza, el id que no existe y el token
ausente.

`tests/Http/EditTaskTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

/**
 * La U de CRUD: la edición es inline en la fila.
 *
 * El form de edición vive dentro de `_list.twig`, o sea dentro del panel que
 * el swap reemplaza. Ahí no alcanzan los hidden de `#alta-tarea`, que queda
 * fuera de ese panel, así que el form lleva sus propios tokens CSRF.
 *
 * El PUT llega como `application/x-www-form-urlencoded` (el default de htmx
 * para verbos no-GET) y lo parsea `addBodyParsingMiddleware()`.
 */
final class EditTaskTest extends TestCase
{
    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_get_edit_renderiza_el_formulario_de_la_fila(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('GET', '/tareas/' . $task->id . '/edit')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('hx-put="/tareas/' . $task->id . '"', $html);
        self::assertStringContainsString('value="Comprar pan"', $html);
        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('Cancelar', $html);
    }

    public function test_get_edit_de_un_id_inexistente_avisa_en_lugar_de_ignorar(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas/999999/edit')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('tareas-panel', $html);
        self::assertStringContainsString('La tarea no existe o ya fue eliminada.', $html);
        self::assertStringNotContainsString('hx-put=', $html, 'No hay fila que editar.');
    }

    public function test_put_con_token_valido_actualiza_la_tarea(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan integral',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Comprar pan integral', $task->fresh()->title);
        self::assertStringContainsString('Comprar pan integral', (string) $response->getBody());
        // El swap vuelve a la lista normal: editing_id queda en null.
        self::assertStringNotContainsString('hx-put=', (string) $response->getBody());
    }

    public function test_put_sin_titulo_no_actualiza_y_muestra_el_flash(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => '',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title, 'La validación falló: el título no cambia.');
        self::assertStringContainsString('bg-red-100', (string) $response->getBody(), 'El flash de error no se renderizó.');
        // La fila se queda en modo formulario: si volviera a la lista, el
        // usuario pierde el lugar que estaba editando junto con el error.
        self::assertStringContainsString('hx-put=', (string) $response->getBody());
    }

    public function test_put_a_un_id_inexistente_avisa_en_lugar_de_ignorar(): void
    {
        $token = $this->token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/999999', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'No debería guardarse',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'La tarea no existe o ya fue eliminada.',
            (string) $response->getBody()
        );
        self::assertSame(0, Task::count(), 'No se creó ninguna fila nueva.');
    }

    public function test_put_sin_token_devuelve_400_para_htmx(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, ['title' => 'No debería guardarse'], htmx: true)
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title);
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig. El name real del
     * input es `csrf_name` / `csrf_value`: `csrf_name_key` es variable de Twig
     * y no aparece en el HTML renderizado.
     */
    private function token(): array
    {
        $html = (string) self::app()->handle(self::request('GET', '/tareas'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    private static function request(string $method, string $path, array $body = [], bool $htmx = false): ServerRequestInterface
    {
        // URI absoluta y body crudo con su Content-Type, para que pase por el
        // Body Parsing Middleware igual que en producción.
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
```

Cuatro cosas que estos tests dejan por escrito. Primero: `hx-put` manda el body como
`application/x-www-form-urlencoded`, así que si `addBodyParsingMiddleware()` no lo parsea,
`getParsedBody()` devuelve `null`, la validación revienta con un 500 y no con un mensaje.
Segundo: los tokens del form de edición salen del mismo render que la fila, no del form de
altas que queda fuera del panel. Tercero: un id inexistente no puede tumbar la página, pero
tampoco puede responder en silencio — `edit()` y `update()` buscan la fila con `Task::find()` y,
si no existe, devuelven el panel con `La tarea no existe o ya fue eliminada.` en el flash; nunca
un 404, porque htmx metería la página de error dentro de `#tareas-panel`. Cuarto: cuando la
validación falla, `update()` devuelve `editing_id` con el id de la fila, para que la fila no
salga del modo formulario justo cuando aparece el mensaje.

### 19.9 Qué NO testear

- **Eloquent.** Es de Laravel. `Task::create()` inserta una fila: eso es un test de
  SQLite, no tuyo.
- **Twig.** Es de Twig. Un test de render de plantillas testea el motor de plantillas.
- **Slim.** El framework ya tiene su propia suite.

Y una regla de costo: **cada test que escribas tiene que pagar su mantenimiento**. Un
test que asserta comportamiento trivial o que se rompe con cada refactor es peor que
nada, porque entrenás a ignorar rojas. Si no te da miedo que falle, no lo escribas.

## 20. Troubleshooting: síntomas, no theory

Este capítulo existe porque los capítulos 1 a 18 te dicen cómo **configurar** y ninguno
te dice qué hacer cuando algo ya está mal. Es el hueco más grande que le quedaba a esta
guía, y es el que más te va a costar la primera hora en el teléfono.

Cada entrada lleva un tag que dice de dónde sale:

| Tag | Significado |
|---|---|
| **[v]** | Verificado acá: lo corrí contra el proyecto de referencia de esta guía y te paso la salida real. |
| **[t]** | **No verificado en dispositivo.** Es lo más probable en un teléfono real, pero nadie lo ejecutó todavía — ni yo, ni vos hasta que lo pruebes. Tratá la salida esperada como hipótesis, no como hecho. |
| **[d]** | Comportamiento documentado por el proyecto upstream. No lo probé yo. |

Esa distinción es el punto del capítulo. El cap. 1 al 19 es casi todo **[v]** o **[d]**
en la mitad PHP, y casi todo **[t]** en la mitad Termux. Saber cuál de las dos estás
mirando es la diferencia entre debuggear y adivinar.

### 20.1 La app no instala: `Unable to locate package`

**[t]** Es el primer riesgo real de la guía entera, y el único que puede invalidar el
capítulo 16 para abajo. Toda la guía asume que `pkg` tiene paquetes `arm`.

```bash
uname -m                        # armv7l = 32-bit, aarch64 = 64-bit
pkg update
pkg install php -y
```

Si sigue sin encontrar el paquete, el problema no es tu proyecto: es el repo que estás
apuntando. Cambialo y reintentá:

```bash
termux-change-repo              # elegí un mirror distinto
pkg update && pkg install php -y
```

Lo que **sí** es un hecho verificado: las releases de `termux-app` con feed público
incluyen el APK de 32 bits (`armeabi-v7a`), en las variantes `apt-android-5` y
`apt-android-7`. La variante de 32 bits **existe como artefacto**. Lo que no está
verificado es que el repo de paquetes siga compilando para el arch `arm`, que es otra
cosa: la declara `termux-packages/scripts/properties.sh`.

Si llegaste hasta acá y falló, no sigas con el cap. 2 — no vas a poder instalar Composer
sin PHP. Es el momento de decidir si el teléfono puede con esto o conviene 64-bit.

### 20.2 PHP no trae SQLite

```bash
php -m | grep -i sqlite
# esperado: pdo_sqlite y sqlite3
```

En Termux el paquete `php` normalmente los trae compilados adentro, así que un resultado
vacío significa que no tenés el `php` de Termux. **[t]** Comprobá con `which php` que
esté bajo `$PREFIX/bin` y no en otro lado del `PATH`.

Sin `pdo_sqlite`, el contenedor de DI del cap. 6 revienta al hacer el `PRAGMA`, y como
eso corre en `bootstrap/app.php` **antes** del error middleware, no vas a ver la página
de error: vas a ver el stack trace crudo (ver 20.3).

### 20.3 `QueryException` envolviendo `SQLiteDatabaseDoesNotExistException`

**[v]** Es la causa número uno de "la app no arranca y ni siquiera muestra el error
bonito", y el cap. 5 ya lo advierte: `DB_DATABASE` apunta a un path que no existe en
**tu** teléfono.

```bash
ls -la ~/proyectos/mi-app/database/     # ¿existe el archivo?
```

El path del cap. 5 es `/data/data/com.termux/files/home/proyectos/mi-app/database/database.sqlite`.
Es el sandbox de Termux, pero **cambia si clonaste el proyecto en otro lado**. Corré
`pwd` en la raíz de tu proyecto y corregí la variable.

El detalle que confunde: el error sale del `PRAGMA` en `config/container.php`, que se
ejecuta **dentro de `bootstrap/app.php`**, o sea antes de que exista el error middleware.
Por eso ves la excepción cruda en vez de `errors/error.twig`. No es un bug del error
handler: es que todavía no estaba instalado.

### 20.4 Composer muere sin decir nada

**[t]** El síntoma es el peor posible: `composer install` no imprime error de PHP, no
deja stack trace, y tu shell vuelve. Moriste por el OOM killer del kernel.

```bash
composer install; echo "exit=$?"
# exit=137 es la firma del OOM killer (128 + 9 = SIGKILL)
```

La solución no es `-1`, es resolver afuera y traer `composer.lock` + `vendor/`. Está
desarrollada en el cap. 17.1 y es el consejo más importante de la guía para 32 bits.

Si preferís resolver en el teléfono, poné un límite **realista** y reportable:

```bash
export COMPOSER_MEMORY_LIMIT=1024M
composer install --prefer-dist --no-dev --no-scripts
```

### 20.5 `Class "Respect\Validation\Validator" not found` o `V::create()` falla

**[v]** Es el bug del cap. 4, y es el que más gente va a comer si arma el `composer.json`
a mano. El síntoma aparece **en la primera request**, no en el `composer install`, que es
lo que lo hace confuso.

```bash
composer show respect/validation | head -2
# versions : * 2.5.0     <- correcto
# versions : * 3.x.x     <- 3.x, donde Validator pasó a ser interface
```

La causa: agregaste las dependencias con `composer require respect/validation` sin el
`^2.0`, y el instalador te trajo 3.x. Ahí `V::create()`, `assert()` y las excepciones
anidadas que usa `app/Support/Validator.php` ya no existen. Corregí el `require` y
`composer update respect/validation`.

### 20.6 Página de error vacía (0 bytes)

**[v]** El cap. 7 lo explica: `Twig::render()` escribe en el stream **sin rebobinar**, así
que con el cursor al final `getContents()` devuelve `''`. El fix ya está aplicado en el
cap. 7 (`$stream->rewind()`), pero lo vas a ver si tocaste esa parte o si tenés una copia
vieja.

Otro camino al mismo síntoma: `errors/error.twig` con un error de sintaxis. Esa plantilla
está envuelta en `try/catch` y degrada a texto plano, así que **no** la toques a la
ligera. Recordá que deliberadamente **no** extiende `layouts/app.twig` (cap. 7.1): si le
agregás un `{% extends %}` y el layout es lo que está roto, entrás en loop.

Para descartar la cache de plantillas:

```bash
rm -rf storage/cache/twig/*
```

### 20.7 Un 404 que devuelve 500, o un 404 que pesa 154 KB

**[v]** Es el bug de Whoops, y son dos síntomas distintos con dos causas distintas:

- **El 404 sale como 500** → `allowQuit(false)` y `sendHttpCode(false)` no están puestos
  en `config/middleware.php`. Con los defaults, Whoops manda 500 para *toda* excepción,
  incluido un 404, y sale del proceso con `exit(1)` — por lo que
  `SecurityHeadersMiddleware` nunca corre y la página se sirve sin
  `X-Content-Type-Options`. Los tests pasan igual, porque `PrettyPageHandler` no renderiza
  en SAPI `cli`.
- **El 404 pesa 154 KB** → Whoops también está atendiendo los 4xx. Medido: 154.490 bytes
  con Whoops, 507 bytes sin él. Un 404 no es un defecto y no tiene stack trace que mirar;
  por eso la rama de Whoops está acotada a `$status >= 500` en el cap. 7.

```bash
curl -sI http://127.0.0.1:8080/no-existe | head -5
# esperás HTTP/1.1 404 + X-Frame-Options: DENY + X-Content-Type-Options: nosniff
```

### 20.8 La sesión no persiste: todo deslogueado o CSRF mismatch

**[t]** Es el bug más silencioso del stack, porque **no da ningún error**. Después de
cerrar la terminal notás que perdiste la sesión, o el POST de HTMX vuelve con
`Token mismatch` sin que sepas por qué.

Tres causas, en orden de probabilidad:

1. **`session.save_path` murió.** Android puede limpiar un directorio temporal del
   sandbox. Verificá y fijalo (cap. 17.4):

   ```bash
   php -i | grep -E 'session.save_path|session.use_strict_mode'
   ```

2. **`secure => true` sin HTTPS.** Si activaste la cookie como `secure` pero servís por
   HTTP plano, el navegador **no** manda la cookie y la app falla en silencio. Para uso
   en `127.0.0.1`, `secure` tiene que ser `false`. Es el ítem del cap. 18.

3. **El directorio no existe.** `ini_set()` a un path inexistente no avisa: las sesiones
   se pierden sin dejar rastro.

   ```bash
   mkdir -p storage/sessions
   ```

Para ver si el cookie se está mandando de verdad:

```bash
curl -sI http://127.0.0.1:8080/tareas | grep -i set-cookie
# esperado: mi_app_session=...; path=/; HttpOnly; SameSite=Lax
```

Ojo: `HttpOnly` y `SameSite=Lax` tienen que estar. La ausencia de `HttpOnly` significa que
el cap. 8 quedó a medio aplicar.

### 20.9 El toast de CSRF no aparece en el navegador

**[v]** Este se encuentra tarde y es el que más se escapa, porque el backend funciona
perfecto: el `failureHandler` emite `HX-Trigger: {"csrf":"..."}` y el navegador lo ignora
en silencio. El listener tiene que estar en el layout y en `document.body` o
`document`, porque **HTMX no hace bubbling de eventos por default**.

```bash
curl -sI -X POST http://127.0.0.1:8080/tareas | grep -i hx-trigger
# el header tiene que estar en la respuesta del POST fallido
```

Si el header llega y el toast no, el problema es el listener o el CSS: `[x-cloak] {
display: none !important; }` tiene que estar presente, o la vaca de Alpine parpadea en
cada carga.

### 20.10 Alpine o Tailwind no hacen nada

**[t]** La página se ve sin estilos o los atributos `x-*` no hacen nada. Causa casi
segura: **el CDN no cargó** o **el SRI no matchea**.

```bash
curl -sI https://cdn.jsdelivr.net/npm/alpinejs@3.17.4/dist/cdn.min.js | head -3
# tiene que devolver 200
```

En orden:

1. ¿Tenés internet? Un teléfono en datos móviles o en una red que bloquea jsdelivr deja
   la página sin framework. Es la causa más subestimada.
2. ¿El SRI matchea? Si el CDN devolvió algo distinto, el navegador **rechaza el script**
   entero y solo lo dice en la consola. Para eso sirven los `integrity`: para fallar.
3. ¿La consola muestra `Refused to execute script`? Eso es SRI.

Para debuggear en el móvil: `chrome://inspect` desde el desktop con el cable conectado.

### 20.11 La app se muere cuando apagás la pantalla

**[t]** Funciona, la refrescás, y a los dos minutos deja de responder. No es un crash:
Android **suspende el proceso** cuando la pantalla se apaga.

```bash
pkg install termux-api
termux-wake-lock
```

Y si se muere cuando **cerrás la terminal** (que es distinto), es que `php -S` corre en
foreground y va dentro de `tmux`. Ambos en los caps. 16.1 y 16.2.

### 20.12 `Address already in use`

**[v]** Otro proceso tiene el puerto. Es el error más tonto y el más frecuente cuando dejás
el server en `tmux` y después querés levantarlo otra vez.

```bash
tmux ls                   # ¿ya tenés una sesión con el server corriendo?
lsof -i :8080             # ¿quién lo tiene?
```

Matá el proceso viejo en vez de abrir un puerto nuevo. Terminar sirviendo en el 8091 para
siempre, porque cada reinicio usa un puerto nuevo, es la forma más común de convertir esto
en un proyecto que nadie puede levantar.

### 20.13 HTMX se cuelga o dos requests se pisan

**[v]** `php -S` es **single-threaded**: si tu página dispara dos `hx-get` al mismo tiempo,
el segundo espera al primero. Con workers los tenés, pero entrás al problema siguiente:
dos requests concurrentes sobre la **misma sesión** se contendian por el lock de archivo
de PHP.

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public
```

Y en `php.ini`, `session.lazy_write=1` para tomar el lock menos tiempo. Detalle en los
caps. 16.4 y 17.2.

### 20.14 Cómo leer el log cuando todo lo anterior falló

**[v]** El error handler del cap. 7 loguea **5xx como `error` y 4xx como `info`**. Esa
distinción es deliberada: un 404 no merece una línea de error, o cualquier URL que
alguien pruebe te inunda el archivo.

```bash
tail -f storage/logs/app.log
```

Si el archivo no existe, el problema es que `storage/logs/` no existe o no es writable, y
Monolog deja de escribir **sin avisar**:

```bash
mkdir -p storage/logs storage/cache storage/sessions
```

### 20.15 "No puede conectarse": `localhost` no es `127.0.0.1`

**[v]** Síntoma: el servidor arranca e imprime `Development Server (http://localhost:8080)
started`, pero el navegador dice **"No puede conectarse al servidor en 127.0.0.1:8080"**.
No es firewall, no es que la app esté rota, y no es el puerto ocupado.

La causa es que `localhost` y `127.0.0.1` son direcciones **distintas** para el que escucha:

```bash
# en Windows
Resolve-DnsName localhost -Type A       # 127.0.0.1   IPv4
Resolve-DnsName localhost -Type AAAA    # ::1         IPv6
```

Y PHP se queda escuchando en **una sola** de ellas. Medido en esta máquina con el mismo
PHP 8.5.10:

| Comando | A la que queda escuchando | `127.0.0.1:8081` | `[::1]:8081` |
|---|---|---|---|
| `php -S localhost:8081` | `::1` (solo IPv6) | **000 — refused** | 200 |
| `php -S 127.0.0.1:8081` | `::1`, `127.0.0.1` | 200 | 200 |
| `php -S 0.0.0.0:8081` | `::1`, `127.0.0.1`, `0.0.0.0` | 200 | 200 |

Si llamás por `localhost` te va a funcionar; si llamás por `127.0.0.1` no. Y el navegador
muchas veces muestra una cosa y pide la otra: **`http://127.0.0.1:8080` y
`http://localhost:8080` no son la misma URL**, y podés estar curado por una mientras la otra
falla.

Diagnóstico en una línea — mirá a qué IP está escuchando:

```bash
# Windows
netstat -ano | findstr :8080
# Linux / Termux
ss -ltnp | grep 8080
```

Si dice `::1` y no `127.0.0.1` o `0.0.0.0`, ya tenés el diagnóstico.

**La solución es no usar `localhost` como argumento de bind.** Usá `127.0.0.1` para solo la
máquina, o `0.0.0.0` para la red — que es exactamente lo que ya hace la guía:

```bash
composer serve                                  # -> php -S 0.0.0.0:8080 -t public
php -S 127.0.0.1:8080 -t public                 # solo desde esta máquina
```

Este error es fácil de mal diagnosticar como firewall o como "la app no levanta", y lleva
la gente a abrir puertos o a tocar el código cuando el problema entero es qué hostname se
pasó como primer argumento.

## 21. Cómo verificar esta guía vos mismo

Este capítulo existe porque una guía de 21 capítulos que dice "confiá en mí" no vale
casi nada. Todo lo que la guía afirma es reproducible y, en la mayoría de los casos, con
cinco minutos de tu lado.

La lógica es esta: los capítulos 4 a 19 son código, y el código se **ejecuta**. Los
capítulos 1 a 3 y 16 a 18 son afirmaciones sobre tu teléfono, y esas **no** se pueden
verificar desde afuera — solo corriendo en el dispositivo. Por eso este capítulo está
partido en dos, y es honesto sobre cuál de las dos partes podés hacer hoy.

### 21.1 Nivel A — cinco minutos, los chequeos que pagan

Corré estos cinco. Cada uno cubre una clase de bug real que esta guía ya tuvo.

```bash
# 1. Dependencias: 2.x o la app revienta en la primera request (cap. 4)
composer show respect/validation | head -2
# esperado: versions : * 2.5.0

# 2. Suite completa: el esqueleto, no la demo (cap. 19)
composer test
# esperado: OK (37 tests, 89 assertions)

# 3. El 404 tiene que seguir siendo 404, con sus headers (caps. 7 y 7.2)
php -S 127.0.0.1:8080 -t public &
curl -sI http://127.0.0.1:8080/no-existe | head -5
# esperado: HTTP/1.1 404
#           X-Frame-Options: DENY
#           X-Content-Type-Options: nosniff
#           Referrer-Policy: strict-origin-when-cross-origin

# 4. El 404 tiene que pesar bytes, no cientos de KB (cap. 7)
curl -s http://127.0.0.1:8080/no-existe | wc -c
# esperado: 507

# 5. La cookie de sesión tiene que viajar (cap. 8)
curl -sI http://127.0.0.1:8080/tareas | grep -i set-cookie
# esperado: mi_app_session=...; path=/; HttpOnly; SameSite=Lax
```

Los cinco valores esperados son los reales del proyecto de referencia, medidos, no
declarados. Si alguno no coincide, ese es tu bug y no hace falta seguir leyendo: es el
primero.

Un chequeo extra, también real, que verifica de un golpe la superficie del frontend:

```bash
curl -s http://127.0.0.1:8080/tareas | wc -c
# esperado: 2273 con la base vacía; suma unos 870 bytes por fila y varía unos
#           pocos bytes con cada token CSRF. Lo que no puede salir es 0.
curl -s http://127.0.0.1:8080/tareas | grep -c 'csrf.window'
# esperado: 1 o más — el listener de CSRF tiene que estar en el layout
```

### 21.2 Nivel B — cuarenta y cinco minutos, el que de verdad importa

Acá está la prueba real, y no la puede hacer nadie que ya sepa qué viene después de cada
paso. La guía se verificó contra un proyecto de referencia, y eso garantiza que el
**código** de la guía es correcto. No garantiza que la guía no tenga un salto raro, un
comando que haya que improvisar, o un detalle del cap. 3 que asumí sin que lo notaras.

La forma de detectarlo es ser un lector ciego de tu propia guía:

```bash
mkdir -p ~/verificacion && cd ~/verificacion
```

Después seguí los capítulos 1 a 19 **de corrido, sin abrir el proyecto de referencia ni
esta conversación**. Anotá, en un archivo o en un papel, cada una de estas tres cosas:

- **Cada comando que tengas que improvisar.** Ese es el hueco, sin rodeos.
- **Cada error que no esté en el capítulo 20.** Sumalo a la tabla de 20.
- **Cada paso que no entiendas sin contexto.** Un paso que necesitás que te expliquen es un
  paso que le va a faltar a cualquiera.

Si al terminar no anotaste nada, la guía está completa y me la podés dar de vuelta. Si
anotaste algo, eso es un defecto de documentación mío, no tuyo.

### 21.3 Nivel C — el script que usé para encontrar los huecos

Si tocás la guía y querés saber si dejaste algo afuera, este script compara cada línea no
trivial de tu proyecto contra el texto de la guía y te dice qué no está documentado.

```bash
G=guia-stack-php-termux.md
for f in $(find app config routes tests resources bootstrap -type f \
           \( -name '*.php' -o -name '*.twig' \) 2>/dev/null); do
  miss=$(grep -vE '^\s*(//|\*|#|\{#)' "$f" | grep -vE '^\s*$' | sed 's/^\s*//' \
         | while read -r l; do grep -qF -- "$l" "$G" || echo x; done | wc -l)
  [ "$miss" -gt 0 ] && echo "$f: $miss lineas NO documentadas"
done
```

Dos detalles de este script que no son opcionales, porque sin ellos **no hace nada**:

- El `--` en `grep -qF --`. Sin él, toda línea que empiece con `->` (y en este proyecto
  casi todas las líneas encadenadas) la interpreta grep como una opción y aborta: te
  imprimía errores de uso y un recuento de líneas que no existían.
- El `grep -vE '^\s*(//|\*|#|\{#)'` descarta comentarios a propósito. Consecuencia honesta:
  **este script no verifica que los comentarios estén documentados.** Solo el código. Para
  probar que detecta algo, metele una línea de código real que no esté en la guía, no un
  comentario: un comentario falso no lo va a marcar y vas a creer que el script anda.

Salida esperada: **nada**.

Y algo que conviene tener claro para no malinterpretar el resultado: el `find` cubre
`app`, `config`, `routes`, `tests`, `resources` y `bootstrap`, y **nada más**. Entonces
`composer.json` y `.env` no aparecen nunca, ni aunque los vacíes. No es que estén
"cubiertos": es que están fuera del alcance del escaneo, y está bien que lo estén, porque
los dos son archivos que la guía no te pide copiar textualmente:

- `composer.json`: el `require` lo genera Composer. La guía da el fragmento y te dice que
  lo merges con lo que ya tenés, no que lo reemplaces.
- `.env`: el path de `DB_DATABASE` es específico de dónde clonaste el proyecto. La guía
  documenta la variable y el motivo por el que hay que editarla, no tu path.

Cualquier otra línea que aparezca es un hueco real.

### 21.4 El chequeo de cinco segundos que sí depende de tu teléfono

Este es el único que decide si la guía te sirve, y es el que no pude verificar yo:

```bash
uname -m                        # armv7l = 32-bit. Si dice aarch64 la guía igual anda,
                                # pero el cap. 17 deja de aplicar
pkg install php -y              # ¿existe php para tu arquitectura?
php -m | grep -i sqlite         # pdo_sqlite y sqlite3 tienen que aparecer
php -r "echo ini_get('memory_limit'), PHP_EOL;"
```

Si `pkg install php` responde `Unable to locate package`, no sigas con el cap. 2: el
problema no es la guía, es que el repo de paquetes de tu arquitectura no está. Volvé al
20.1, que es donde eso se resuelve.

### 21.5 Lo que esta guía NO puede verificar por vos

Para que la confianza sea del tamaño correcto, esto queda abierto y no lo puedo cerrar
desde acá:

| No verificado | Por qué | Cómo lo cerrás |
|---|---|---|
| Que `pkg` tenga paquetes `arm` | Es estado de un repo ajeno y cambiante | 21.4, o 20.1 si falla |
| Que funcione `termux-wake-lock` | Necesita Android suspendiendo el proceso | 20.11, en el teléfono |
| Consumo real de RAM bajo OOM killer | Es el OOM killer del kernel, no PHP | Cap. 17.1, con `echo "exit=$?"` |
| Que el CDN cargue en tu red | Depende de tu operador y tu red | 20.10 |
| Que SQLite ande bien con WAL en Android | Ver 17.6: el backup tiene que llevar `-wal` y `-shm` | Cap. 17.6 |

Todo lo demás — la lógica PHP, el pipeline, las sesiones, el CSRF, los tests, los headers
de seguridad, el SRI — está verificado por ejecución, y el Nivel A lo reproduce en cinco
minutos.
