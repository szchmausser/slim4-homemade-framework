# Guía paso a paso — Framework base en Termux (Slim 4 + Eloquent + Twig + HTMX/Alpine/daisyUI)

Stack confirmado: Slim 4, php-di, phpdotenv, Eloquent (Capsule standalone), Phinx, **respect/validation**, Twig, slim/csrf, **monolog/monolog**, whoops (solo dev), SQLite3, HTMX + Alpine.js + **daisyUI 5 as-is** (los tres vendorizados, cero CDN en producción), estructura de carpetas al estilo Laravel.

> Nota sobre `respect/validation`: buena decisión — su repo tiene actividad reciente, a diferencia de `illuminate/validation` standalone (pesado) o alternativas abandonadas.
>
> Nota sobre daisyUI: se usa **as-is**, temas claro/oscuro por defecto, sin personalizar. Nada de `dark:` utilities (el tema lo gobierna `data-theme`), nada de JS de terceros, nada de variantes `is-drawer-*` (no existen en el CSS linkeado — verificado, capítulo 14).

> **Las copias de archivos que hay en esta guía están verificadas, no mantenidas a mano.** Cada bloque que declara un archivo del repo (`resources/views/tasks/_list.twig`, por ejemplo) es una copia literal de ese archivo: `composer docs:check` falla si difieren y `composer docs:sync` las reescribe desde el repo. Un fragmento (un método suelto, tres líneas de un array) se marca `excerpt` en el info string y queda fuera del control. El motivo es medido: la primera vez que se comparó, **27 de 56 copias estaban viejas** (una vista tenía tres features de atraso).

## Índice
1. Preparar Termux
2. Instalar Composer
3. Estructura de carpetas (Laravel-way)
4. Dependencias del backend
5. Variables de entorno (`.env.example` + `.env`)
6. Contenedor DI (con base resiliente)
7. Bootstrap de la app y pipeline de middlewares
8. Sesiones nativas (middleware propio)
9. Rutas, controladores y auth (login/logout/remember-me)
10. Modelos Eloquent (+ User y RememberToken)
11. Migraciones con Phinx (+ seed del admin)
12. Validación estilo Laravel sobre respect/validation
13. CSRF en formularios Twig
14. Frontend: assets vendorizados + SRI (nada de CDN)
15. Shell de la app: sidebar, header, temas y footer
16. Ejemplo funcional de punta a punta (lista de tareas con HTMX + paginación reutilizable)
17. Levantar el servidor y mantenerlo vivo (+ backup)
18. El capítulo que de verdad importa: 32 bits
19. Antes de exponer la app fuera de tu teléfono
20. Testing: testear el esqueleto, no la demo (+ suite de auth y paginación)
21. Troubleshooting: síntomas, no theory
22. Cómo verificar esta guía vos mismo
23. Archivos que existen pero NO se replican

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

Para evitar que el solver de dependencias se quede sin memoria (el problema clásico con Eloquent en 32 bits), dejalo seteado de forma permanente en tu shell. Ojo: el valor es `1024M`, NO `-1` (cap. 18.1 explica por qué `-1` te mata sin mensaje):

```bash
echo 'export COMPOSER_MEMORY_LIMIT=1024M' >> ~/.bashrc
source ~/.bashrc
```

## 3. Estructura de carpetas (Laravel-way)

```bash
mkdir -p ~/proyectos && cd ~/proyectos
mkdir mi-app && cd mi-app

mkdir -p app/Http/Controllers app/Http/Middleware app/Models app/Support
mkdir -p bootstrap config database/migrations
mkdir -p public/assets resources/views/layouts resources/views/tasks resources/views/partials resources/views/errors
mkdir -p routes storage/logs storage/cache/twig storage/sessions
mkdir -p tests/Http tests/Support
```

Árbol final (lo que va a git; `storage/`, `vendor/`, `.env` y la base NO viajan, ver `.gitignore` en el cap. 5):

```
mi-app/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php      # base abstracta: render()+csrf()+viewDefaults()
│   │   │   └── TaskController.php
│   │   └── Middleware/
│   │       ├── SessionMiddleware.php
│   │       └── SecurityHeadersMiddleware.php
│   ├── Models/
│   │   └── Task.php
│   └── Support/
│       ├── Flash.php
│       └── Validator.php
├── bootstrap/
│   └── app.php
├── config/
│   ├── container.php
│   └── middleware.php
├── database/
│   ├── migrations/
│   │   └── 001_create_tasks_table.php
│   └── database.sqlite             # se autocrea; NO va a git
├── public/
│   ├── assets/                     # vendorizado, SÍ va a git
│   │   ├── tailwind-browser-4.3.3.js
│   │   ├── htmx-2.0.11.min.js
│   │   ├── alpine-3.17.4.min.js
│   │   └── daisyui-5.7.46.css
│   └── index.php
├── resources/
│   └── views/
│       ├── layouts/app.twig        # shell: sidebar+header+footer
│       ├── errors/error.twig       # standalone a propósito (cap. 7.1)
│       ├── home.twig
│       ├── partials/{_csrf.twig,_logo.twig,_icon.twig}
│       └── tasks/{index.twig,_panel.twig,_list.twig}
├── routes/
│   └── web.php
├── storage/                        # se autocrea; NO va a git
│   ├── cache/twig/
│   ├── logs/
│   └── sessions/
├── tests/
│   ├── bootstrap.php
│   ├── Http/{CsrfFlowTest,DeleteTaskTest,EditTaskTest,ErrorHandlingTest,PartialRenderTest,RoutingTest}.php
│   └── Support/{AppFactory,ValidatorTest,FlashTest}.php
├── .env                            # local; NO va a git (se genera de .env.example)
├── .env.example                    # SÍ va a git
├── .gitattributes
├── .gitignore
├── composer.json
├── composer.lock                   # SÍ va a git (cap. 18.1)
├── phinx.php
└── phpunit.xml
```

## 4. Dependencias del backend

```bash
composer init --name="tuusuario/mi-app" --type=project --require="php:>=8.4.1" --no-interaction

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
composer require --dev phpunit/phpunit
```

> **Techo duro: Phinx queda en `^0.13`, y no es conservadurismo.** Verificado contra
> Packagist: las líneas 0.14, 0.15 y 0.16 exigen `php-64bit` (la 0.16.12 ni siquiera
> pide `php` a secas, pide `php-64bit >= 8.1` directo). En un ARM de 32 bits eso es
> imposible por arquitectura, no por versión: ningún bump dentro de esas líneas te
> salva. La 0.13.4 (`php >= 7.2`, sin requisito 64-bit) es la última instalable, y
> la API que usa esta guía (`change()`, `table()->...->create()`, `migrate`,
> `rollback -t 0`, `status`) es idéntica entre ambas. Si upstream algún día quita
> el requisito, se re-evalúa; mientras tanto, `^0.13` es techo, no pin.

> **PHP ≥ 8.4.1 obligatorio, sin atajos.** No lo fija un solo pin: `illuminate/database ^13.33`
> exige `^8.3`, y `symfony/clock` y `symfony/translation` (dependencias de runtime de
> Illuminate) más `phpunit/phpunit ^13.3` exigen `>=8.4.1`. Con un PHP menor no instala
> ni por clon (el lock falla el platform check) ni desde cero. Verificá primero con
> `php -v` (cap. 22.4); si tu Termux trae uno viejo, actualizá el paquete `php` antes de
> seguir. No hay fallback documentado porque implicaría retroceder varios pins a la vez.
>
> **Trade-off de pinear `respect/validation:^2.0`:** hoy resuelve a **2.5.0**, que
> corre limpio sobre PHP 8.5 (cero deprecations). Mientras no migres a 3.x
> (`Validator` interface, sin `V::create()` ni excepciones anidadas), `^2.0` es
> lo correcto y el capítulo 12 no se toca.

`composer.json` completo (incluye scripts para Termux y autoload optimizado):

```json
{
    "name": "tuusuario/mi-app",
    "type": "project",
    "require": {
        "php": ">=8.4.1",
        "slim/slim": "^4.15",
        "slim/psr7": "^1.8",
        "php-di/php-di": "^7.1",
        "vlucas/phpdotenv": "^5.7",
        "illuminate/database": "^13.33",
        "twig/twig": "^3.30",
        "slim/twig-view": "^3.4",
        "slim/csrf": "^1.5",
        "respect/validation": "^2.0",
        "monolog/monolog": "^3.12"
    },
    "require-dev": {
        "robmorgan/phinx": "^0.13",
        "filp/whoops": "^2.18",
        "phpunit/phpunit": "^13.3"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "serve": "@php scripts/serve.php",
        "migrate": "phinx migrate",
        "push": "bash scripts/push-termux.sh",
        "db:backup": "php scripts/db-backup.php",
        "test": "phpunit",
        "lint": "@php scripts/lint.php",
        "docs:check": "@php scripts/docs-check.php",
        "docs:sync": "@php scripts/docs-sync.php",
        "phpstan": "@php scripts/phpstan.php",
        "migrate:fresh": "@php scripts/migrate-fresh.php",
        "verify": [
            "@lint",
            "@docs:check",
            "@phpstan",
            "@test",
            "@migrate:fresh"
        ],
        "install:termux": "composer install --prefer-dist --no-scripts",
        "check:termux": "@php -r \"echo PHP_INT_SIZE === 4 ? '32-bit PHP detectado' : '64-bit PHP', PHP_EOL;\""
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
```

```bash
composer dump-autoload -o
composer check:termux   # te dice si tu PHP es de 32 o 64 bits
```

En el teléfono, para instalar dependencias usá SIEMPRE el script low-mem (banderas que bajan el pico del solver), nunca un `update` pelado:

```bash
composer install:termux
# equivalente a: COMPOSER_MEMORY_LIMIT=1024M composer install --prefer-dist --no-scripts
# (SIN --no-dev a propósito: en el teléfono necesitás Phinx y PHPUnit, o sea
# las dev. --no-dev solo ahorra memoria en el SOLVER del update, y el install
# no resuelve nada: saca las dev y te deja sin migrate ni tests.)
```

## 5. Variables de entorno

`.env.example` (este SÍ se commitea — es la referencia documentada):

```
# Copiá a .env y ajustá a tu máquina:
#   cp .env.example .env
# .env nunca se commitea (está en .gitignore).
APP_ENV=local
APP_DEBUG=true

# Opcional: si no se setea, la app usa database/database.sqlite relativo
# al proyecto. En Termux, si clonaste en otro path, descomentá y poné TU absoluto:
# DB_DATABASE=/data/data/com.termux/files/home/proyectos/mi-app/database/database.sqlite
#DB_DATABASE=

SESSION_NAME=mi_app_session
```

Generá tu `.env` local:

```bash
cp .env.example .env
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"  # si algún día necesitás firmar cookies
```

> **DB_DATABASE es opcional.** Si no está, la app usa `database/database.sqlite`
> relativo al proyecto y lo crea solo (cap. 6). El path absoluto del ejemplo es
> para el caso "cloné en otro lado". La causa N.º 1 de "la app no arranca y ni
> siquiera muestra el error bonito" era justamente un path inexistente: ahora
> verías un `RuntimeException` accionable en vez del stack crudo (cap. 21.3).

`.gitignore` completo:

```
/vendor/
/database/*.sqlite
/database/*.sqlite-journal
/database/*.sqlite-wal
/database/*.sqlite-shm
/storage/logs/*.log
/storage/cache/
/storage/sessions/
/storage/backups/
/.phpunit.cache/
/tests/.phpunit.result.cache
.env
.atl/
```

Fijate qué cubre y por qué: la base y sus sidecars de WAL (`-wal`/`-shm`, cap. 18.6 — sin ellos el backup va incompleto pero al repo no van nunca), las sesiones (datos reales de usuarios), el caché de Twig, y `.env` (secretos locales). `composer.lock` **sí** se commitea a propósito: con el lock, `install` no resuelve dependencias — en 32 bits es la diferencia entre 10 segundos y un OOM (cap. 18.1). `public/assets/` SÍ va a git (vendorizado: clonar ya trae el frontend).

`.gitattributes` completo (una línea que salva el SRI):

```
# Evita que Git convierta LF->CRLF en assets vendorizados.
# Si cambia un byte, el SRI del layout falla y la app queda sin JS sin error visible.
public/assets/* binary
*.min.js binary
```

Sin esto, un checkout en Windows reescribe los `.js` a CRLF, el hash `integrity` deja de matchear y el navegador **rechaza el script en silencio**: página sin HTMX/Alpine y cero errores en el log. Es el tipo de bug que te hace dudar de todo menos del culpable.

## 6. Contenedor DI

`config/container.php` completo. Dos cosas cambiaron respecto a la primera versión de esta guía: la base tiene **default relativo + autocreación** (ya no revienta con path inexistente) y el `failureHandler` del CSRF distingue HTMX de formularios normales:

```php
<?php

declare(strict_types=1);

use App\Http\Middleware\RememberMeMiddleware;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Support\Url;
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

                // Prefijado como todo redirect: un 303 a '/' desde /app deja
                // al usuario en la raíz del dominio, que no es esta app.
                return $response->withStatus(303)->withHeader('Location', Url::path('/'));
            },
        );
    },

    SecurityHeadersMiddleware::class => \DI\autowire(SecurityHeadersMiddleware::class),

    RememberMeMiddleware::class => \DI\autowire(RememberMeMiddleware::class),
    RequireAuthMiddleware::class => \DI\autowire(RequireAuthMiddleware::class),
];
```

## 7. Bootstrap de la app y pipeline de middlewares

`bootstrap/app.php` completo. Cambios respecto a la primera versión: `safeLoad()` (no revienta sin `.env` — clave en un clon fresco) y autocreación de `storage/` + `database/` antes del container:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Support\Url;
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

// S13: el prefijo de ruta es parte de la RUTA registrada, no un detalle de las
// plantillas. Con APP_BASE_PATH=/app, una request a /app/tareas matchea la ruta
// /tareas (y /tareas, sin el prefijo, deja de existir). Sin prefijo es '' y el
// router se comporta exactamente como antes.
$app->setBasePath(Url::base());

(require __DIR__ . '/../config/middleware.php')($app);
(require __DIR__ . '/../routes/web.php')($app);

return $app;
```

`config/middleware.php` completo — respeta el pipeline LIFO, con un agregado necesario: **Body Parsing Middleware**, que Slim tampoco trae activado por defecto y que necesitás para leer `$_POST`/JSON antes de que el Guard de CSRF pueda validar el token del formulario:

```php
<?php

declare(strict_types=1);

use App\Http\Middleware\RememberMeMiddleware;
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

    // Entre sesión y routing: resume remember-me con la sesión ya abierta.
    // En orden de ejecución corre justo después de SessionMiddleware.
    $app->add(RememberMeMiddleware::class);

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

El error handler de arriba renderiza `errors/error.twig`, que recibe `code` y `message`:

`resources/views/errors/error.twig`:

```twig
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ code }} · Mi App</title>

    {# Sin favicon el navegador pide /favicon.ico y esta página autónoma
       costaría un 404 extra. Son ~55 bytes. #}
    <link rel="icon" type="image/svg+xml" href="{{ url('/favicon.svg') }}">

    {# Página autónoma a propósito: NO extiende layouts/app.twig. Un error no
       necesita sidebar, toasts ni los scripts de Alpine/htmx, y ese peso se
       paga caro en 32 bits (cap. 17). Sólo el bundle de daisyUI que la app ya
       vendoriza: un <link> cuesta ~60 bytes, y sin él todas las clases de
       abajo quedan inertes, que es exactamente el defecto que esto arregla.
       Sin JS no hay JIT de Tailwind, así que acá NO se usan utilidades
       (`flex`, `p-6`, `text-6xl`): sólo nombres que el bundle define. #}
    <link rel="stylesheet" href="{{ url('/assets/daisyui-5.7.46.css') }}">

    <style>
        /* Lo que en el resto de la app aporta el JIT de Tailwind y esta página
           no carga: el preflight que saca el margen del body, el alto de
           viewport y el centrado del texto. */
        body { margin: 0; }
        .error-main { min-height: 100dvh; }
        .error-body { align-items: center; text-align: center; }
    </style>
</head>
<body class="bg-base-200 text-base-content">
    {# El mensaje se deduce del código y NO se imprime el `message` que manda
       config/middleware.php: viene del vendor, en inglés ("Not found."), y
       puede arrastrar detalles internos de la excepción. El código queda a la
       vista para que soporte lo pueda pedir; un código desconocido cae al
       texto genérico, no a "sin mensaje". #}
    {% set mensaje = code == 404 ? 'No encontramos esa página.'
        : (code == 403 ? 'No tienes permiso para ver esto.'
        : (code == 405 ? 'Ese método no está permitido.'
        : (code == 419 or code == 400 ? 'La petición no es válida.'
        : 'Algo salió mal de nuestro lado.'))) %}
    <main class="hero error-main">
        <div class="hero-content">
            <div class="card card-border bg-base-100">
                <div class="card-body error-body">
                    <span class="badge badge-neutral">Error {{ code }}</span>
                    <h1 class="card-title">{{ mensaje }}</h1>
                    <a href="{{ url('/') }}" class="btn btn-primary btn-block">Volver al inicio</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
```

**Deliberadamente NO extiende `layouts/app.twig` y NO usa daisyUI.** Si el layout es justamente lo que se rompió, una página de error que depende del mismo layout o del CSS vendorizado vuelve a fallar — y como el error handler se ejecuta dentro del error handler, entrás en un loop hasta que se te queda la memoria. Esta plantilla no depende de nada: ni del layout, ni de la sesión, ni de Alpine, ni de los assets. Por eso el handler la envuelve en `try/catch` y degrada a texto plano si algo se rompe igual.

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

        $response = $response
            // El navegador deja de adivinar el tipo de contenido. Sin esto, un
            // archivo served como octet-stream que en realidad es HTML se
            // ejecuta. Es el mejor valor por byte de todo el set.
            ->withHeader('X-Content-Type-Options', 'nosniff')

            // Nadie puede embeber la app en un iframe y hacerte click sin
            // querer (clickjacking). Alternativa moderna: `frame-ancestors 'none'`
            // dentro de una CSP, pero eso implicaría adoptar CSP entera.
            ->withHeader('X-Frame-Options', 'DENY')

            // Los assets son locales con SRI, pero la app puede enlazar a
            // sitios externos. Sin esto, cada salida manda tu URL completa
            // (con paths y query string) en el header Referer.
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS solo cuando la app se sirve por HTTPS. Se gatea con el mismo
        // flag que las cookies Secure: activarlo en dev local deja al navegador
        // recordando un HTTPS que el server no habla.
        if (filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000'
            );
        }

        return $response;
    }
}
```

Registralo en `config/container.php` (`SecurityHeadersMiddleware::class => \DI\autowire(...)`) y agregalo al pipeline **después** del error middleware, como en el capítulo 7.

El orden importa: `addErrorMiddleware` es el que *genera* las respuestas 404/500, así que los headers tienen que agregarse **por fuera** de él o las páginas de error salen limpias.

### 7.3 Middlewares de auth: dónde van y por qué ahí

Dos middlewares más, con posiciones distintas por motivos distintos. En `config/container.php` (fragmento: las dos líneas que importan):

```php excerpt
RememberMeMiddleware::class => \DI\autowire(RememberMeMiddleware::class),
RequireAuthMiddleware::class => \DI\autowire(RequireAuthMiddleware::class),
```

`app/Http/Middleware/RememberMeMiddleware.php` (global: resume la sesión desde la cookie remember):

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RememberMe;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Resume la sesión desde la cookie remember-me, si hay una y no hay sesión.
 * Corre globalmente justo después de SessionMiddleware (ver config/middleware.php).
 * La rotación/limpieza viaja en ESTA misma respuesta: aplicada en otro lado,
 * el token nuevo nunca llegaría al navegador.
 */
final class RememberMeMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $outgoing = empty($_SESSION['user_id'])
            ? RememberMe::resumeFromCookie()
            : null;

        $response = $handler->handle($request);

        return $outgoing === null
            ? $response
            : $response->withAddedHeader('Set-Cookie', $outgoing);
    }
}
```

En `config/middleware.php`, entre routing y sesión (en ejecución corre justo después de `SessionMiddleware`, con la sesión ya abierta y antes de resolver la ruta):

```php excerpt
    // 3ro: resuelve la ruta solicitada
    $app->addRoutingMiddleware();

    // Entre sesión y routing: resume remember-me con la sesión ya abierta.
    // En orden de ejecución corre justo después de SessionMiddleware.
    $app->add(RememberMeMiddleware::class);

    // 2do: arranca la sesión nativa para toda la app
    $app->add(SessionMiddleware::class);
```

`app/Http/Middleware/RequireAuthMiddleware.php` (NO global: se agrega por grupo, solo a `/tareas`):

```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth;
use App\Support\Flash;
use App\Support\Url;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Puerta de las rutas protegidas (se agrega por grupo, no global).
 * HTMX no sigue un 303 con swap (no es 2xx): se le ordena navegación completa
 * con HX-Redirect en un 200 — el mismo patrón que el failureHandler de CSRF
 * usa con HX-Trigger. Formularios/navegación normal: 303 clásico.
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    public function process(Request $request, Handler $handler): Response
    {
        if (Auth::check()) {
            return $handler->handle($request);
        }

        if ($request->getHeaderLine('HX-Request') !== '') {
            return $this->responseFactory->createResponse(200)
                ->withHeader('HX-Redirect', Url::path('/login'));
        }

        Flash::set('error', 'Iniciá sesión para continuar.');

        return $this->responseFactory->createResponse(303)
            ->withHeader('Location', Url::path('/login'));
    }
}
```

El 200 + `HX-Redirect` es deliberado (no 401): htmx procesa ese header en respuestas completadas sin meterse en el camino de error. El 401 es para APIs; acá queremos navegación.

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
    /**
     * Parámetros de la cookie de sesión. Públicos para que el test verifique
     * Secure sin depender de abrir/cerrar la sesión nativa del proceso.
     *
     * @return array{lifetime: int, path: string, httponly: bool, samesite: string, secure: bool}
     */
    public static function cookieParams(): array
    {
        return [
            'lifetime' => 0,     // 0 = cookie de sesión, expira al cerrar el navegador
            'path'     => '/',
            'httponly' => true,  // inaccesible desde document.cookie
            'samesite' => 'Lax', // capa extra; el token CSRF sigue siendo la defensa real
            'secure'   => filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function process(Request $request, Handler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name($_ENV['SESSION_NAME'] ?? 'mi_app_session');

            // SESSION_SECURE=true es para cuando la app se sirve por HTTPS
            // (directo o detrás de un proxy TLS). En dev local queda false:
            // una cookie Secure no viaja por HTTP y la sesión no persiste.
            session_set_cookie_params(self::cookieParams());

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

### 8.1 Auth, remember-me y tokens CSRF compartidos

Tres clases de soporte para el cap. 9.2. La security-critical es `RememberMe`: cualquier cambio ahí merece relectura y suite verde.

`app/Support/Auth.php` (sesión de auth sobre la sesión nativa; solo guarda el id):

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Sesión de autenticación sobre la sesión nativa de PHP (que abre
 * SessionMiddleware). Solo guarda el id: el usuario se lee de la base en
 * cada request (una query indexada; si el usuario se borra, la sesión muere).
 */
final class Auth
{
    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function user(): ?User
    {
        $id = self::id();
        return $id === null ? null : User::find($id);
    }

    /** Establece la sesión. Regenera el ID (fijación de sesión). */
    public static function login(int $id): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $id;
    }

    /**
     * Vacía la sesión y regenera el ID. No la destruye: el CSRF vive ahí y
     * el redirect siguiente lo necesita.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
```

`app/Support/RememberMe.php` (split-token con rotación — leer los comentarios, SON el diseño):

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RememberToken;
use App\Models\User;

/**
 * Remember-me con split-token. ESTE es el archivo security-critical del login:
 * cualquier cambio acá merece relectura y sus tests (AuthTest) en verde.
 *
 * - Cookie `selector:validator`. En base: selector en claro (lookup) y SOLO
 *   EL HASH del validator (bcrypt, como un password). Jamás el token en claro.
 * - Rotación en cada uso: el token presentado muere y nace otro. Achica la
 *   ventana de replay de un token copiado.
 * - Vencimiento normal: se borra esa fila y la cookie, sin alarma.
 * - Selector conocido + validator que NO matchea: posible robo → se queman
 *   TODOS los tokens del usuario y se borra la cookie.
 * - La cookie es HttpOnly + SameSite=Lax, igual que la de sesión. `Secure`
 *   se activa con SESSION_SECURE=true (HTTPS real): ver cap. 19.
 */
final class RememberMe
{
    public const COOKIE = 'remember';

    public const TTL = 2592000; // 30 días, en segundos

    /**
     * Crea un token para el usuario y devuelve el par crudo "selector:validator"
     * para la cookie. El validator sale de acá una sola vez: no se loguea.
     */
    public static function create(int $userId): string
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        RememberToken::create([
            'selector'         => $selector,
            'user_id'          => $userId,
            'hashed_validator' => password_hash($validator, PASSWORD_DEFAULT),
            'expires_at'       => time() + self::TTL,
        ]);

        return $selector . ':' . $validator;
    }

    /** Header Set-Cookie completo para el par, o para borrarlo con $clear. */
    public static function cookieHeader(string $pair = '', bool $clear = false): string
    {
        $secure = filter_var($_ENV['SESSION_SECURE'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? '; Secure'
            : '';

        if ($clear) {
            return self::COOKIE . '=; Path=/' . $secure . '; HttpOnly; SameSite=Lax; Max-Age=0';
        }

        return self::COOKIE . '=' . $pair . '; Path=/' . $secure
            . '; HttpOnly; SameSite=Lax; Max-Age=' . self::TTL;
    }

    /**
     * Intenta sesión desde la cookie. Devuelve el header Set-Cookie a aplicar
     * en la respuesta de salida (rotación o limpieza), o null si no hay nada
     * que hacer. Nunca lanza: un token roto simplemente no loguea.
     */
    public static function resumeFromCookie(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if ($raw === '' || !str_contains($raw, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $raw, 2);

        $row = RememberToken::query()->where('selector', $selector)->first();
        if ($row === null) {
            return null; // selector desconocido: nada que quemar, solo ignorar
        }

        $user = User::find($row->user_id);
        if ($user === null) {
            $row->delete();
            return self::cookieHeader(clear: true);
        }

        if ((int) $row->expires_at <= time()) {
            $row->delete(); // vencimiento normal: sin alarma, solo limpieza
            return self::cookieHeader(clear: true);
        }

        if (!password_verify($validator, $row->hashed_validator)) {
            // Posible robo: el selector existe pero el secreto no matchea.
            RememberToken::query()->where('user_id', $user->id)->delete();
            return self::cookieHeader(clear: true);
        }

        $row->delete();
        Auth::login($user->id);

        return self::cookieHeader(self::create($user->id));
    }

    public static function clearUser(int $userId): void
    {
        RememberToken::query()->where('user_id', $userId)->delete();
    }
}
```

`app/Support/CsrfTokens.php` (las 4 vars de `_csrf.twig`, una sola fuente para controladores y closures de ruta):

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;

/**
 * Las 4 vars que espera partials/_csrf.twig. Una sola fuente para
 * controladores (vía Controller::csrf) y closures de ruta (p. ej. la home,
 * que renderiza directo y también necesita el form de logout del layout).
 */
final class CsrfTokens
{
    public static function fields(Request $request, Guard $guard): array
    {
        $nameKey  = $guard->getTokenNameKey();
        $valueKey = $guard->getTokenValueKey();

        return [
            'csrf_name_key'  => $nameKey,
            'csrf_name'      => $request->getAttribute($nameKey),
            'csrf_value_key' => $valueKey,
            'csrf_value'     => $request->getAttribute($valueKey),
        ];
    }
}
```

> **Advertencia honesta sobre remember-me en red local:** en `127.0.0.1` es seguro; en un wifi abierto sin TLS, un token de 30 días está *más* expuesto que una cookie de sesión (ventana más larga para olfatearlo). Si la app va a vivir en red local, primero el túnel/TLS del cap. 19, después remember-me.

## 9. Rutas, controladores y auth

`routes/web.php` completo. Novedades respecto al CRUD pelado: rutas de auth (login/logout públicas, `/tareas` protegido por grupo) y la home que pasa `user_*` + CSRF porque el layout los necesita en todo render:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\RequireAuthMiddleware;
use App\Support\Auth;
use App\Support\CsrfTokens;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

return function (App $app) {
    // Ruta raiz: pagina de presentacion del stack, con status 200. Sin ella
    // GET / devuelve 404 y el boton "Volver al inicio" de errors/error.twig
    // vuelve a ser un link muerto. Pública a propósito (lo pide /tareas, no ella).
    // El layout necesita csrf (form de logout) y usuario: como acá se renderiza
    // directo sin Controller, van explícitos con la misma fuente (CsrfTokens).
    $app->get('/', function ($request, $response) use ($app) {
        $container = $app->getContainer();

        // Guard se resuelve acá (en request), no al registrar: al construirse
        // exige sesión iniciada y en registro todavía no hay ninguna.
        return $container->get(Twig::class)->render($response, 'home.twig', [
            'active'     => 'home',
            'user_id'    => Auth::id(),
            'user_email' => Auth::user()?->email,
            ...CsrfTokens::fields($request, $container->get(Guard::class)),
        ]);
    });

    $app->get('/login', [AuthController::class, 'show']);
    $app->post('/login', [AuthController::class, 'store']);
    $app->post('/logout', [AuthController::class, 'destroy']);

    $app->group('/tareas', function ($group) {
        $group->get('', [TaskController::class, 'index']);
        $group->post('', [TaskController::class, 'store']);
        $group->get('/{id}/edit', [TaskController::class, 'edit']);
        $group->post('/{id}/toggle', [TaskController::class, 'toggle']);
        $group->put('/{id}', [TaskController::class, 'update']);
        $group->delete('/{id}', [TaskController::class, 'destroy']);
    })->add(RequireAuthMiddleware::class);
};
```

El `->add()` al grupo protege las 5 rutas de un saque; `/`, `/login` y `/logout` quedan públicas. El logout es POST a propósito (con CSRF del Guard global): un logout por GET se dispara con un link externo sin que quieras.

**Por qué la raíz renderiza una página y no una redirección.** `errors/error.twig` (cap. 7.1) cierra con `<a href="/">Volver al inicio</a>`. Sin una ruta en `/`, ese botón es un link muerto: se llega a un 404, se pulsa "volver al inicio" y se recibe **el mismo 404**. La ruta responde `200` con `home.twig` y no redirige. De paso es el health check más barato que existe y el test de humo más económico del proyecto (cap. 20.6).

**`active` le dice al layout qué item del menú resaltar.** El shell (`layouts/app.twig`, cap. 15) pinta `menu-active` según esa variable. Solo la página completa la necesita — los parciales HTMX no llevan nav, así que las demás acciones ni la pasan.

### 9.1 Controlador base reutilizable

Antes de `TaskController`, una decisión: el payload de respuesta (flash + CSRF) y el `render()` se iban a repetir en cada controlador futuro. En Laravel no se nota porque el framework te da `view()`, `View::share()` y view composers; acá esa maquinaria no existe, así que va la versión mínima: una clase base abstracta con hook.

`app/Http/Controllers/Controller.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Auth;
use App\Support\CsrfTokens;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

/**
 * Controlador base: toda respuesta HTML sale con los datos comunes (flash
 * consumido + tokens CSRF). Cada controlador suma lo suyo por dos vías:
 * viewDefaults() para lo que repite en todas sus acciones, $extra para lo
 * puntual de una acción. Nada de payloads copiados entre controladores.
 */
abstract class Controller
{
    /**
     * Orden en que se muestran los avisos cuando una acción deja más de uno
     * (ej.: falla la validación y además había un info pendiente).
     */
    private const FLASH_ORDER = ['error', 'success', 'info'];

    public function __construct(protected Twig $view, protected Guard $guard) {}

    protected function render(Request $request, Response $response, string $template, array $extra = []): Response
    {
        // user_* en cada render (los parciales lo ignoran): una PK indexada
        // cuando hay sesión, nada cuando no la hay. Lo usa el bloque de
        // usuario/salir del layout.
        $user = Auth::user();

        // ÚNICO consumidor del flash. Antes cada controlador hacía además su
        // propio Flash::get('info') para pasarlo a la plantilla: el que llegaba
        // primero se llevaba el mensaje y el otro recibía null, así que el
        // aviso se perdía sin error. Si se suma un tipo nuevo, va acá.
        $flash = [];
        foreach (self::FLASH_ORDER as $type) {
            $flash[$type] = Flash::get($type);
        }

        // Un aviso, un canal, elegido por el request:
        // - htmx: el aviso viaja en `HX-Trigger-After-Swap` y el HTML sale sin
        //   el banner. Si viajara por los dos lados, se vería dos veces.
        // - página completa: banner inline (login, redirect de RequireAuth)
        //   porque ahí no hay evento de htmx que escuchar.
        if ($request->hasHeader('HX-Request')) {
            $response = $this->withToasts($response, $flash);
            $flash = ['error' => null, 'success' => null, 'info' => null];
        }

        return $this->view->render($response, $template, [
            'flash_error'   => $flash['error'],
            'flash_success' => $flash['success'],
            'flash_info'    => $flash['info'],
            'user_id'       => $user?->id,
            'user_email'    => $user?->email,
            ...$this->csrf($request),
            ...$this->viewDefaults(),
            ...$extra,
        ]);
    }

    /**
     * Emite los avisos de una acción htmx como evento `toasts`.
     *
     * - `After-Swap` y no `HX-Trigger` a secas: en un ARM de 32 bits el swap
     *   puede tardar y con el evento temprano el toast aparecería antes que la
     *   lista nueva.
     * - El payload es un OBJETO: htmx entrega `detail` tal cual solo cuando el
     *   valor del header es un objeto; un array lo envuelve en `{value: [...]}`
     *   (verificado en public/assets/htmx-2.0.11.min.js, handleTriggerHeader).
     *   Por eso el layout lee `$event.detail.items` y no `.value`.
     * - JSON_UNESCAPED_UNICODE: los mensajes van en castellano; escapados a
     *   `\u00xx` el header crece al doble sin ganar nada. El SUBSTITUTE es la
     *   red: un mensaje con bytes inválidos no puede tumbar la respuesta con un
     *   TypeError de withHeader(false).
     *
     * @param array<string, string|null> $flash
     */
    private function withToasts(Response $response, array $flash): Response
    {
        $items = [];
        foreach (self::FLASH_ORDER as $type) {
            if ($flash[$type] !== null && $flash[$type] !== '') {
                $items[] = ['message' => $flash[$type], 'type' => $type];
            }
        }

        if ($items === []) {
            return $response;
        }

        return $response->withHeader('HX-Trigger-After-Swap', (string) json_encode(
            ['toasts' => ['items' => $items]],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        ));
    }

    protected function viewDefaults(): array
    {
        return [];
    }

    protected function csrf(Request $request): array
    {
        return CsrfTokens::fields($request, $this->guard);
    }
}
```

El orden del merge es el contrato: flash + CSRF, después defaults del controlador, después `$extra` de la acción. Lo puntual siempre gana a lo general. Y php-di autowirea el constructor heredado sin configuración: el próximo controlador nace con `extends Controller` y listo.

`app/Http/Controllers/TaskController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\Flash;
use App\Support\Pagination;
use App\Support\Url;
use App\Support\Validator;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TaskController extends Controller
{
    /**
     * Slice paginado + metadata para TODA respuesta de esta ruta (página
     * completa o swap parcial: ambas regiones llevan su paginación al día).
     * La página y el tamaño salen del query string (?page=, ?per_page=), que
     * mandan tanto el navegador como los links HTMX del partial.
     */
    private function pageData(Request $request): array
    {
        $query = $request->getQueryParams();

        $listing = Pagination::paginate(
            Task::orderByDesc('id'),
            (int) ($query['page'] ?? 1),
            (int) ($query['per_page'] ?? Pagination::DEFAULT_PER_PAGE),
            // Con el prefijo ya aplicado: base_url es la fuente de la
            // paginación, del `hx-get` del select de tamaño y del HX-Push-Url
            // de más abajo, así que las tres heredan el prefijo sin saberlo.
            Url::path('/tareas')
        );

        return [
            'tasks'      => $listing['items'],
            'pagination' => $listing,
        ];
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->respond($request, $response);
    }

    /**
     * Toda acción de esta ruta responde por acá, y el template lo elige el
     * request, no la acción.
     *
     * Con `HX-Request` va sólo la región: "Cancelar" apunta a /tareas con
     * hx-target="#tareas-panel", y si htmx recibiera index.twig (que extiende
     * el layout) se quedaría con el <body> y lo insertaría dentro del panel:
     * dos <h1>, dos #alta-tarea y el padding del layout dos veces.
     *
     * Sin `HX-Request` va la página entera: un form con JS apagado, una URL
     * abierta a mano o un curl son un navegador pidiendo un documento. La
     * mutación ocurre igual, pero el fragmento llega sin <head>, sin hoja de
     * estilos y sin shell, así que una acción exitosa se ve rota (S9).
     *
     * `active => 'tareas'` lo lee el layout (menú y miga de pan) y lo ignoran
     * los parciales, así que puede viajar en las dos formas.
     */
    private function respond(Request $request, Response $response, array $extra = []): Response
    {
        // El orden del merge importa: `pageData()` primero, así un $extra no
        // puede pisar `tasks`/`pagination` (ni el header de abajo).
        $data = $this->pageData($request) + $extra + ['active' => 'tareas'];

        $response = $this->render(
            $request,
            $response,
            $request->hasHeader('HX-Request') ? 'tasks/_panel.twig' : 'tasks/index.twig',
            $data
        );

        if (!$request->hasHeader('HX-Request') || !isset($data['pagination'])) {
            return $response;
        }

        // La posición de la lista viaja en la URL (S11) desde el HEADER, no
        // desde un `hx-push-url="/tareas?page=2"` en la plantilla:
        // Pagination::paginate() clampea la página pedida al rango válido, así
        // que borrar la última fila de la página 2 hace que el server
        // renderice la 1. Una URL fija en el markup empujaría una posición que
        // contradice el contenido que acaba de llegar; sólo el server que
        // renderizó sabe qué página mostró. htmx 2.0.11 lee el header antes que
        // el atributo (verificado en el vendor: `HX-Push-Url` gana sobre
        // `hx-push-url`). Se empuja la URL canónica de la vista y no la de la
        // acción: /tareas/5/toggle sólo acepta POST y recargarla sería un 404.
        // Sin `HX-Request` no se emite: la página entera ya trae su propia URL.
        $pagination = $data['pagination'];

        return $response->withHeader('HX-Push-Url', sprintf(
            '%s?page=%d&per_page=%d',
            $pagination['base_url'],
            $pagination['current_page'],
            $pagination['per_page']
        ));
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
            Flash::set('success', 'Tarea creada.');
        }

        // Devolvemos el panel completo, no solo la lista: el flash vive en el
        // mismo swap, así se ve sin recargar.
        return $this->respond($request, $response);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $deleted = Task::destroy((int) $args['id']);

        // destroy() de un id inexistente no falla: devuelve 0. Sin este branch
        // el toast diría "eliminada" sobre una fila que nunca existió.
        Flash::set(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0 ? 'Tarea eliminada.' : 'La tarea no existe o ya fue eliminada.'
        );

        return $this->respond($request, $response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        // La edición es inline: el panel vuelve entero con una sola fila en
        // modo formulario. Sin `editing_id` la lista no sabe cuál editar.
        $task = Task::find((int) $args['id']);

        // Un id que no existe no responde 404: htmx metería la página de
        // error dentro de #tareas-panel. Devolvemos el panel con un flash,
        // que es donde la app ya comunica todo lo que el usuario tiene que
        // ver sin recargar. El flash NO se consume acá: lo levanta render(),
        // que decide si va inline o en el header de htmx.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');
        }

        return $this->respond($request, $response, [
            'editing_id' => $task?->id,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en edit(): sin fila no hay nada que validar ni
        // que guardar, y un 404 llegaría al panel como HTML ajeno.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->respond($request, $response, [
                'editing_id' => null,
            ]);
        }

        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
            // La fila sigue en modo formulario Y conserva lo tipeado: sin esto
            // el input se repinta con task.title (guardado) y el usuario pierde
            // lo que escribió justo cuando aparece el error.
            return $this->respond($request, $response, [
                'editing_id'    => $task->id,
                'editing_title' => $data['title'] ?? '',
            ]);
        }

        $task->update(['title' => $data['title']]);
        Flash::set('success', 'Tarea actualizada.');

        return $this->respond($request, $response, [
            'editing_id'    => null,
            'editing_title' => null,
        ]);
    }

    /**
     * Cambia el estado entre pendiente y completado, guardando la fecha
     * de finalización cuando se marca como completada y limpiándola cuando
     * se desactiva. El mismo panel vuelve entero con la lista actualizada.
     */
    public function toggle(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->respond($request, $response);
        }

        if ($task->status === 'completado') {
            $task->update(['status' => 'pendiente', 'completed_at' => null]);
            Flash::set('success', 'Tarea marcada como pendiente.');
        } else {
            $task->update(['status' => 'completado', 'completed_at' => Carbon::now()]);
            Flash::set('success', 'Tarea completada.');
        }

        return $this->respond($request, $response);
    }
}
```

Tres cosas que este controlador deja por escrito y que antes estaban implícitas:

1. **`index()` tiene dos respuestas y la elige el header `HX-Request`.** Sin el branch, el botón "Cancelar" mete un `<html>` completo dentro del panel.
2. **Un id que no existe responde 200 con un flash, no 404** (la página de error dentro del panel sería peor) **y nunca en silencio** (el `?->update()` mudo se fue).
3. **En fallo de validación se conserva lo tipeado** (`editing_title`), no el valor guardado. Twig autoescapea, así que comillas o `<script>` tipeados salen neutrales.

Y una deliberada: cada acción repite `return $this->render($request, $response, 'tasks/_panel.twig', ...)` con el template a la vista. Como en Laravel repetís `return view(...)`: cada acción declara su respuesta, se encuentra con un grep, cero magia. Un helper que lo esconda (`panel()`) o un default en la firma ahorran 20 caracteres a cambio de esconder información — mal negocio.

### 9.2 AuthController: login, logout y remember-me

Diseño v1 deliberadamente chico: login + logout + remember-me + usuario inicial por seed. **Sin registro público** (achica superficie: enumeración, spam de cuentas) y **sin reset por email** (en Termux no hay MTA; eso es otro proyecto con SMTP externo). Cero dependencias nuevas: `password_hash`/`password_verify` son núcleo PHP, 32-bit safe.

`app/Http/Controllers/AuthController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\RememberMe;
use App\Support\Throttle;
use App\Support\Url;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends Controller
{
    /**
     * Hash bcrypt valido de una clave aleatoria, con el mismo coste que usa
     * password_hash(PASSWORD_DEFAULT) en PHP >= 8.4. Cuando el email no existe
     * se verifica contra este hash en vez de saltarse password_verify: un hash
     * invalido (o de otro coste) tarda distinto y vuelve a enumerar cuentas
     * por timing. Debe tener 60 caracteres o bcrypt lo rechaza al instante.
     */
    public const DUMMY_HASH = '$2y$12$hmGwg9QWNfE1u1JP/s90ReQugq9oOeSa4YlxVNYaQFX3/eCtS84G2';

    /** Mismo mensaje para credenciales malas y para cuenta throttled. */
    private const INVALID_CREDENTIALS = 'Credenciales inválidas.';

    public function show(Request $request, Response $response): Response
    {
        if (Auth::check()) {
            return $response->withStatus(303)->withHeader('Location', Url::path('/tareas'));
        }

        return $this->render($request, $response, 'auth/login.twig', [
            'active' => 'login',
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $email = trim((string) ($data['email'] ?? ''));

        $validator = Validator::make($data, [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');

            return $this->render($request, $response, 'auth/login.twig', [
                'active' => 'login',
                'email'  => $email,
            ]);
        }

        // IP del cliente para el throttling. En CLI/tests puede no venir.
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'desconocida');

        // Antes de tocar la base de usuarios: si ya hay demasiados fallos, no
        // se revela nada (ni bloqueo ni existencia) y no se gasta bcrypt.
        if (Throttle::tooMany($email, $ip)) {
            Flash::set('error', self::INVALID_CREDENTIALS);

            return $this->render($request, $response, 'auth/login.twig', [
                'active' => 'login',
                'email'  => $email,
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        // Dummy bcrypt cuando el email no existe: así el tiempo de respuesta
        // no delata si la cuenta existe o no (enumeración por timing).
        $hash = $user === null ? self::DUMMY_HASH : $user->password_hash;
        $password = (string) ($data['password'] ?? '');

        if ($user === null || !password_verify($password, $hash)) {
            Throttle::hit($email, $ip);

            // Genérico a propósito: nunca decir si falló el email o la clave.
            Flash::set('error', self::INVALID_CREDENTIALS);

            return $this->render($request, $response, 'auth/login.twig', [
                'active' => 'login',
                'email'  => $email,
            ]);
        }

        // Login válido: se perdona el historial de fallos de este email + IP.
        Throttle::clear($email, $ip);

        // Rehash silencioso si cambió el algoritmo o el costo.
        if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
            $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }

        Auth::login($user->id);

        $response = $response->withStatus(303)->withHeader('Location', Url::path('/tareas'));

        if (!empty($data['remember'])) {
            $response = $response->withAddedHeader(
                'Set-Cookie',
                RememberMe::cookieHeader(RememberMe::create($user->id))
            );
        }

        return $response;
    }

    public function destroy(Request $request, Response $response): Response
    {
        $id = Auth::id();
        if ($id !== null) {
            RememberMe::clearUser($id);
        }

        Auth::logout();
        Flash::set('info', 'Sesión cerrada.');

        $response = $response->withStatus(303)->withHeader('Location', Url::path('/login'));

        return $response->withAddedHeader('Set-Cookie', RememberMe::cookieHeader(clear: true));
    }
}
```

El login es un form normal (sin HTMX): navega de verdad con 303. Cuatro detalles que importan y son fáciles de hacer mal:

1. **Mensaje genérico** (`Credenciales inválidas.`) + **dummy bcrypt** con email inexistente: ni el texto ni el tiempo delatan si la cuenta existe.
2. **`session_regenerate_id(true)`** al entrar y salir (vive en `Auth::login/logout`, cap. 8.1): fijación de sesión.
3. **Logout quema todo**: filas remember del usuario + cookie expirada, no solo la sesión.
4. **Rehash silencioso**: si PHP sube el costo default, los hashes viejos migran solos al próximo login.

## 10. Modelos Eloquent (+ User y RememberToken)

`app/Models/Task.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static Task|null find(int|string $id)
 * @method static Task create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Task> orderByDesc(string $column)
 */
class Task extends Model
{
    protected $fillable = ['title', 'status', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
```

`app/Models/User.php` (`password_hash` oculto en serializaciones por las dudas):

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static User|null find(int|string $id)
 */
class User extends Model
{
    protected $fillable = ['email', 'password_hash'];

    protected $hidden = ['password_hash'];
}
```

`app/Models/RememberToken.php` (sin id autoincrement: el selector ES la clave; sin timestamps de Eloquent: la tabla lleva solo `created_at`):

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $selector
 * @property int $user_id
 * @property string $hashed_validator
 * @property int $expires_at
 * @method static RememberToken create(array<string, mixed> $attributes = [])
 */
class RememberToken extends Model
{
    protected $table = 'remember_tokens';

    public $timestamps = false;

    protected $primaryKey = 'selector';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['selector', 'user_id', 'hashed_validator', 'expires_at'];
}
```

## 11. Migraciones con Phinx

`phinx.php` (en la raíz del proyecto). Ojo al `safeLoad()`: con `load()` un clon fresco sin `.env` revienta con "Unable to read any of the environment file(s)" antes de migrar:

```php
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
```

> **Divergencia honesta:** Phinx migra siempre `database/database.sqlite` fijo, pero la app usa `DB_DATABASE` si está seteado. Si tu `.env` apunta a otro lado, el `migrate` escribe en un archivo y la app lee otro. Para el flujo normal (sin `DB_DATABASE`, default relativo) ambos usan el mismo archivo y no hay problema.

Crear y correr una migración (SOLO si construís desde cero: si clonaste el repo, las 3 ya vienen — salteá directo al `migrate`. Crear de más acá es la causa N.º 1 del error "Duplicate migration" del cap. 21.23):

```bash
vendor/bin/phinx create CreateTasksTable
```

Completá el archivo generado en `database/migrations/` y renombralo a `001_create_tasks_table.php` (en 32 bits los timestamps de Phinx saturan el entero — regla en 11.1, vale desde la primera migración):

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

El equivalente a `migrate:fresh` de Laravel (con una sola migración, verificado ida y vuelta, y funciona igual en Phinx 0.13):

```bash
vendor/bin/phinx rollback -t 0   # baja todo a down
vendor/bin/phinx status          # verifica: down
vendor/bin/phinx migrate         # vuelve a up
```

### 11.1 Migraciones de auth y seed del admin

```bash
vendor/bin/phinx create CreateUsersTable
vendor/bin/phinx create CreateRememberTokensTable
```

### 11.1 Migraciones de auth y seed del admin

```bash
vendor/bin/phinx create CreateUsersTable
vendor/bin/phinx create CreateRememberTokensTable
```

> **Regla de 32 bits: renombrá SIEMPRE lo que genera `create`.** Phinx nombra `YYYYMMDDHHMMSS_algo.php` (14 dígitos, ~2×10¹³) y extrae la versión con `(int)` del prefijo (`Util.php:93`). En 64 bits entra; en 32 bits **satura a `PHP_INT_MAX` (2147483647)** con el warning `The float-string "2026..." is not representable as an int` — y TODAS tus migraciones colapsan a la misma versión: `Duplicate migration` garantizado aunque haya un solo archivo por timestamp. El patrón acepta cualquier dígito inicial (`/^\d+_.../`, sin validar fechas), así que usamos secuenciales de 3 dígitos (ordenan bien hasta 999):
>
> ```bash
> mv database/migrations/20260928185619_create_users_table.php \
>    database/migrations/002_create_users_table.php
> ```
>
> Nuestras tres migraciones son `001_create_tasks_table.php`, `002_create_users_table.php` y `003_create_remember_tokens_table.php`. Cada `create` futuro: renombrar al siguiente número. Sin excepciones en 32 bits.

`database/migrations/002_create_users_table.php`:

```php
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['limit' => 160])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addIndex(['email'], ['unique' => true])
            ->addTimestamps()
            ->create();
    }
}
```

`database/migrations/003_create_remember_tokens_table.php` (posterior al de users):

```php
<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRememberTokensTable extends AbstractMigration
{
    public function change(): void
    {
        // Sin id autoincrement: el selector ES la clave (lookup directo).
        // expires_at como entero unix: sin dramas de timezone al comparar.
        $this->table('remember_tokens', ['id' => false, 'primary_key' => ['selector']])
            ->addColumn('selector', 'string', ['limit' => 48])
            ->addColumn('user_id', 'integer')
            ->addColumn('hashed_validator', 'string', ['limit' => 255])
            ->addColumn('expires_at', 'integer')
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
```

Sin registro público, el primer usuario nace por **seed**: en Phinx, migraciones para SCHEMA y seeds para DATOS. Los seeds corren con `seed:run`, quedan registrados y se pueden repetir sin duplicar.

Primero declará el path en `phinx.php`, junto a `migrations` (fragmento):

```php excerpt
return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
```

```bash
vendor/bin/phinx seed:create AdminSeeder
```

Completá `database/seeds/AdminSeeder.php` (el secret sale de entorno real o de `.env` —ojo: Dotenv immutable NO usa `putenv`, así que `.env` llega a `$_ENV` pero nunca a `getenv()`: se miran ambos—; valida con el `Validator` del cap. 12 y nunca imprime la clave; Eloquent sale del bootstrap de la app, igual que en runtime):

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Validator;
use Phinx\Seed\AbstractSeed;

/**
 * Admin inicial. Los seeds son para DATOS (las migraciones son para SCHEMA):
 * corren con `seed:run`, quedan registrados y se pueden repetir sin duplicar.
 *
 * El secret sale de entorno real o de `.env` (ojo: Dotenv immutable NO usa
 * putenv, así que `.env` llega a $_ENV pero nunca a getenv: se miran ambos).
 * Nunca de argumentos (visibles en `ps`) ni hardcodeado (viaja en git).
 * Por eso el bootstrap se carga ANTES de leer (Dotenv puebla $_ENV ahí).
 */
final class AdminSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../bootstrap/app.php';

        // $_ENV primero (ahí cae `.env` vía Dotenv), getenv después (entorno
        // real). Con Dotenv immutable el real gana en ambos si está en los dos.
        $email = $_ENV['ADMIN_EMAIL'] ?? getenv('ADMIN_EMAIL') ?: null;
        $password = $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: null;

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required|min:8']
        );

        if ($email === null || $validator->fails()) {
            throw new \RuntimeException(
                'ADMIN_EMAIL y ADMIN_PASSWORD (8+ caracteres) son obligatorios: ' .
                'ADMIN_EMAIL=x ADMIN_PASSWORD=... vendor/bin/phinx seed:run -s AdminSeeder'
            );
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]
        );

        echo "OK admin {$user->email} (id {$user->id})" . PHP_EOL;
    }
}
```

Uso — vía `.env` (recomendado: descomentá `ADMIN_EMAIL`/`ADMIN_PASSWORD` en tu `.env`; no pasan por history ni `ps`):

```bash
vendor/bin/phinx seed:run -s AdminSeeder
# OK admin vos@ejemplo.com (id 1)
```

Orden obligatorio: **primero `composer migrate` (tablas), después el seed**. Sin tablas verás `no such table: users` — no es un bug del seed, es orden de ejecución. Y si el seed aborta con el mensaje de uso, te falta alguna de las dos variables (o la clave tiene menos de 8).

O inline (el entorno real gana si están ambos; ojo que en bash SÍ queda en el history):

```bash
ADMIN_EMAIL=vos@ejemplo.com ADMIN_PASSWORD=una-clave-larga vendor/bin/phinx seed:run -s AdminSeeder
```

### 11.2 DatabaseSeeder: sembrar otros datos

Phinx no trae el `$this->call()` de Laravel: `seed:run` pelado ejecuta todo el directorio en orden de `glob` (~alfabético, sin garantía contractual) y `-s` corre uno solo. El orden explícito vive entonces en un orquestador propio, y cada seeder DEBE ser idempotente (porque nada impide correrlos sueltos).

`database/seeds/DatabaseSeeder.php`:

```php
<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Orquestador estilo DatabaseSeeder de Laravel. Phinx no trae $this->call():
 * `seed:run` pelado ejecuta todo el directorio en orden de glob, así que el
 * orden explícito vive acá. Cada seeder DEBE ser idempotente (updateOrCreate
 * o guardas), porque nada impide correrlos sueltos con -s.
 *
 * Uso: vendor/bin/phinx seed:run -s DatabaseSeeder
 */
final class DatabaseSeeder extends AbstractSeed
{
    /** @var class-string[] en orden explícito de ejecución. */
    private const SEEDS = [
        AdminSeeder::class,
        TaskSeeder::class,
    ];

    public function run(): void
    {
        foreach (self::SEEDS as $class) {
            (new $class())->setAdapter($this->getAdapter())->run();
        }
    }
}
```

`database/seeds/TaskSeeder.php` (datos demo; el guard la hace idempotente):

```php
<?php

declare(strict_types=1);

use App\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * Datos demo para desarrollo. Idempotente: si ya hay tareas no toca nada,
 * así re-correr el DatabaseSeeder (o seed:run pelado) nunca duplica.
 */
final class TaskSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../bootstrap/app.php';

        if (Task::query()->exists()) {
            echo "TaskSeeder: ya hay tareas, no se toca nada." . PHP_EOL;
            return;
        }

        foreach (['Comprar pan', 'Regar las plantas', 'Terminar la guía'] as $title) {
            Task::create(['title' => $title]);
        }

        echo "TaskSeeder: 3 tareas demo." . PHP_EOL;
    }
}
```

```bash
ADMIN_EMAIL=... ADMIN_PASSWORD=... vendor/bin/phinx seed:run -s DatabaseSeeder
# OK admin ... (id 1)
# TaskSeeder: 3 tareas demo.
# All Done.
```

¿Otros datos mañana? Nuevo seeder con guard, una línea en `SEEDS`, listo. Regla: ningún seeder duplica en re-ejecución, nunca.

`database/seeds/DemoTasksSeeder.php` (100 filas para ejercitar la paginación del cap. 16.1 — a propósito FUERA de `DatabaseSeeder`: una instalación fresca no quiere 100 filas de ruido, se corre solo con `-s`):

```php
<?php

declare(strict_types=1);

use App\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * 100 tareas demo para ejercitar la paginación. A propósito FUERA del
 * DatabaseSeeder (una instalación fresca no quiere 100 filas de ruido):
 * se corre solo con -s DemoTasksSeeder. Idempotente por título.
 */
final class DemoTasksSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../bootstrap/app.php';

        $created = 0;
        for ($i = 1; $i <= 100; $i++) {
            if (Task::firstOrCreate(['title' => "Tarea demo {$i}"])->wasRecentlyCreated) {
                $created++;
            }
        }

        echo "DemoTasksSeeder: {$created} nuevas (100 esperadas en base vacía)." . PHP_EOL;
    }
}
```

```bash
vendor/bin/phinx seed:run -s DemoTasksSeeder
# DemoTasksSeeder: 100 nuevas (100 esperadas en base vacía).
```

> **Por qué el seed NO trae credenciales hardcodeadas** (ni `admin@admin.dev/123456` ni ninguna otra): una migración o un script con clave viaja en git, y una clave en git es una clave publicada — `123456` es literalmente la primera que prueban los bots contra cualquier login expuesto. Además nuestro propio `Validator` exige 8+ caracteres y la regla vale en todos lados, incluido acá. El flujo es: secret por entorno, validado, hasheado con bcrypt, nunca impreso. Para tu primer acceso en Termux usá el comando de arriba con una clave larga que solo vos sepas.

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

## 13. CSRF en formularios Twig

`resources/views/partials/_csrf.twig` (parcial reusable):

```twig
<input type="hidden" name="{{ csrf_name_key }}" value="{{ csrf_name }}">
<input type="hidden" name="{{ csrf_value_key }}" value="{{ csrf_value }}">
```

Se incluye en cualquier formulario con `{% include 'partials/_csrf.twig' %}`, siempre que el controlador le haya pasado esas cuatro variables a la vista (como hace `Controller::csrf()` arriba). Dos parciales hermanos viven al lado, documentados en el cap. 15: `_logo.twig` (marca) y `_icon.twig` (iconos compartidos sidebar/header).

`public/index.php`:

```php
<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
```

## 14. Frontend: assets vendorizados + SRI (nada de CDN en runtime)

Cambio grande respecto a la primera versión de esta guía: **la app no carga nada de ningún CDN**. Los 4 assets viven en `public/assets/` (que SÍ va a git) y el layout los sirve locales con `integrity`. Sin internet la app funciona igual; con internet, ningún publish de un mantenedor te cambia el runtime sin deploy.

> Por qué versiones pineadas con SRI, igual que antes: una URL flotante (`@4`, `@2`, `@3`) significa que el día que el mantenedor publique algo nuevo, tu app ejecuta código distinto sin que toques nada. `integrity` SHA-384 le dice al navegador: si un solo byte no coincide, **no lo ejecuta**.

### 14.1 Descargar y verificar (solo al bumpear versiones)

En un clon fresco NO hace falta: los assets ya vienen commiteados. Estos comandos son para cuando quieras actualizar una versión:

```bash
mkdir -p public/assets
curl -sL "https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3/dist/index.global.js" -o public/assets/tailwind-browser-4.3.3.js
curl -sL "https://unpkg.com/htmx.org@2.0.11/dist/htmx.min.js" -o public/assets/htmx-2.0.11.min.js
curl -sL "https://cdn.jsdelivr.net/npm/alpinejs@3.17.4/dist/cdn.min.js" -o public/assets/alpine-3.17.4.min.js
curl -sL "https://cdn.jsdelivr.net/npm/daisyui@5.7.46/daisyui.css" -o public/assets/daisyui-5.7.46.css
ls -la public/assets/
```

Verificá los hashes con PHP (a propósito sin `openssl`: en Termux PHP siempre está, el CLI de openssl no necesariamente):

```bash
php -r '$f=$argv[1]; echo "sha384-".base64_encode(hash_file("sha384",$f,true)),PHP_EOL;' public/assets/htmx-2.0.11.min.js
```

Tabla de versiones pineadas con sus SRI verificados (los bytes sirven idénticos desde el origen de cada asset):

| Asset | Versión | SRI |
|---|---|---|
| Tailwind browser | 4.3.3 | `sha384-2ql948lIdLcGEE0/qxNiudyTjgauA3RDJERu5xW75kFCvSl5a9odyQYCb6tEjnmB` |
| htmx | 2.0.11 | `sha384-2OatzQy1H+Zd/IIrjr1TcuDGqLXeHhbooAyJY1KdQMKnr4LZ22k31GBLdYKHmVjg` |
| Alpine.js | 3.17.4 | `sha384-5/joNqFnRyVWzXp99bHot6RHG+EksGp+USSgZwPar7T9SD9PKKER37n/8bXBAZGd` |
| daisyUI | 5.7.46 | `sha384-bbGkD3MAh/9AO9eBt/6ReKyGTu78VjNCrlo1uLqxHFOrFr8lRuS4H0sC04ucGill` |

El costo es que bumpear versiones es manual. Es intencional: actualizar el runtime es tu decisión, no la de un CDN. Y acordate del `.gitattributes`: sin `public/assets/* binary`, un checkout en Windows reescribe los `.js` a CRLF y el SRI falla en silencio (cap. 5).

### 14.2 daisyUI 5 as-is: reglas del juego

daisyUI entra como **CSS precompilado**, igual que cualquier asset: clases listas (`btn`, `card`, `menu`, `alert`, `drawer`...) + 35 temas. Tres reglas que no se negocian en este stack:

1. **Nada de utilities `dark:`**: el tema lo gobierna el atributo `data-theme` en `<html>` (`light`/`dark` por defecto, sin personalizar). Los colores semánticos (`bg-base-100`, `text-error`, `btn-primary`...) se adaptan solos.
2. **Nada de JS de terceros**: el comportamiento es Alpine o nada. El JS de otras librerías bindea en `DOMContentLoaded` y no ve el contenido que HTMX trae por swap — es el mismo motivo por el que el toast CSRF es Alpine (cap. 15).
3. **Nada de variantes `is-drawer-*`**: el ejemplo plegable de la doc de daisyUI usa `is-drawer-open:`/`is-drawer-close:`, y esas variantes **no existen en el CSS linkeado** (verificado: cero ocurrencias en `daisyui-5.7.46.css` — necesitan el compilador con daisyUI registrado como plugin). Con CSS linkeado serían clases muertas que *parecen* funcionar. El collapse de esta guía va con Alpine + utilities estándar, que el browser build sí compila al vuelo.

Como referencia, la CDN browser de Tailwind sigue siendo "solo desarrollo" según su propia doc — para tu app personal en tu teléfono ese trade-off es razonable. Si algún día querés CSS estático compilado sin Node, existe el CLI standalone de Tailwind, aunque probablemente necesite la capa glibc de Termux (`pkg install glibc-repo glibc`).

## 15. Shell de la app: sidebar, header, temas y footer

`resources/views/layouts/app.twig` completo. Es el archivo que más creció: shell con sidebar plegable (overlay en móvil, icon-rail en desktop), header con breadcrumb + toggle de tema, toast CSRF, contenido y footer:

```twig
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block title %}Mi App{% endblock %}</title>

    {# Favicon: el glifo de capas sobre el primario de daisyUI, en SVG plano
       (sin build ni referencias externas). El <link> evita el 404 de
       /favicon.ico que costaba un request fallido en cada visita. Va acá y no
       en /assets porque el archivo vive en la raíz de public: la URL dice
       dónde está. SRI no aplica a rel=icon, así que la disciplina es el peso
       (~600 bytes). #}
    <link rel="icon" type="image/svg+xml" href="{{ url('/favicon.svg') }}">

    {# Fija el tema ANTES del primer paint: sin esto, Alpine lo corregiría
       después y se vería un flash del tema claro en cada recarga oscura. #}
    <script>try{document.documentElement.dataset.theme=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light')}catch(e){document.documentElement.dataset.theme='light'}</script>
    {# Misma idea para el sidebar: si Alpine aplicara el plegado después del
       primer paint, con transition-all activo se vería abrir y cerrarse en
       cada recarga. Se deja la marca acá y el CSS de abajo pre-pinta. #}
    <script>try{if(localStorage.getItem('sidebarMini')==='1')document.documentElement.setAttribute('data-sidebar-mini','')}catch(e){}</script>

    {# daisyUI 5.7.46 vendorizado + SRI: offline, sin cambio de runtime sin deploy. #}
    <link rel="stylesheet" href="{{ url('/assets/daisyui-5.7.46.css') }}"
          integrity="sha384-bbGkD3MAh/9AO9eBt/6ReKyGTu78VjNCrlo1uLqxHFOrFr8lRuS4H0sC04ucGill">
    <script src="{{ url('/assets/tailwind-browser-4.3.3.js') }}"
            integrity="sha384-2ql948lIdLcGEE0/qxNiudyTjgauA3RDJERu5xW75kFCvSl5a9odyQYCb6tEjnmB"></script>
    <script src="{{ url('/assets/htmx-2.0.11.min.js') }}"
            integrity="sha384-2OatzQy1H+Zd/IIrjr1TcuDGqLXeHhbooAyJY1KdQMKnr4LZ22k31GBLdYKHmVjg"
            defer></script>
    <script defer src="{{ url('/assets/alpine-3.17.4.min.js') }}"
            integrity="sha384-5/joNqFnRyVWzXp99bHot6RHG+EksGp+USSgZwPar7T9SD9PKKER37n/8bXBAZGd"></script>

    <style>
        [x-cloak] { display: none !important; }
        {# Pre-pintado del sidebar plegado (ver script de arriba): sin esto el
           primer paint sale expandido y Alpine lo colapsa animado.
           Solo desktop (min-width lg): en móvil el sidebar es overlay y este
           estado no aplica. Los valores espejan las clases de Alpine (w-16,
           labels ocultos) para que al iniciar no haya ningún cambio que animar. #}
        @media (min-width: 1024px) {
            html[data-sidebar-mini] #sidebar { width: 4rem; }
            html[data-sidebar-mini] #sidebar .sidebar-head { justify-content: center; padding-left: .5rem; padding-right: .5rem; }
            html[data-sidebar-mini] #sidebar .sidebar-label { display: none; }
            html[data-sidebar-mini] #collapse-btn svg { transform: rotate(180deg); }
        }

        /* Tinta de los iconos de estado: el color semantico de daisyUI es un
           FONDO, no una tinta. Como color de icono sobre base-100 da 1.96:1 y
           2.87:1, bajo el 3:1 que pide un objeto grafico. Estos tres valores
           estan medidos contra los fondos reales de cada tema. */
        :root {
            --ink-success: oklch(53% .14 163.223);  /* 4.77:1 sobre base-100 claro */
            --ink-error:   oklch(55% .19 13.428);   /* 5.37:1 */
            --ink-pending: oklch(48% .006 285.885); /* 6.55:1 */
            --ink-info:    oklch(52% .12 233.807);  /* 5.31:1 */
        }
        /* En oscuro los semanticos brillantes ya pasan (8.06 y 5.52:1) y se
           reusan tal cual. No es cosmetico: los mismos ink claros sobre el
           fondo oscuro dan 3.59 y 2.95:1 y fallarian. */
        [data-theme="dark"] {
            --ink-success: var(--color-success);
            --ink-error:   var(--color-error);
            --ink-pending: color-mix(in oklab, var(--color-base-content) 60%, transparent);
            --ink-info:    var(--color-info);
        }
        .icon-success { color: var(--ink-success); }
        .icon-error   { color: var(--ink-error); }
        .icon-pending { color: var(--ink-pending); }

        /* `menu-active` de daisyUI usa `neutral`: en claro eso pinta la pagina
           activa como un bloque casi negro, mas pesado que cualquier otro
           elemento de la pantalla. Se cambia por un tinte suave del propio
           texto, que funciona igual en los dos temas y conserva el contraste
           (el color del texto sigue siendo base-content). */
        .menu .menu-active {
            background-color: color-mix(in oklab, var(--color-base-content) 12%, transparent);
            color: var(--color-base-content);
        }

        /* Salto al contenido (WCAG 2.4.1). Fuera de pantalla por posicion y no
           con `display`/`visibility`: un elemento con display:none no recibe el
           foco de la primera tabulacion, que es justo lo que este enlace tiene
           que hacer. `sr-only` de Tailwind haria lo mismo, pero depende de que
           el JIT lo compile; esto es determinista y no agrega una clase al
           guard. */
        .skip-link {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 60;          /* sobre header (z-20), sidebar (z-40) y avisos (z-50) */
        }
        .skip-link:focus {
            left: .5rem;
            top: .5rem;
            padding: .5rem 1rem;
            border-radius: .5rem;
            background: var(--color-primary);
            color: var(--color-primary-content);
        }

        /* ── Avisos flotantes ────────────────────────────────────────────
           Posición: abajo a la derecha del area de contenido. El host sigue
           replicando la geometria de la columna (en desktop el sidebar es
           `static`, asi que un `fixed` contra el viewport no cae donde uno
           espera) y el stack adentro repite el `max-w-6xl` y el padding de
           <main>: el borde derecho de cada toast queda sobre el del contenido.
           Anclado ABAJO y no arriba: pegado al titulo tapaba el formulario de
           alta y su boton, que es justo lo que el usuario esta por usar cuando
           llega el aviso.
           La pila crece hacia ARRIBA (`column-reverse`): el aviso nuevo entra
           encima del anterior y el bloque se expande hacia el titulo, nunca
           hacia el borde inferior de la pantalla. En móvil el mismo criterio,
           con 1rem de margen. */
        /* En móvil tambien tiene que librar el footer (h-12). */
        .toast-host {
            position: fixed;
            z-index: 50;          /* sobre header sticky (z-20) y sidebar (z-40) */
            inset-inline: 0;
            bottom: 3.5rem;       /* = footer h-12 + 8px */
            padding-inline: 1rem;
            pointer-events: none; /* el hueco alrededor no bloquea clicks */
        }
        .toast-stack {
            display: flex;
            /* El ultimo del DOM es el mas nuevo y queda arriba: la pila crece
               hacia el titulo y el aviso recien llegado es el primero que se
               lee. El ancla inferior la da `bottom` en el host. */
            flex-direction: column-reverse;
            align-items: flex-end;
            gap: .5rem;
            width: 100%;
            max-width: 72rem;     /* = max-w-6xl de <main> */
            margin-inline: auto;
        }
        @media (min-width: 1024px) {
            .toast-host {
                inset-inline-start: 16rem;                 /* sidebar expandido (w-64) */
                top: auto;
                bottom: 4rem;                              /* = footer h-12 + 16px */
                padding-inline: 0;
                transition: inset-inline-start 200ms;      /* sigue el plegado del sidebar */
            }
            html[data-sidebar-mini] .toast-host { inset-inline-start: 4rem; } /* plegado (w-16) */
            .toast-stack { padding-inline: 1.5rem; }       /* = lg:p-6 de <main> */
        }

        /* Tarjeta propia en vez de `.alert` de daisyUI: el alert fija radio,
           padding y sombra, y acá lo que se busca es acento lateral + barra de
           vida. El color sale de los tokens del tema activo, así que el
           claro/oscuro sigue sin una sola utility `dark:`. */
        .toast-card {
            /* El acento del aviso usa las mismas tintas que los iconos de fila.
               Con los semanticos crudos el icono del toast daba 1.75:1
               (success), 2.45:1 (error) y 2.10:1 (info) contra su propio
               fondo: bajo el 3:1 de un objeto grafico. Eran dos sistemas de
               tinta en el mismo archivo, y este era el que se habia quedado
               atras. Con las tintas: 3.89 / 4.26 / ~4.9:1. */
            --toast-accent: var(--ink-info);
            position: relative;
            overflow: hidden;                        /* recorta acento y barra */
            /* Borde completo acá y no con `border border-base-300` en el HTML:
               el CSS del browser build de Tailwind se inyecta después de este
               <style>, y su shorthand `border-width: 1px` pisaría el acento de
               3px (misma especificidad, más tarde en la cascada). */
            border: 1px solid var(--color-base-300);
            border-inline-start: 3px solid var(--toast-accent);
            pointer-events: auto;                    /* sí se puede usar el botón cerrar */
        }
        .toast-card[data-type="success"] { --toast-accent: var(--ink-success); }
        .toast-card[data-type="error"]   { --toast-accent: var(--ink-error); }
        .toast-icon {
            color: var(--toast-accent);
            background: color-mix(in oklab, var(--toast-accent) 15%, transparent);
        }

        /* La barra ES el temporizador: al terminar la animación se retira el
           toast (`@animationend`), sin setTimeout que pueda desincronizarse o
           congelarse con la pestaña en segundo plano. Si querés otra duración,
           se cambia este `5s` y nada más. `paused` al pasar el mouse: no te lo
           borres mientras lo estás leyendo. */
        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 2px;
            width: 100%;
            background: var(--toast-accent);
            transform-origin: left center;
            animation: toast-countdown 5s linear forwards;
        }
        .toast-card:hover .toast-progress { animation-play-state: paused; }
        @keyframes toast-countdown {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }
        @keyframes toast-fade {
            from { opacity: 1; }
            to   { opacity: 0; }
        }
        /* Con "reducir movimiento" la barra no se mueve, pero la animación
           sigue existiendo como fade: animationend es lo que retira el nodo,
           y apagarla del todo dejaría el toast pegado para siempre. */
        @media (prefers-reduced-motion: reduce) {
            .toast-progress { animation-name: toast-fade; }

            /* S10: el resto de las transiciones también se apaga. Es una regla
               por ELEMENTO y con `!important` porque los `x-transition` de
               Alpine se aplican como clases e inline SOBRE el nodo que se
               mueve: un selector normal perdería contra el inline, y apuntar
               a Alpine ("anulá las transiciones") no es algo que su API
               exponga. `transition` no toca `animation`, así que la barra del
               toast sigue corriendo como fade (regla de arriba). */
            #sidebar,        /* plegado (w-64/w-16) y entrada del overlay móvil */
            .toast-host,     /* acompaña el plegado del sidebar */
            .toast-card,     /* x-transition enter/leave del toast */
            .tooltip-estado { /* x-transition.opacity del tooltip de estado */
                transition: none !important;
            }
        }
    </style>
</head>
<body class="bg-base-200 text-base-content">
{# Primer elemento tabulable de la pagina. Sin esto, con teclado hay que atravesar
   hamburguesa, plegado, breadcrumb y toggle de tema en cada pagina antes de
   llegar al contenido. #}
<a href="#contenido" class="skip-link">Saltar al contenido</a>
{# Shell de la app: sidebar plegable + header + contenido + footer.
   El collapse lo gobierna Alpine con utilities estándar (w-16/w-64,
   translate-x, hidden): el browser build las compila al vuelo, incluso
   cuando Alpine las alterna. Deliberadamente NO se usa el drawer de
   daisyUI: su ejemplo plegable depende de las variantes is-drawer-open: /
   is-drawer-close:, que no existen en el CSS linkeado (verificado: cero
   ocurrencias en daisyui-5.7.46.css) y quedarían como clases muertas.
   El tema claro/oscuro lo gobierna data-theme: cero utilities dark:.
   `Escape` cierra el drawer SOLO por debajo de lg: en desktop el sidebar no
   es un overlay (`sidebarOpen` arranca en true) y apagarlo ahi lo dejaria con
   `inert` —enlaces y «Salir» muertos— por una tecla apretada para cerrar un
   tooltip o un modal. La guarda es la misma condicion con la que shell()
   inicializa el estado. #}
<div x-data="shell()" @keydown.escape.window="window.innerWidth < 1024 && (sidebarOpen = false)" class="flex min-h-screen">
    {# Backdrop solo móvil: en desktop el sidebar empuja el contenido. #}
    <div x-cloak x-show="sidebarOpen" @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

    {# Sidebar: overlay en móvil, columna plegable a iconos en desktop.
       `inert` (atributo, no una clase CSS) es lo que saca de verdad los
       enlaces del orden de tabulación: una clase con `visibility` u `opacity`
       los deja enfocables, y acá el sidebar fuera de pantalla sigue en el
       layout por la animación de translate-x. En desktop `sidebarOpen`
       arranca en true, así que ahí nunca queda inerte.
       En desktop es CROMO FIJO, no una columna que se estira con el
       documento: `lg:sticky lg:top-0 lg:h-dvh` la sujeta al borde superior y
       al alto de la ventana. Con `lg:static` y una lista larga, el bloque de
       usuario y «Salir» quedaban al final del scroll —fuera de la pantalla,
       había que bajar hasta el pie para salir— y el carril quedaba vacío a la
       vista. `lg:bottom-auto` neutraliza el inset inferior que fija
       `inset-y-0`, que en sticky sujetaría las dos puntas; en móvil sigue
       `fixed` + `inset-y-0` + translate, sin cambios. El ancho lo gobierna el
       plegado (w-64/w-16 y el pre-pintado del <style>), que no se toca. #}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-base-100 transition-all duration-200 lg:sticky lg:top-0 lg:bottom-auto lg:h-dvh lg:translate-x-0"
           :class="{ 'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen, 'lg:w-64': !sidebarMini, 'lg:w-16': sidebarMini }"
           x-bind:inert="!sidebarOpen"
           aria-label="Navegación principal">
        <div class="sidebar-head flex h-16 shrink-0 items-center gap-2 border-b border-base-300 px-4" :class="sidebarMini && 'lg:justify-center lg:px-2'">
            {% include 'partials/_logo.twig' %}
            <span class="sidebar-label font-bold" :class="sidebarMini && 'lg:hidden'">Mi App</span>
            {# Cerrar el drawer en móvil. Sin esto no había salida visible: la
               hamburguesa vive en el header (z-20) y el panel abierto la tapa
               (z-40), así que en un teléfono el único cierre era acertar el
               backdrop. `lg:hidden`: en desktop el sidebar no es un overlay y
               no hay nada que cerrar. `ml-auto` lo empuja al extremo sin
               tocar el logo ni la etiqueta, que el plegado pre-pintado ya
               oculta con `.sidebar-label`. #}
            <button type="button"
                    class="btn btn-square btn-ghost btn-sm ml-auto lg:hidden"
                    @click="sidebarOpen = false"
                    aria-label="Cerrar menú"
                    title="Cerrar menú">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        {# `overflow-y-auto` sobre el `grow`: con el sidebar de alto fijo, un
           menú largo scrollea DENTRO del panel en vez de empujar el bloque de
           usuario (y «Salir») fuera de la pantalla. Como el <ul> pasa a ser un
           contenedor de scroll, su min-height automático vale 0: no hace falta
           un `min-h-0` al lado. #}
        <ul class="menu w-full grow gap-1 overflow-y-auto p-2">
            <li>
                <a href="{{ url('/') }}" class="{% if active is defined and active == 'home' %}menu-active{% endif %}"
                   title="Inicio"
                   aria-label="Inicio">
                    {% include 'partials/_icon.twig' with { name: 'home' } %}
                    <span class="sidebar-label" :class="sidebarMini && 'lg:hidden'">Inicio</span>
                </a>
            </li>
            <li>
                <a href="{{ url('/tareas') }}" class="{% if active is defined and active == 'tareas' %}menu-active{% endif %}"
                   title="Tareas"
                   aria-label="Tareas">
                    {% include 'partials/_icon.twig' with { name: 'tasks' } %}
                    <span class="sidebar-label" :class="sidebarMini && 'lg:hidden'">Tareas</span>
                </a>
            </li>
        </ul>
        {% if user_id is defined and user_id %}
        <div class="border-t border-base-300 p-2">
            {# Fila de usuario: inicial en un disco, email y la salida como icono.
               Antes el email flotaba suelto arriba y "Salir" era un boton
               fantasma de ancho completo: dos pesos distintos para la misma
               zona, y ninguno de los dos se leia como una unidad.
               `sidebar-label` en el disco y en el email hace que el plegado
               pre-pintado del CSS los oculte igual que las etiquetas del menu:
               plegado queda solo el boton, centrado. #}
            <div class="flex items-center gap-2 rounded-box bg-base-200 p-1.5"
                 :class="sidebarMini && 'lg:justify-center lg:bg-transparent lg:p-0'">
                <span class="sidebar-label grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary text-xs font-bold uppercase text-primary-content"
                      :class="sidebarMini && 'lg:hidden'" aria-hidden="true">{{ user_email|first }}</span>
                <span class="sidebar-label min-w-0 flex-1 truncate text-xs"
                      :class="sidebarMini && 'lg:hidden'" title="{{ user_email }}">{{ user_email }}</span>
                <form method="post" action="{{ url('/logout') }}" class="shrink-0">
                    {% include 'partials/_csrf.twig' %}
                    <button type="submit" class="btn btn-ghost btn-sm btn-square" title="Cerrar sesión" aria-label="Cerrar sesión">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M18.36 6.64a9 9 0 1 1-12.73 0"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                    </button>
                </form>
            </div>
        </div>
        {% endif %}
    </aside>

    {# Columna de contenido. #}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="navbar sticky top-0 z-20 border-b border-base-300 bg-base-100">
            {# Abrir/cerrar en móvil. #}
            <button class="btn btn-square btn-ghost lg:hidden" @click="sidebarOpen = !sidebarOpen" :aria-label="sidebarOpen ? 'Cerrar menú' : 'Abrir menú'">
                <svg x-show="!sidebarOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                <svg x-show="sidebarOpen" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            {# Plegar a iconos en desktop. #}
            {# Un solo glyph: la rotación sale del CSS pre-pintado (data-sidebar-mini),
               NO de una clase Alpine. rotate-180 de Tailwind v4 usa la propiedad
               `rotate`, que SE SUMA al `transform` del fallback (180+180=360):
               con ambos, el icono nunca cambiaba. Una sola fuente de verdad. #}
            <button id="collapse-btn" class="btn btn-square btn-ghost hidden lg:inline-flex" @click="toggleMini()" :aria-label="sidebarMini ? 'Desplegar menú' : 'Plegar menú'">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/></svg>
            </button>
            {# Breadcrumb con el texto del menú: en Tareas, Inicio (link) / Tareas;
               en Inicio, solo icono + texto. El logo vive en la sidebar. #}
            <span class="flex flex-1 items-center gap-2 px-2 font-semibold">
                {% if active is defined and active == 'tareas' %}
                    {% include 'partials/_icon.twig' with { name: 'tasks' } %}
                    <nav class="breadcrumbs" aria-label="Miga de pan">
                        <ul>
                            <li><a href="{{ url('/') }}">Inicio</a></li>
                            <li aria-current="page">Tareas</li>
                        </ul>
                    </nav>
                {% elseif active is defined and active == 'login' %}
                    <span>Acceder</span>
                {% else %}
                    {% include 'partials/_icon.twig' with { name: 'home' } %}
                    <span>Inicio</span>
                {% endif %}
            </span>
            {# Toggle claro/oscuro: swap de daisyUI + estado Alpine persistido. #}
            <label class="swap swap-rotate btn btn-square btn-ghost">
                <input type="checkbox" :checked="theme === 'dark'" @change="theme = $event.target.checked ? 'dark' : 'light'" aria-label="Cambiar entre tema claro y oscuro" />
                <svg class="swap-off h-6 w-6 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05A1,1,0,0,0,5,8.71l.71-.71A1,1,0,0,0,4.29,6.64Zm12,.71a1,1,0,0,0,1.41,0l.71-.71A1,1,0,1,0,18.36,5.64l-.71.71A1,1,0,0,0,17.66,7.76ZM19,11H20a1,1,0,0,0,0,2H19A1,1,0,0,0,19,11Zm-7,7a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V19A1,1,0,0,0,12,18Zm7.36-12.36a1,1,0,0,0,0,1.41l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,19.36,5.64ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>
                <svg class="swap-on h-6 w-6 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73,8.15,8.15,0,0,1-8.14-8.1,8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>
            </label>
        </header>

        {# Avisos flotantes. Posición y estilos en el <style> del head: el
           host replica la geometría de <main> para caer alineado con el <h1>.
           Un solo componente, dos canales:
           - @toasts.window: evento `toasts` que emite HX-Trigger-After-Swap
             desde Controller::render() en cada acción htmx (éxito, error de
             validación, tarea inexistente). detail.items es un ARRAY porque
             el payload del header es un objeto; un array suelto lo envolvería
             htmx en {value: [...]}.
           - @csrf.window: HX-Trigger del failureHandler de slim/csrf. Ese caso
             NO puede usar el canal de arriba: el request se corta con 400 y
             htmx no hace swap, así que no hay After-Swap. Se lee
             $event.detail.value porque un string se envuelve en {value}:
             con $event.detail el aviso decía [object Object] (hay test).
           Los items viven en un array de Alpine, NO en el DOM del panel: el
           swap de #tareas-panel no los borra ni los duplica. #}
        <div class="toast-host"
             x-data="toastStack()"
             @toasts.window="addAll($event.detail.items)"
             @csrf.window="add({ message: String($event.detail.value ?? ''), type: 'error' })"
             role="status"
             aria-live="polite">
            <div class="toast-stack">
                <template x-for="t in items" :key="t.id">
                    {# `bg-base-100/90` y no `/95`: el bundle precompila los pasos
                       de opacidad de `bg-*` solo en decenas, así que `/95` no
                       existía y la tarjeta quedaba sin fondo: el texto flotaba
                       sobre la página. El JIT del navegador tampoco puede
                       generarlo, porque `base-100` es un token de daisyUI y no
                       está en la paleta que conoce. #}
                    <div class="toast-card flex w-full max-w-sm items-start gap-3 rounded-xl bg-base-100/90 p-3 pr-2 shadow-lg backdrop-blur"
                         :data-type="t.type"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-x-4"
                         x-transition:enter-end="opacity-100 translate-x-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-x-0"
                         x-transition:leave-end="opacity-0 translate-x-4">
                        <span class="toast-icon grid h-7 w-7 shrink-0 place-items-center rounded-lg">
                            <svg x-show="t.type === 'success'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                            <svg x-show="t.type === 'error'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <svg x-show="t.type === 'info'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><line x1="12" y1="11" x2="12" y2="16"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        </span>
                        <p class="min-w-0 flex-1 pt-0.5 text-sm font-semibold leading-snug" x-text="t.message"></p>
                        <button type="button"
                                class="btn btn-square btn-ghost btn-xs shrink-0 opacity-50 hover:opacity-100"
                                @click="remove(t.id)"
                                aria-label="Cerrar aviso"
                                title="Cerrar aviso">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                        <span class="toast-progress" aria-hidden="true" @animationend="remove(t.id)"></span>
                    </div>
                </template>
            </div>
        </div>

        <main id="contenido" class="mx-auto w-full max-w-6xl flex-1 p-4 lg:p-6">
            {# El flash vive en tasks/_panel.twig, que es la región que HTMX
               reemplaza en los swaps parciales: acá no se renderiza. #}
            {% block content %}{% endblock %}
        </main>

        {# El stack vive aca y no en la sidebar: el pie de la columna de
           contenido es el lugar estable para esa linea (no cambia con el
           plegado) y la marca ya esta en la cabecera del sidebar. Una sola
           copia del dato, en un lugar que no depende del estado del menu. #}
        <footer class="footer footer-center h-12 border-t border-base-300 bg-base-100 p-0 text-xs opacity-80">
            <aside>Slim 4 · HTMX · Alpine · daisyUI</aside>
        </footer>
    </div>
</div>

<script>
// R1 del sidebar: Alpine entra con `defer`, así que entre el parseo y su
// arranque el sidebar fuera de pantalla de móvil todavía se alcanza con Tab.
// `x-bind:inert` no puede tapar esa ventana: corre exactamente cuando Alpine
// inicializa, que es lo que llega tarde. Se aplica acá, durante el parseo
// (antes del script diferido), el mismo valor inicial que calcula shell():
// sidebarOpen = window.innerWidth >= 1024. Alpine lo mantiene después.
// Sólo móvil: en desktop el sidebar es una columna visible y `inert` la
// dejaría muerta (sin foco ni clicks) hasta que Alpine la corrigiera.
if (window.innerWidth < 1024) {
    const el = document.getElementById('sidebar');
    if (el) {
        el.setAttribute('inert', '');
    }
}

function shell() {
    return {
        theme: document.documentElement.dataset.theme || 'light',
        sidebarOpen: window.innerWidth >= 1024,
        sidebarMini: document.documentElement.hasAttribute('data-sidebar-mini'),
        init() {
            this.$watch('theme', v => {
                document.documentElement.dataset.theme = v;
                localStorage.setItem('theme', v);
            });
            this.$watch('sidebarMini', v => {
                localStorage.setItem('sidebarMini', v ? '1' : '0');
                v ? document.documentElement.setAttribute('data-sidebar-mini', '')
                  : document.documentElement.removeAttribute('data-sidebar-mini');
            });
        },
        toggleMini() { this.sidebarMini = !this.sidebarMini; }
    };
}

/**
 * Pila de avisos flotantes. Vive fuera de #tareas-panel (en el layout), así
 * que sobrevive a los swaps de htmx; el estado es un array de Alpine, no DOM.
 * La vida de cada toast la lleva la barra de progreso (CSS + animationend),
 * no un setTimeout: ver `.toast-progress` en el <style> del layout.
 */
function toastStack() {
    return {
        items: [],
        seq: 0,
        // Tope de 3: en un teléfono, cinco acciones seguidas no pueden tapar
        // media pantalla. Los más viejos salen primero.
        max: 3,
        add(item) {
            if (!item || !item.message) return;
            this.items.push({ id: ++this.seq, message: item.message, type: item.type || 'info' });
            if (this.items.length > this.max) {
                this.items.splice(0, this.items.length - this.max);
            }
        },
        addAll(list) {
            (Array.isArray(list) ? list : []).forEach(i => this.add(i));
        },
        remove(id) {
            this.items = this.items.filter(i => i.id !== id);
        }
    };
}

/**
 * Foco después de un swap (S4). Toda acción de fila reemplaza #tareas-panel
 * entero (`hx-swap="outerHTML"`), así que el nodo que tenía el foco se va con
 * el swap y `document.activeElement` cae en <body>: la Tab siguiente arranca
 * en el sidebar y después de cada toggle, edición o borrado hay que recorrer
 * la página completa otra vez.
 *
 * Se mantiene el swap del panel (decisión D2(a)) y el foco se devuelve a mano:
 * 1. El control que dispara lleva `data-focus-key` (ver tasks/_list.twig) y lo
 *    publica en `clavePendiente` durante htmx:beforeRequest, ANTES del request.
 * 2. En htmx:afterSettle se busca esa misma clave en el DOM recién
 *    intercambiado y se enfoca: es el mismo control, en la fila nueva.
 * 3. Si la clave no existe en el DOM nuevo (la fila se borró, o guardar y
 *    cancelar salieron del modo edición y esos botones ya no están), el foco
 *    va al panel, que tiene `tabindex="-1"`. Sigue en la zona de la fila, no
 *    en el shell, que es lo que pide S4.
 * 4. Sin `data-focus-key` no se toca el foco: paginar o cambiar el tamaño de
 *    página es navegación, y llevarse el foco ahí sería robarlo.
 */
function restaurarFocoTrasSwap() {
    // Una sola clave viva: la intención dura lo que dura el request.
    let clavePendiente = null;

    document.body.addEventListener('htmx:beforeRequest', (evento) => {
        const elt = evento.detail && evento.detail.elt;
        // Se ASIGNA siempre, no se conserva el valor anterior cuando no hay
        // clave: un request que no llega a hacer swap (un 400 de CSRF, un 5xx)
        // dejaría su clave viva y el request siguiente la gastaría en el lugar
        // equivocado. `dataset` no existe si htmx dispara sobre un target que
        // no es un elemento (puede pasar), así que se lee con guarda.
        clavePendiente = (elt && elt.dataset && elt.dataset.focusKey) || null;
    });

    document.body.addEventListener('htmx:afterSettle', () => {
        if (clavePendiente === null) {
            return;
        }
        const clave = clavePendiente;
        clavePendiente = null;

        const nuevo = document.querySelector(selectorPorClave(clave));
        const destino = nuevo || document.getElementById('tareas-panel');
        if (!destino) {
            return;
        }

        // `focus()` sin opciones a propósito: `preventScroll: true` dejaría el
        // foco en un nodo fuera de pantalla justo cuando el swap movió el
        // layout, y el siguiente Tab saldría de un lugar que el usuario no ve.
        // El default sólo scrollea si el destino no está a la vista.
        destino.focus();
    });

    /**
     * La clave la arma Twig con el id de la tarea (un entero), pero el selector
     * no depende de eso: `CSS.escape` neutraliza comillas o backslashes si
     * alguna vez la clave deja de ser numérica, y el fallback cubre los
     * navegadores que no exponen `CSS.escape`.
     */
    function selectorPorClave(clave) {
        const escapada = (window.CSS && typeof window.CSS.escape === 'function')
            ? window.CSS.escape(clave)
            : String(clave).replace(/["\\]/g, '\\$&');
        return '[data-focus-key="' + escapada + '"]';
    }
}

// Los listeners van en document.body y no en el panel: el panel se reemplaza en
// cada swap, el body no. Se enganchan acá, al final del body, cuando ya existe.
restaurarFocoTrasSwap();

/**
 * Un fallo de red o del servidor no puede quedar en silencio (S8). htmx NO
 * intercambia respuestas de error: el panel queda como estaba y, sin aviso, el
 * usuario ve una acción que "no hizo nada" y la repite.
 *
 * - `htmx:responseError`: el servidor contestó 4xx/5xx.
 * - `htmx:sendError`: no hubo respuesta (sin red, DNS, servidor caído).
 *
 * Se usa el MISMO evento `toasts` que ya emite Controller::render() para las
 * acciones htmx exitosas: un solo componente de avisos, y este script no toca
 * el estado de Alpine (`.toast-host` es su dueño, no un consumidor más).
 */
function avisarFallosDeHtmx() {
    const avisar = (mensaje) => window.dispatchEvent(new CustomEvent('toasts', {
        detail: { items: [{ message: mensaje, type: 'error' }] }
    }));

    document.body.addEventListener('htmx:responseError', (evento) => {
        const estado = evento.detail && evento.detail.xhr ? evento.detail.xhr.status : 0;

        // 400/419 ya tienen su aviso: el failureHandler de slim/csrf responde
        // 400 con `HX-Trigger` y ese evento `csrf` lo muestra este mismo stack.
        // Avisar también acá daría DOS toasts del mismo fallo, y el de CSRF
        // dice más ("Tu sesión expiró") porque sabe por qué falló.
        if (estado === 400 || estado === 419) {
            return;
        }

        avisar('No se pudo completar la acción. Probá de nuevo.');
    });

    document.body.addEventListener('htmx:sendError', () => {
        avisar('No hay conexión con el servidor.');
    });
}

avisarFallosDeHtmx();
</script>
</body>
</html>
```

> **Por qué `.value` y por qué `.items`.** `handleTriggerHeader()` de htmx 2.0.11 no entrega el payload tal cual salvo que sea un **objeto**: si el valor del header no es un objeto, lo envuelve en `{ value: ... }` y `triggerEvent()` agrega además `detail.elt`. Medido en `public/assets/htmx-2.0.11.min.js` (`function M(e){return t(e,"Object")}`). De ahí las dos formas del layout:
>
> | Payload del header | Lo que llega a `$event.detail` | Se lee |
> |---|---|---|
> | `{"csrf":"Token inválido"}` (string) | `{ value: "Token inválido" }` | `$event.detail.value` |
> | `{"toasts":{"items":[…]}}` (objeto) | `{ items: […] }` | `$event.detail.items` |
>
> Convertir el detail completo a string imprime `[object Object]` en el aviso. Y un array suelto (`"toasts":[…]`) también se envolvería en `{value: …}`: por eso el payload es un objeto con `items` adentro.
>
> **Por qué `After-Swap` y no `HX-Trigger` a secas.** En un ARM de 32 bits el swap del panel puede tardar; con el evento temprano el toast de "Tarea creada" aparece mientras la lista vieja sigue en pantalla. `HX-Trigger-After-Swap` lo dispara cuando el DOM ya quedó actualizado.
>
> **Un aviso, un canal.** `Controller::render()` decide: si el request trae `HX-Request`, el flash viaja en el header y el HTML sale sin banner; si no, queda inline en `tasks/_panel.twig` (o en `auth/login.twig`), que es el caso de una navegación o de un formulario sin htmx. Si viajara por los dos lados, el mensaje se vería dos veces.
>
> **Alineación: el toast se cuelga de la columna de contenido, no del viewport.** Un `toast toast-end toast-top` de daisyUI es `position: fixed` contra la ventana, y en desktop lo que se ve es la columna que arranca **después** del sidebar: el toast quedaba pegado al borde de la pantalla, no al del `<h1>`. El host de arriba se corre `16rem` (o `4rem` si el sidebar está plegado — de ahí el selector `html[data-sidebar-mini]`, la misma marca que usa el pre-pintado) y el stack repite `max-w-6xl` + `p-4 lg:p-6` de `<main>`. Resultado: el borde derecho del toast cae exacto sobre el del título, y usa el hueco que la fila de título deja libre a la derecha. En móvil no hay hueco: el host va abajo, como cualquier toast de teléfono, para no tapar el `<h1>`.
>
> **La barra de vida es el timer.** `animationend` retira el toast, así que la barra que se ve es exactamente el tiempo que le queda. Con un `setTimeout` paralelo hay dos fuentes de verdad: la pestaña en segundo plano congela el timer (o no) y la barra sigue animando, y el mensaje se va con la barra a medio llenar. Un solo lugar (`5s` en `.toast-progress`) para cambiarlo.

### 15.1 Parciales de marca: logo e iconos

Para no duplicar SVGs entre sidebar y header (el mismo icono tiene que ser el mismo archivo, no dos copias que divergen):

`resources/views/partials/_logo.twig` (marca genérica sobre `primary`: se adapta a claro/oscuro por tokens, no por colores fijos):

```twig
{# Logo genérico de la app: marca abstracta (capas) sobre fondo primary.
   Usa tokens daisyUI, no colores fijos: se adapta solo a claro/oscuro.
   `size` opcional para la caja exterior (el glifo escala adentro). #}
<span class="inline-flex {{ size|default('h-8 w-8') }} shrink-0 items-center justify-center rounded-lg bg-primary text-primary-content" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
</span>
```

`resources/views/partials/_icon.twig` (una sola fuente para sidebar y header):

```twig
{# Iconos compartidos entre sidebar y header: una sola fuente para que ambos
   muestren EL MISMO svg por ruta. `name`: home|tasks. `class` opcional. #}
{% if name is defined and name == 'home' %}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ class|default('h-5 w-5 shrink-0') }}"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
{% elseif name is defined and name == 'tasks' %}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="{{ class|default('h-5 w-5 shrink-0') }}"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
{% else %}
{# Nombre desconocido: NO se renderiza nada. Antes esta rama caía a la casa, y
   un icono equivocado que parece válido es peor que un hueco: el typo se lee
   como un icono correcto y nadie lo revisa. Sin fallback, el nombre mal
   escrito se ve visiblemente mal y se arregla. #}
{% endif %}
```

### 15.2 Página de login y bloque usuario/salir

`resources/views/auth/login.twig` (form normal, sin HTMX: el login navega de verdad con 303):

```twig
{% extends 'layouts/auth.twig' %}

{% block title %}Acceder{% endblock %}

{% block content %}
    {# `w-full` y no `mx-auto`: el layout de auth ya centra en columna, asi el
       card aprovecha el ancho en movil en vez de quedar flotando. #}
    <div class="w-full max-w-sm">
        <h1 class="mb-4 text-center text-2xl font-bold">Acceder</h1>

        {% if flash_error %}
            <div class="alert alert-error mb-4" role="alert">
                <span>{{ flash_error }}</span>
            </div>
        {% endif %}
        {% if flash_info is defined and flash_info %}
            <div class="alert alert-info mb-4" role="alert">
                <span>{{ flash_info }}</span>
            </div>
        {% endif %}

        {# Form normal, sin HTMX: el login navega de verdad (303 a /tareas). #}
        <form method="post" action="{{ url('/login') }}" class="card border border-base-300 bg-base-100">
            <div class="card-body gap-3">
                {% include 'partials/_csrf.twig' %}
                {# Receta de daisyUI 5: `fieldset` + `fieldset-legend` en vez de
                   los wrappers de la v4 (`form-control` y `label-text`), que la
                   5 eliminó. El fieldset ya trae el grid vertical con su gap
                   entre la etiqueta y el input, que era lo único que aportaba
                   `form-control`.
                   La etiqueta es un <label for> y NO un <legend>: el legend
                   nombra al grupo, no al control, así que los inputs se
                   quedaban sin nombre accesible. `.fieldset-legend` es un
                   selector de clase (no `legend.fieldset-legend`), así que el
                   formato es el mismo. #}
                <fieldset class="fieldset">
                    <label class="fieldset-legend" for="login-email">Email</label>
                    <input id="login-email" type="email" name="email" required autocomplete="username"
                           value="{{ email|default('') }}"
                           placeholder="vos@ejemplo.com"
                           class="input w-full">
                </fieldset>
                <fieldset class="fieldset">
                    <label class="fieldset-legend" for="login-password">Clave</label>
                    <input id="login-password" type="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="input w-full">
                </fieldset>
                <label class="label cursor-pointer justify-start gap-2">
                    <input type="checkbox" name="remember" value="1" class="checkbox">
                    {# Sin `label-text`: era el texto de la v4 y la 5 la eliminó;
                       el formato de la fila lo da el `label` de afuera. #}
                    <span>Recordarme 30 días</span>
                </label>
                <button type="submit" class="btn btn-primary w-full">
                    Entrar
                </button>
            </div>
        </form>
    </div>
{% endblock %}
```

El bloque usuario/salir vive en la sidebar del layout (arriba del pie), porque tiene que estar en todas las páginas. Necesita `user_id`/`user_email` + CSRF en TODO render completo — por eso el `render()` base los pasa siempre (una PK indexada cuando hay sesión, nada cuando no) y el cierre de `/` los pasa explícito (cap. 9):

```twig
{% if user_id is defined and user_id %}
<div class="border-t border-base-300 p-2">
    <div class="sidebar-label truncate px-2 pb-1 text-xs opacity-60" :class="sidebarMini && 'lg:hidden'">{{ user_email }}</div>
    <form method="post" action="/logout">
        {% include 'partials/_csrf.twig' %}
        <button type="submit" class="btn btn-ghost btn-sm w-full justify-start" :class="sidebarMini && 'lg:justify-center'" title="Salir">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 shrink-0"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="sidebar-label" :class="sidebarMini && 'lg:hidden'">Salir</span>
        </button>
    </form>
</div>
{% endif %}
```

Y el breadcrumb del layout conoce un tercer caso (`active == 'login'` → texto plano "Acceder"): con dos ramas el header mentiría en la página de login. Si agregás páginas, cada una necesita su `active` y su rama — anotado como deuda visible, no como magia.

## 16. Ejemplo funcional de punta a punta (lista de tareas con HTMX)

La raíz (`GET /`) renderiza `resources/views/home.twig`, la página de presentación del stack. El formulario de altas vive en `resources/views/tasks/index.twig`.

`resources/views/home.twig`:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Mi App{% endblock %}

{% block content %}
    <div class="hero rounded-box border border-base-300 bg-base-100">
        <div class="hero-content py-6 text-center">
            <div class="max-w-xl">
                <h1 class="text-3xl font-bold">Mi App</h1>
                <p class="py-3 opacity-70">
                    Listado de tareas con altas, edición y borrado, servido por un esqueleto
                    PHP en capas. El formulario de altas está en la pantalla de tareas.
                </p>
                <a href="{{ url('/tareas') }}" class="btn btn-primary">Ir a tareas</a>
            </div>
        </div>
    </div>

    <h2 class="mb-3 mt-8 text-lg font-semibold">Stack</h2>

    <div class="grid gap-2 sm:grid-cols-2">
        {% for name, role in {
            'PHP': 'lenguaje',
            'Slim 4': 'router y middleware',
            'Eloquent ORM': 'modelos',
            'Twig': 'plantillas',
            'HTMX': 'intercambios parciales',
            'Alpine.js': 'estado en el cliente',
            'Tailwind CSS': 'estilos',
            'daisyUI': 'componentes',
            'SQLite': 'base de datos'
        } %}
            <div class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-100 p-3">
                <span class="font-medium">{{ name }}</span>
                <span class="badge badge-ghost">{{ role }}</span>
            </div>
        {% endfor %}
    </div>
{% endblock %}
```

`resources/views/tasks/index.twig`:

```twig
{% extends 'layouts/app.twig' %}

{% block title %}Tareas{% endblock %}

{% block content %}
    <h1 class="mb-4 text-2xl font-bold">Mis tareas</h1>

    {# hx-on::after-request limpia el input SOLO si el alta salió bien: el form
       vive fuera de #tareas-panel, así que el swap no lo toca y el texto
       quedaría pegado. `event.detail.successful` es false en un 400 de
       validación, en un 5xx y cuando no hubo respuesta; ahí el texto tipeado es
       justo lo que no se puede borrar, porque es lo único que el usuario tiene
       para corregir. El server ya conserva el título en el camino de edición
       (`editing_title`); esto es la otra mitad de la misma regla.
       maxlength=120 alinea el browser con el max:120 del server.
       hx-disabled-elt: sin esto un doble click en "Agregar" (o Enter repetido)
       emite dos POST y crea dos tareas; y en hardware lento el botón apagado es
       la única señal de que el request salió. El selector viaja con el id
       porque htmx resuelve un selector CSS pelado contra el documento entero
       (querySelectorAll sobre el root), no contra el form: `button` a secas
       apagaría también las filas, el header y el sidebar. `find button` queda
       atado a qué elemento toma htmx como disparador y `this` apagaría el form,
       no su submit. Acá el único <button> es el submit: el input no se
       deshabilita, así que el foco no se pierde mientras espera.
       La posición viaja en la URL del form: el alta no manda page/per_page en
       el cuerpo, así que sin esto crear desde "25 por página" devuelve la
       lista en 10 y el usuario pierde su elección sin haberla tocado. La
       página es 1 a propósito: la tarea nueva queda arriba. `pagination` está
       siempre en el contexto de index.twig (sólo lo renderiza
       TaskController::respond, que siempre pasa pageData). #}
    {# `method`/`action` no son redundantes con hx-post: htmx intercepta el
       submit y usa su atributo, pero sin JS el formulario tiene que poder
       enviarse solo. Antes solo existia hx-post, asi que un submit nativo hacia
       GET a la URL actual, recargaba y no creaba nada, sin avisar. #}
    <form id="alta-tarea" hx-post="{{ url('/tareas') }}?page=1&per_page={{ pagination.per_page }}"
          method="post" action="{{ url('/tareas') }}?page=1&per_page={{ pagination.per_page }}"
          hx-target="#tareas-panel" hx-swap="outerHTML"
          hx-on::after-request="if (event.detail.successful) this.reset()"
          hx-disabled-elt="#alta-tarea button"
          class="join mb-6 w-full">
        {% include 'partials/_csrf.twig' %}
        <input type="text" name="title" placeholder="Nueva tarea" aria-label="Nueva tarea" required maxlength="120"
               class="input join-item w-full">
        <button type="submit" class="btn btn-primary join-item">
            Agregar
        </button>
    </form>

    {% include 'tasks/_panel.twig' %}
{% endblock %}
```

`resources/views/tasks/_panel.twig` — **la unidad de intercambio de HTMX**:

```twig
{# `tabindex="-1"`: marca el panel como destino de foco programático (no
   entra en el orden de tabulación). Es el fallback de `restaurarFocoTrasSwap()`
   en app.twig cuando la acción hizo desaparecer el control que la disparó:
   borrar la fila, o guardar/cancelar, que salen del modo edición y con él de
   los botones Guardar y Cancelar. Sin este atributo el `.focus()` del layout
   no hace nada y el foco volvería al <body>, que es justo lo que S4 prohíbe.
   `class="focus:outline-none"`: el panel no es un control, así que el anillo
   de foco alrededor de toda la región no informa nada y sí ensucia la
   pantalla. Va SOLO acá; los controles conservan su anillo. #}
<div id="tareas-panel" tabindex="-1" class="focus:outline-none" hx-disabled-elt="#tareas-panel button">
    {# hx-disabled-elt: la región del swap declara UNA vez que sus <button> se
       deshabilitan mientras hay un request en vuelo; htmx resuelve el atributo
       subiendo por los ancestros, así que toggle, editar y borrar lo heredan sin
       repetirlo en cada fila. Sin esto, un doble click en cualquiera de ellos
       dispara dos veces la misma mutación (dos DELETE, dos toggles que se
       anulan) y en hardware lento no hay ninguna señal de que algo esté pasando.
       Por qué selector pelado y con el id adentro:
       - `find button` se resuelve contra el elemento que DISPARA (el botón de la
         fila), que no tiene botones adentro: htmx avisaría "returned no matches"
         por consola y no apagaría nada.
       - `this` apagaría el <div> (ahí `disabled` no hace nada) y dejaría vivos
         justo los botones.
       - Un selector pelado lo resuelve htmx con document.querySelectorAll, no
         contra el div: el prefijo #tareas-panel es lo que lo acota al panel.
       Alcance: sólo <button>, ningún otro descendiente. Los <a> de la paginación
       quedan vivos a propósito: `disabled` no existe para un enlace y daisyUI
       (.btn[disabled]) igual lo pintaría apagado, dejando algo que parece muerto
       y sigue clickeable. El <select> de per_page tampoco entra: cambiar de
       página o de tamaño es navegación, no una mutación que se pueda duplicar.
       A los que ya venían deshabilitados (paginación en el borde) no los toca:
       htmx sólo agrega el atributo si no estaba y sólo lo quita si lo puso él.
       Efecto lateral asumido: durante el DELETE también queda apagado el
       Cancelar/Cerrar del modal de confirmación. htmx los reactiva al terminar
       (incluso si el request falla) y Esc cierra el <dialog> nativo igual, así
       que no hay encierro. La señal visual la da daisyUI: no hace falta CSS. #}
    {# Fallback de PÁGINA COMPLETA (un POST sin HX-Request, ej. un form sin
       htmx): ahí no hay evento que escuchar, así que el aviso va inline.
       En las respuestas htmx estos dos banners llegan en null y el mensaje
       viaja en HX-Trigger-After-Swap hacia el stack de toasts del layout:
       un aviso, un canal (ver Controller::render). #}
    {% if flash_error %}
        <div class="alert alert-error mb-4" role="alert">
            <span>{{ flash_error }}</span>
        </div>
    {% endif %}
    {% if flash_success %}
        <div class="alert alert-success mb-4" role="status">
            <span>{{ flash_success }}</span>
        </div>
    {% endif %}

    <div id="lista-tareas">
        {% include 'tasks/_list.twig' %}
    </div>

    {# Paginación dentro del swap: viaja actualizada con cada acción. #}
    {% include 'partials/_pagination.twig' with {
        pagination: pagination,
        target: '#tareas-panel'
    } %}
</div>
```

> **Por qué el panel y no la lista pelada.** El flash se escribe en la sesión durante el POST, pero `flash_error` se renderiza acá, no en el layout. Si el POST devolviera `_list.twig` y HTMX lo inyectara dentro de `#lista-tareas`, el mensaje se escribiría en la sesión y nunca se vería — y peor, `Flash::get('error')` en `index()` lo consumiría igual en el siguiente full page load, así que tampoco aparecería después. Devolviendo el panel completo con `hx-swap="outerHTML"`, el flash y la lista viajan en el mismo swap.
>
> **Un solo mecanismo de notificación por ámbito.** Las validaciones de negocio viajan por el servidor en el mismo swap. El fallo CSRF **no puede** usar ese camino (el POST se corta antes del controlador y HTMX no hace swap con un 400): usa el header `HX-Trigger` que escucha el layout. Si borrás el listener quedás con el fallo silencioso original.

`resources/views/tasks/_list.twig`:

```twig
{# Posición de la lista (página + tamaño) que tiene que arrastrar TODA acción
   de fila: sin esto, tocar una fila desde la página 2 re-renderiza la página
   1 y el usuario pierde la fila que tocó. Se arma UNA sola vez acá y las
   cuatro URLs la usan tal cual, así no pueden divergir entre sí.
   Se imprime con `|raw` para que el `&` salga literal, igual que en
   partials/_pagination.twig (PaginationFlowTest lo fija así). No es una
   puerta de escape: la cadena sale de dos enteros de App\Support\Pagination,
   nunca de texto del usuario. Dentro del atributo es válido, porque
   `&per_page` no forma una referencia de carácter (no termina en `;`).
   La RUTA (lo que va antes del `?`) sí pasa por `url()`: es lo que la saca del
   dominio cuando la app vive bajo un prefijo (S13). El helper recibe sólo el
   path y el query queda afuera justamente para que el `&` siga siendo `&`. #}
{% set posicion = 'page=' ~ pagination.current_page ~ '&per_page=' ~ pagination.per_page %}
{# `data-focus-key` en cada control de acción: el swap de #tareas-panel
   (`outerHTML`) se lleva puesto el nodo que tenía el foco, así que el layout
   no tiene cómo saber a dónde devolverlo. El control que dispara publica su
   clave (`toggle-<id>`, `edit-<id>`, ...) antes del request y el layout busca
   esa MISMA clave en el markup recién intercambiado. La clave sale del id de
   la tarea, lo único estable entre un render y el siguiente: el índice de la
   fila cambia en cuanto se borra una. Mecanismo en `restaurarFocoTrasSwap()`
   del <script> de layouts/app.twig. #}
<ul class="space-y-2">
    {% for task in tasks %}
        {% if editing_id is defined and editing_id == task.id %}
            <li class="rounded-box border border-base-300 bg-base-100 p-3">
                <form hx-put="{{ url('/tareas/' ~ task.id) }}?{{ posicion|raw }}"
                      hx-target="#tareas-panel"
                      hx-swap="outerHTML"
                      class="flex items-center gap-2">
                    {% include 'partials/_csrf.twig' %}
                    {# En fallo de validación se repinta lo TIPEADO (editing_title),
                       no el valor guardado: si no, el usuario pierde lo que
                       escribió junto con el error. Twig autoescapea, así que
                       comillas o <script> tipeados salen neutrales. #}
                    {# Nombre accesible: un input sin label, sin aria-label y sin
                       placeholder se anuncia solo como "campo de texto", sin
                       decir de qué. NO se usa el título de la tarea como
                       nombre: ya está en `value`, y nombrar el campo con su
                       propio contenido hace que el lector lo anuncie dos
                       veces (nombre + valor). #}
                    <input type="text"
                           name="title"
                           aria-label="Título de la tarea"
                           {# Misma clave que el boton de editar: al entrar en modo
                              edicion ese boton desaparece y este input toma su
                              lugar, asi que el foco del swap aterriza en el
                              campo y no en el panel. #}
                           data-focus-key="edit-{{ task.id }}"
                           value="{{ editing_title is defined and editing_title is not null ? editing_title : task.title }}"
                           required
                           maxlength="120"
                           class="input input-sm w-full">
                    <button type="submit"
                            class="btn btn-primary btn-sm"
                            data-focus-key="save-{{ task.id }}">
                        Guardar
                    </button>
                    {# Cancelar vuelve a la página desde la que se entró a editar. #}
                    <button type="button"
                            hx-get="{{ url('/tareas') }}?{{ posicion|raw }}"
                            hx-target="#tareas-panel"
                            hx-swap="outerHTML"
                            data-focus-key="cancel-{{ task.id }}"
                            class="btn btn-ghost btn-sm">
                        Cancelar
                    </button>
                </form>
            </li>
        {% else %}
            <li class="flex items-center justify-between gap-2 rounded-box border border-base-300 bg-base-100 p-3">
                {# `items-start` y no `items-center`: el disparador de estado se
                   alinea con la PRIMERA línea del título. Centrado contra el
                   bloque, en un título de cinco renglones caía sobre el
                   tercero y dejaba de leerse como parte del título. El
                   desplazamiento óptico (`-mt-0.5` en el disparador) conserva
                   el aspecto centrado cuando el título ocupa un solo renglón. #}
                <span class="flex min-w-0 items-start gap-2 {{ task.status == 'completado' ? 'opacity-80' : '' }}">
                    {# Icono de estado: verde para completado, gris para pendiente.
                       Ambos salen de `--ink-*` (ver el <style> del layout), no de
                       `text-success` ni `text-base-content/40`: los semanticos de
                       daisyUI son FONDOS y como tinta daban 1.96:1 y 2.53:1,
                       bajo el 3:1 que pide un objeto grafico.
                       El estado NO se escribe al lado del título ("Pendiente" /
                       "Completada el ..."): en móvil ese texto le robaba ancho al
                       título y el dato sólo interesa cuando lo buscás. Ahora el
                       icono es el disparador de un tooltip por click.
                       Por qué no `title=` nativo: en táctil no existe el hover y
                       en desktop aparecería solo al pasar el mouse encima del
                       icono, no al pedirlo. `aria-label` lleva el texto completo
                       (lector de pantalla) y el tooltip es puramente visual:
                       `aria-hidden` + `pointer-events-none` (nunca se queda con
                       el click que debería cerrarlo). #}
                    {% set estado_texto = task.status == 'completado'
                        ? 'Completada' ~ (task.completed_at ? ' el ' ~ task.completed_at|date('d/m/Y H:i') : '')
                        : 'Pendiente' %}
                    {# `-mt-0.5` (2px): centra el icono de 20px sobre el primer
                       renglón (24px de alto). Sin esto, con `items-start` el
                       icono quedaría 2px por debajo del centro óptico. #}
                    <span class="relative -mt-0.5 inline-flex shrink-0"
                          x-data="{ abierto: false }"
                          @click.outside="abierto = false"
                          @keydown.escape="abierto = false">
                        {# Es un <button> de verdad: focus + Enter/Space lo abren
                           igual que un click, sin listeners de teclado a mano.
                           El anillo de foco va SIN color a propósito: daisyUI
                           5.7.46 no trae `outline-<color>` y `primary` no está
                           en la paleta de Tailwind, así que `outline-primary` no
                           lo compilaba nadie y el anillo salía por defecto. Con
                           `outline-2` + `outline-offset-2` (utilidades planas de
                           Tailwind, que el browser build sí resuelve) el color
                           cae en `currentColor`, que contrasta en los dos temas.
                           Área de toque: el único recuadro era el svg de 20px,
                           bajo el mínimo de 24×24 (WCAG 2.5.8). El `p-1` lleva
                           el control a 28×28 sin agrandar el icono, y el
                           `flex/items-center/justify-center` lo mantiene
                           centrado en el recuadro que agrega el padding. #}
                        <button type="button"
                                class="flex cursor-help items-center justify-center rounded-full p-1 focus-visible:outline-2 focus-visible:outline-offset-2"
                                @click="abierto = !abierto"
                                :aria-expanded="abierto ? 'true' : 'false'"
                                aria-label="{{ estado_texto }}">
                            {% if task.status == 'completado' %}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 icon-success" aria-hidden="true"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            {% else %}
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 icon-pending" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 12 12 17 16 15"/></svg>
                            {% endif %}
                        </button>
                        {# `tooltip-estado`: gancho para apagar el x-transition en
                           "reducir movimiento". El override vive en el
                           <style> del layout porque Alpine aplica la
                           transición sobre el nodo, y una clase de proyecto
                           es la única forma de apuntarle sin depender de sus
                           atributos internos. #}
                        <span x-cloak
                              x-show="abierto"
                              x-transition.opacity.duration.150ms
                              aria-hidden="true"
                              class="tooltip-estado pointer-events-none absolute left-0 top-full z-20 mt-1 whitespace-nowrap rounded-box border border-base-300 bg-base-100 px-2 py-1 text-xs shadow-md">
                            {{ estado_texto }}
                        </span>
                    </span>
                    {# min-w-0 + break-words: un titulo de 120 caracteres sin
                       espacios es un solo token; sin esto el flex no puede
                       achicar la fila y a 320px produce scroll horizontal. #}
                    <span class="min-w-0 break-words">{{ task.title }}</span>
                </span>
                {# Acciones como ICONOS. Con texto, tres botones ("Completar"
                   + "Editar" + "Eliminar") se comían media fila y en móvil
                   empujaban el título a un renglón. Ahora el ancho es fijo
                   (btn-square) y el texto no desaparece: sigue en aria-label
                   (lector de pantalla) y en title (tooltip nativo). No se usa
                   el `tooltip` de daisyUI porque tres tooltips CSS vecinos se
                   superponen al pasar el mouse entre botones.
                   `shrink-0`: en pantallas angostas se comprimen los iconos,
                   no el título. Los svg son inline (mismo criterio que los
                   iconos de estado de arriba): cero requests y heredan
                   currentColor, así que el tema claro/oscuro es gratis. #}
                <div class="flex shrink-0 gap-1">
                    {# Botón para cambiar el estado: HTMX intercambia el panel completo.
                       `?{{ posicion }}`: la respuesta se repinta en la misma página. #}
                    <button
                        hx-post="{{ url('/tareas/' ~ task.id ~ '/toggle') }}?{{ posicion|raw }}"
                        hx-target="#tareas-panel"
                        hx-swap="outerHTML"
                        hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"
                        data-focus-key="toggle-{{ task.id }}"
                        class="btn btn-square btn-ghost btn-sm size-9 {{ task.status == 'completado' ? 'icon-success' : '' }}"
                        aria-label="{{ task.status == 'completado' ? 'Desmarcar' : 'Completar' }}"
                        title="{{ task.status == 'completado' ? 'Desmarcar' : 'Completar' }}">
                        {% if task.status == 'completado' %}
                            {# Deshacer: flecha circular #}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                        {% else %}
                            {# Completar: tilde #}
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        {% endif %}
                    </button>
                    <button
                        hx-get="{{ url('/tareas/' ~ task.id ~ '/edit') }}?{{ posicion|raw }}"
                        hx-target="#tareas-panel"
                        hx-swap="outerHTML"
                        data-focus-key="edit-{{ task.id }}"
                        class="btn btn-square btn-ghost btn-sm size-9"
                        aria-label="Editar"
                        title="Editar">
                        {# Lápiz #}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                    </button>
                    <button
                        class="btn btn-square btn-ghost btn-sm size-9 icon-error"
                        aria-label="Eliminar"
                        title="Eliminar"
                        data-focus-key="delete-{{ task.id }}"
                        @click="$el.closest('li').querySelector('dialog').showModal()">
                        {# Papelera #}
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </div>

            {# Confirmación con modal daisyUI en vez del confirm() nativo:
               - <dialog> es nativo: sin JS de terceros, sin estado Alpine.
               - Se abre por DOM (botón y dialog viven en la misma fila).
               - Cancelar es form method=dialog y afuera es modal-backdrop.
               - En éxito, el swap reemplaza #tareas-panel y el dialog sale
                 del DOM: no hay nada que cerrar a mano.
               - En 400 (CSRF) no hay swap: queda abierto y suena el toast. #}
            <dialog class="modal" aria-label="Confirmar borrado de {{ task.title }}">
                <div class="modal-box">
                    <h3 class="font-bold">¿Borrar esta tarea?</h3>
                    <p class="py-3 opacity-70">{{ task.title }}</p>
                    <div class="modal-action">
                        <form method="dialog">
                            <button class="btn btn-ghost">Cancelar</button>
                        </form>
                        <button
                            hx-delete="{{ url('/tareas/' ~ task.id) }}?{{ posicion|raw }}"
                            hx-headers="{{ {'csrf_name': csrf_name, 'csrf_value': csrf_value}|json_encode|e('html_attr') }}"
                            hx-target="#tareas-panel"
                            hx-swap="outerHTML"
                            {# La MISMA clave que el botón de arriba, y por la
                               misma razón: este es el botón que dispara el
                               DELETE, así que es el que tiene que publicarla
                               en htmx:beforeRequest. El de arriba solo abre el
                               modal (no hace ningún request) y conserva la
                               clave por simetría. Como la fila desaparece en
                               el swap, esa clave no va a existir en el DOM
                               nuevo y el layout enfoca el panel. #}
                            data-focus-key="delete-{{ task.id }}"
                            class="btn btn-error">
                            Borrar
                        </button>
                    </div>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>Cerrar</button>
                </form>
            </dialog>
            </li>
        {% endif %}
    {% else %}
        <li class="opacity-60">No hay tareas todavía.</li>
    {% endfor %}
</ul>
```

> **`editing_id` gobierna la fila, `editing_title` conserva lo tipeado.** Sin `editing_id` la lista no sabe cuál fila va en formulario; sin `editing_title`, en fallo de validación el input se repintaría con el valor guardado y el usuario perdería lo escrito junto con el error.
>
> **El form de edición lleva sus propios tokens.** Aparece y desaparece con cada swap dentro del panel: no puede depender de los hidden de `#alta-tarea`, que queda fuera. Por eso incluye `partials/_csrf.twig` con los tokens de cada render.
>
> **`hx-put` manda el body como `application/x-www-form-urlencoded`** (default de htmx para no-GET) y `addBodyParsingMiddleware()` lo parsea igual que un POST. No hace falta `_METHOD` oculto: la ruta ya es `PUT`.
>
> **Acciones como iconos, con el texto en `aria-label` + `title`.** Tres botones con texto ("Completar" + "Editar" + "Eliminar") se comían media fila y en móvil empujaban el título a un renglón. Ahora son `btn btn-square`: el ancho es fijo y el texto no desaparece — sigue en `aria-label` (lector de pantalla) y en `title` (tooltip nativo). `shrink-0` en el contenedor: en pantallas angostas se comprimen los iconos, no el título. No se usa el `tooltip` de daisyUI porque tres tooltips CSS vecinos se superponen al pasar el mouse entre botones. Los `svg` son inline: cero requests y heredan `currentColor`, así que el claro/oscuro sale gratis.
>
> **El borrado se confirma con un `<dialog class="modal">` de daisyUI, no con `hx-confirm`.** `hx-confirm` es `window.confirm()`: bloquea el hilo, no se estiliza y no se puede probar sin navegador. El `<dialog>` es nativo (sin JS de terceros ni estado Alpine), se abre por DOM desde el botón de la misma fila, y como vive dentro del panel, en éxito el swap se lo lleva puesto: no hay nada que cerrar a mano. En 400 (CSRF) no hay swap: queda abierto y suena el toast. Cancelar es `<form method="dialog">`; el click afuera, `modal-backdrop`.
>
> **`hx-headers` en el botón Borrar del modal: sin él, eliminar no borra nada.** `Guard` valida el token también en `DELETE`, y el botón no está dentro de un `<form>` con los hidden. Un `hx-delete` a secas cae en el failure handler: **400**, tarea intacta, cero error visible. `Guard` solo mira `getParsedBody()` y headers (nunca query string), y htmx manda los params del DELETE al query string (`methodsThatUseUrlParams`) — por eso los tokens van en headers y **no** con `hx-include`. Medido:
>
> | Dónde van los tokens del DELETE | Resultado medido |
> |---|---|
> | query string (`?csrf_name=…&csrf_value=…`) | **400** + `HX-Trigger: {"csrf":"…"}` |
> | headers `csrf_name` / `csrf_value` | **200**, la tarea desaparece |
>
> **El toggle es un `POST` que devuelve el panel entero.** `hx-post="/tareas/{id}/toggle"` con `hx-swap="outerHTML"` sobre `#tareas-panel` y tokens por `hx-headers` (el botón no está en un form: mismo motivo que el DELETE). No alcanza con swapear el botón solo: cambiar el estado mueve también la fila entera y los contadores de la paginación.
>
> **El estado vive en un tooltip, no escrito al lado del título.** "Pendiente" / "Completada el 14/08/2026 22:31" en la fila le robaba ancho al título en móvil, y es un dato que sólo se busca cuando interesa. Ahora el icono es un `<button>` que abre un globito **por click** (Alpine `abierto`; se cierra con `@click.outside` o `@keydown.escape`): en táctil no existe el hover, así que `title=` nativo no serviría, y el `tooltip` de daisyUI es CSS-hover. El texto completo va en `aria-label` del botón (`:aria-expanded` refleja si está abierto) y el globo es puramente visual: `aria-hidden` + `pointer-events-none`, para que no se quede con el click que debería cerrarlo. El icono es entonces la única señal de estado, así que el pendiente usa `text-base-content/40` (tenue) y el completado `text-success`: `text-base-400` no existe en daisyUI 5 y salía a full contraste, más pesado que el verde.
>
> **`index()` también responde distinto para htmx** (cap. 9): los botones "Editar" y "Cancelar" piden `GET /tareas/...`/`GET /tareas` esperando la región. Sin el branch, htmx metería un `<html>` completo dentro del panel.

`public/index.php` (ya mostrado en el cap. 13):

```php
<?php

declare(strict_types=1);

$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
```

### 16.1 Paginación reutilizable estilo Laravel

Nada de `get()` pelado en listados (cap. 18.3): cada render trae su slice + metadata. Y sin `illuminate/pagination` a propósito — ese paquete exige `php ^8.3` y subiría el piso de todo el proyecto solo por un objeto cuyos links igual renderizamos nosotros. Son las mismas 2 queries que hace `paginate()` por dentro (count + slice), a mano:

`app/Support/Pagination.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Paginación estilo Laravel sin illuminate/pagination (ese paquete exige
 * php ^8.3 y subiría el piso del proyecto; acá son las mismas 2 queries que
 * hace paginate() por dentro: count + slice). Todo testeable sin HTTP.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 10;

    /** @var int[] */
    public const ALLOWED_PER_PAGE = [10, 15, 25, 50];

    /**
     * Pagina un query Eloquent. Devuelve items + metadata lista para
     * partials/_pagination.twig. $page fuera de rango se clampena (nunca vacío
     * por pedir de más).
     *
     * @return array{items: \Illuminate\Support\Collection, total: int, per_page: int, current_page: int, last_page: int, from: int, to: int, base_url: string, pages: array}
     */
    public static function paginate(Builder $query, int $page, int $perPage, string $baseUrl): array
    {
        $perPage = self::clampPerPage($perPage);

        $total = (int) (clone $query)->count();
        $lastPage = (int) max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $items = (clone $query)->forPage($page, $perPage)->get();

        return [
            'items'        => $items,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
            'to'           => $total === 0 ? 0 : min($page * $perPage, $total),
            'base_url'     => $baseUrl,
            'pages'        => self::window($page, $lastPage),
        ];
    }

    public static function clampPerPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::ALLOWED_PER_PAGE, true)
            ? $value
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * Ventana de páginas con techo de CINCO entradas: primera, actual y
     * última, más un ellipsis (null = "…") en cada salto. Con last <= 5
     * entran todas y no hay nada que saltear.
     *
     * Los vecinos de la actual NO se muestran a propósito: llegar a la
     * anterior/siguiente lo cubren las flechas « », que están siempre. La
     * regla vieja (current±2, hasta nueve botones) pasaba el techo de cuatro
     * opciones visibles que aguanta un punto de decisión (R7).
     *
     * La deduplicación sale de las guardas, no de array_unique: current == 1
     * o current == last no repiten el número porque el `if` que lo agrega lo
     * excluye. array_unique con comparación laxa borraría además un ellipsis
     * doble, que es válido cuando current está lejos de los dos extremos.
     *
     * @return array<int|null>
     */
    public static function window(int $current, int $last): array
    {
        if ($last <= 5) {
            return range(1, $last);
        }

        $pages = [1];

        if ($current > 2) {
            $pages[] = null;
        }

        if ($current > 1 && $current < $last) {
            $pages[] = $current;
        }

        if ($current < $last - 1) {
            $pages[] = null;
        }

        $pages[] = $last;

        return $pages;
    }
}
```

En el controlador, un método privado por ruta listada (la página y el tamaño salen del query string, que mandan tanto el navegador como los links HTMX):

```php
    private function pageData(Request $request): array
    {
        $query = $request->getQueryParams();

        $listing = Pagination::paginate(
            Task::orderByDesc('id'),
            (int) ($query['page'] ?? 1),
            (int) ($query['per_page'] ?? Pagination::DEFAULT_PER_PAGE),
            '/tareas'
        );

        return [
            'tasks'      => $listing['items'],
            'pagination' => $listing,
        ];
    }
```

Cada acción lo mezcla (`$this->pageData($request) + ['editing_id' => ...]`): full y swap llevan su slice al día. Ojo con el casteo `(int)`: bajo `strict_types`, pasar el string del query directo al `int` del helper es `TypeError`.

`resources/views/partials/_pagination.twig` (portable: sin ids ni rutas propias, todo por params — `pagination`, `target` obligatorio, `swap`/`show_size` opcionales; así sirve en cualquier ruta sin cambios):

```twig
{# Paginación reutilizable (daisyUI + HTMX). Params obligatorios: pagination
   (ver Pagination::paginate) y target (selector hx-target). Opcionales: swap
   (default outerHTML) y show_size (default true). Sin ids ni rutas propias:
   todo sale de los params, así sirve en cualquier ruta sin cambios.
   Cada <a> lleva `href` ADEMÁS de `hx-get`, y ambos reciben la MISMA URL: un
   <a> sin href no tiene rol de enlace ni entra en el orden de tabulación, así
   que con teclado o lector de pantalla las páginas 2+ eran inalcanzables.
   Las URLs son navegación real, pero htmx sigue interceptando el click cuando
   está cargado: el href es el fallback, no un segundo camino. #}
{% if pagination.total > 0 %}
<div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-sm opacity-70">
        Mostrando {{ pagination.from }} al {{ pagination.to }} de {{ pagination.total }} tareas
    </p>

    <div class="flex flex-wrap items-center gap-2">
        {% if show_size|default(true) %}
        {# Etiqueta visible, no sólo `aria-label`: el texto dice qué controla el
           select sin depender de que el lector de pantalla lo anuncie, y el
           nombre accesible queda idéntico para no anunciar dos cosas distintas.
           No se usa el `.label` de daisyUI: atenúa al 60 % de la tinta (~4.4:1
           sobre base-200, bajo AA para 14px) y ese color lo heredaría el select.
           El mismo tono del contador de arriba, con `opacity-70` sólo en el
           texto, da ~6:1 y deja el select a tinta plena. #}
        <label class="flex items-center gap-2">
            <span class="text-sm opacity-70">Tareas por página</span>
            <select class="select select-sm w-auto" name="per_page"
                    aria-label="Tareas por página"
                    hx-get="{{ pagination.base_url }}"
                    hx-target="{{ target }}"
                    hx-swap="{{ swap|default('outerHTML') }}"
                    hx-trigger="change"
                    hx-include="this">
                {% for size in [10, 15, 25, 50] %}
                    <option value="{{ size }}"{% if size == pagination.per_page %} selected{% endif %}>{{ size }}</option>
                {% endfor %}
            </select>
        </label>
        {% endif %}

        {# Bordes y estado actual de la paginación. Los enlaces llevaban el
           mismo fondo que la página y un borde de ~1.1:1, así que los números
           se leían como texto suelto; `btn-outline` les da un contorno que
           existe en los dos temas. La página actual era `btn-active` (un gris
           pálido de relleno): indistinguible de una flecha deshabilitada, es
           decir, no se podía saber dónde se está parado. `btn-primary` la
           vuelve la única ficha con relleno de color pleno contra `base-100`,
           distinta de los enlaces (contorno, sin relleno) y de las flechas
           deshabilitadas (fondo base, sin contorno), en claro y en oscuro. #}
        <div class="join" role="navigation" aria-label="Paginación">
            {# Un `destino` por enlace y se imprime dos veces (href y hx-get):
               una sola fuente, así los dos no pueden apuntar a páginas
               distintas. Se imprime con `|raw` para que el `&` salga literal,
               como ya estaba (PaginationFlowTest lo fija así). No es una
               puerta de escape: la cadena es la base fija del controller más
               enteros de App\Support\Pagination. Dentro del atributo es
               válido, porque `&per_page` no termina en `;` y por lo tanto no
               es una referencia de carácter. #}
            {% if pagination.current_page > 1 %}
                {% set destino = pagination.base_url ~ '?page=' ~ (pagination.current_page - 1) ~ '&per_page=' ~ pagination.per_page %}
                <a class="join-item btn btn-sm btn-outline"
                   href="{{ destino|raw }}"
                   hx-get="{{ destino|raw }}"
                   hx-target="{{ target }}" hx-swap="{{ swap|default('outerHTML') }}"
                   aria-label="Página anterior">«</a>
            {% else %}
                <button class="join-item btn btn-sm btn-disabled" disabled aria-label="Página anterior">«</button>
            {% endif %}

            {% for p in pagination.pages %}
                {% if p is null %}
                    <button class="join-item btn btn-sm btn-disabled" disabled aria-hidden="true">…</button>
                {% elseif p == pagination.current_page %}
                    <button class="join-item btn btn-sm btn-primary" aria-current="page">{{ p }}</button>
                {% else %}
                    {% set destino = pagination.base_url ~ '?page=' ~ p ~ '&per_page=' ~ pagination.per_page %}
                    <a class="join-item btn btn-sm btn-outline"
                       href="{{ destino|raw }}"
                       hx-get="{{ destino|raw }}"
                       hx-target="{{ target }}" hx-swap="{{ swap|default('outerHTML') }}"
                       aria-label="Página {{ p }}">{{ p }}</a>
                {% endif %}
            {% endfor %}

            {% if pagination.current_page < pagination.last_page %}
                {% set destino = pagination.base_url ~ '?page=' ~ (pagination.current_page + 1) ~ '&per_page=' ~ pagination.per_page %}
                <a class="join-item btn btn-sm btn-outline"
                   href="{{ destino|raw }}"
                   hx-get="{{ destino|raw }}"
                   hx-target="{{ target }}" hx-swap="{{ swap|default('outerHTML') }}"
                   aria-label="Página siguiente">»</a>
            {% else %}
                <button class="join-item btn btn-sm btn-disabled" disabled aria-label="Página siguiente">»</button>
            {% endif %}
        </div>
    </div>
</div>
{% endif %}
```

Tres decisiones que importan:

1. **Los links conservan `per_page`.** Sin eso, cambiar de página resetearía el tamaño a 10 en silencio. El selector, al revés, resetea a página 1 (no manda `page`): es lo correcto cuando cambia el tamaño.
2. **`per_page` se valida contra lista blanca** (`clampPerPage`): un `?per_page=999999` cae al default en vez de traer la tabla entera a memoria — que es exactamente el bug de 18.3 con otro nombre.
3. **En vacío no se renderiza nada** (el `{% if %}` exterior): la fila "No hay tareas todavía" ya comunica el estado, y un paginador de cero páginas es ruido.

## 17. Levantar el servidor y mantenerlo vivo

```bash
php -S 0.0.0.0:8080 -t public
```

**El servidor va directo, nunca por Composer.** `composer serve` existió y se retiró: Composer ejecuta cada script como subproceso con un `process-timeout` de 300 s (default de `composer config process-timeout`) y, al vencer, lo mata con:

```
The process "php -S 0.0.0.0:8080 -t public" exceeded the timeout of 300 seconds
```

O sea: `php -S` no se cae solo a los 5 minutos, lo baja Composer. Un servidor web no cabe en un script de un solo tiro. Si te encontrás escribiendo `composer serve`, el script ahora es una guarda (`scripts/serve.php`) que aborta con esa explicación en vez de dejarte esperando media hora de debugging.

Flujo completo en un clon fresco (en este orden):

```bash
cp .env.example .env
composer install:termux            # dependencias sin resolver en el teléfono
composer dump-autoload -o
composer migrate                   # crea el schema (phinx usa safeLoad: no pide .env)
nano .env                          # descomentar ADMIN_EMAIL y ADMIN_PASSWORD (cap. 11.1)
vendor/bin/phinx seed:run -s DatabaseSeeder   # admin + datos demo
php -S 0.0.0.0:8080 -t public
```

Abrí `http://localhost:8080/` desde el navegador del teléfono (bienvenida) y `http://localhost:8080/tareas` (la app).

### 17.1 Que no se te muera cuando apagás la pantalla

Android suspende los procesos cuando la pantalla se apaga, y la app deja de responder sin error visible.

```bash
pkg install termux-api
termux-wake-lock
```

### 17.2 Que sobreviva cerrar la terminal

`php -S` corre en foreground: si cerrás la sesión de Termux, muere. Corrélo dentro de `tmux`:

```bash
pkg install tmux
tmux new -s app
php -S 0.0.0.0:8080 -t public   # ctrl+b, d para desprender la terminal
```

Volvés cuando quieras con `tmux attach -t app`.

### 17.3 A quién le estás exponiendo el puerto

`0.0.0.0` significa **todas las interfaces**, incluyendo el wifi.

| Quiero | Uso |
|---|---|
| Solo desde el propio teléfono | `php -S 127.0.0.1:8080 -t public` |
| Desde la red local confiable | `php -S 0.0.0.0:8080 -t public` |
| Desde una red que no controlo | Un túnel SSH, no `0.0.0.0` |

### 17.4 El server embebido y las requests paralelas de HTMX

`php -S` es **single-threaded** por defecto: si tu página dispara dos `hx-get` al mismo tiempo, el segundo espera al primero.

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 0.0.0.0:8080 -t public
```

Con workers, dos requests concurrentes sobre la **misma sesión** se contendian por el lock de archivo de PHP. El flag para eso es `session.lazy_write=1` en el `php.ini` (cap. 18.2).

### 17.5 Comandos frecuentes

```bash
composer migrate                         # corre migraciones pendientes
vendor/bin/phinx create NombreMigracion  # crea una nueva migración
vendor/bin/phinx seed:create NombreSeed  # crea un seed de datos (cap. 11.1)
vendor/bin/phinx seed:run -s DatabaseSeeder  # corre todos en orden (cap. 11.2)
vendor/bin/phinx seed:run -s NombreSeed  # corre uno solo
vendor/bin/phinx rollback -t 0           # baja todo (el "fresh", ver cap. 11)
vendor/bin/phinx status                  # up/down por migración
composer dump-autoload -o                # regenera el autoload tras agregar clases
composer db:backup                       # backup timestamped a storage/backups/
composer test                            # corre la suite (phpunit)
composer check:termux                    # 32 o 64 bits, según PHP_INT_SIZE
```

### 17.6 Backup: el teléfono se puede perder

`scripts/db-backup.php` (con `composer db:backup`): copia `database.sqlite` + sidecars WAL (`-wal`/`-shm`/`-journal`) si existen, a `storage/backups/YYYYMMDD-HHMMSS/` (gitignored: son datos reales). La base y el path salen del mismo default relativo del cap. 6:

```php
<?php

declare(strict_types=1);

// Backup consistente de SQLite con VACUUM INTO.
// Uso: composer db:backup
// Destino: storage/backups/YYYYMMDD-HHMMSS.sqlite (gitignored: son datos reales).
//
// VACUUM INTO hace una lectura transaccional y escribe un archivo nuevo: la
// copia sale consistente aunque la app esté escribiendo, sin copiar -wal/-shm/
// -journal en caliente.
//
// Retención: KEEP (env, default 10) conserva los últimos archivos .sqlite.
// Los directorios de backups viejos no se tocan.

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad();

/**
 * GitHub Actions arranca PHP con variables_order=GPCS: $_ENV queda vacío
 * aunque la variable exista. getenv() es el fallback que también usa
 * phinx.php para que DB_DATABASE no dependa de la config del SAPI.
 */
function envOrDefault(string $name, string $default): string
{
    $value = $_ENV[$name] ?? null;
    if (!is_string($value) || $value === '') {
        $value = getenv($name);
    }

    return is_string($value) && $value !== '' ? $value : $default;
}

$db = envOrDefault('DB_DATABASE', __DIR__ . '/../database/database.sqlite');

if ($db === ':memory:' || !is_file($db)) {
    fwrite(STDERR, "No hay base para respaldar en '{$db}'.\n");
    exit(1);
}

$storage = __DIR__ . '/../storage';
if (!is_dir($storage) && !mkdir($storage, 0777, true) && !is_dir($storage)) {
    fwrite(STDERR, "No se pudo crear '{$storage}'.\n");
    exit(1);
}

$backupDir = $storage . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "No se pudo crear '{$backupDir}'.\n");
    exit(1);
}
$backupDir = realpath($backupDir);

$base = $backupDir . '/' . date('Ymd-His');
$dest = $base . '.sqlite';
for ($suffix = 2; file_exists($dest); $suffix++) {
    $dest = $base . '-' . $suffix . '.sqlite';
}

try {
    $pdo = new PDO('sqlite:' . $db, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    // SQLite no admite parámetros en VACUUM INTO: se escapa la ruta como
    // literal SQL duplicando las comillas simples.
    $target = str_replace("'", "''", $dest);
    $pdo->exec("VACUUM INTO '{$target}'");
} catch (PDOException $e) {
    if (file_exists($dest)) {
        @unlink($dest);
    }
    fwrite(STDERR, "Falló el backup de '{$db}': {$e->getMessage()}\n");
    exit(1);
}

$keep = max(1, (int) envOrDefault('KEEP', '10'));
$backups = [];
foreach (glob($backupDir . '/*.sqlite') ?: [] as $file) {
    if (is_file($file) && preg_match('/^\d{8}-\d{6}(?:-\d+)?\.sqlite$/', basename($file))) {
        $backups[] = $file;
    }
}
// strnatcmp ordena timestamps y sufijos (-2, -3, ... -10) como números.
usort($backups, static fn (string $a, string $b): int => strnatcmp(basename($b), basename($a)));
foreach (array_slice($backups, $keep) as $old) {
    @unlink($old);
}

$size = filesize($dest);
echo "Backup en {$dest} ({$size} bytes)." . PHP_EOL;
```

Hacelo con la app quieta (sin requests en vuelo): copiar sqlite+WAL en caliente puede partir el backup por la mitad. Y si querés sacarlo del teléfono, con `termux-setup-storage` (cap. 1) es un `cp -r` a `~/storage/shared/Download/`.

## 18. El capítulo que de verdad importa: 32 bits

En `armv7l` el límite no es la CPU, es la **memoria**. Y casi siempre se manifiesta en Composer, no en tu app.

### 18.1 Composer es el problema, no tu app

`COMPOSER_MEMORY_LIMIT=-1` (que aparece en muchos tutoriales) es **medio consejo**: desactiva el límite interno de Composer, no el del sistema. En un proceso de 32 bits el techo sigue siendo el espacio de direcciones, y cuando lo pasás te mata el OOM killer del kernel. Morís sin mensaje de error de PHP, que es la peor forma de morir.

Lo que sí funciona, en este orden:

1. **Resolver afuera y traer el resultado.** Resolvé el `composer.lock` en una máquina de 64 bits y pasá `composer.lock` + `vendor/`. El teléfono nunca corre el solver.
2. **`composer install:termux`, nunca `composer update`.** Con el lock commiteado, `install` no resuelve: descarga. Es la razón por la que el `.gitignore` excluye `vendor/` pero **no** `composer.lock`.
3. **Si necesitás resolver en el teléfono, poné un límite realista**, no `-1`. Un límite que Composer puede reportar es infinitamente más útil que uno que el kernel te aplica a escondidas (el script `install:termux` ya trae las banderas que bajan el pico: `--prefer-dist --no-dev --no-scripts`).

### 18.2 OPcache: la mayor ganancia disponible

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
; entre requests concurrentes de HTMX (ver 17.4)
session.lazy_write=1
```

Si tu `php -m` no lista `Zend OPcache`, el paquete de Termux no lo trae compilado y esto no aplica.

### 18.3 `memory_limit` de la app

El default suele ser 128M, que con Eloquent y una consulta de cientos de filas se queda corto. Subilo a 256M y observá:

```bash
php -r "echo ini_get('memory_limit');"
```

Si ves `Allowed memory size exhausted`, el culpable casi siempre es una consulta sin paginación sobre una tabla que crece. En un teléfono eso se convierte en el OOM killer, y ahí sí morís sin stack trace.

### 18.4 Dónde viven las sesiones

```bash
php -i | grep -E 'session.save_path|session.gc_maxlifetime'
```

El backend de archivos de PHP guarda las sesiones en un directorio del sistema. Si `save_path` apunta a algo que Android puede limpiar, **perdés todas las sesiones sin ningún error**. Si ese es tu caso, fijalo a un directorio que vos controles (el bootstrap ya crea `storage/sessions/` para eso; agregalo donde corresponda antes de `session_start()`). Y agregá `/storage/sessions/` al `.gitignore` (ya está).

### 18.5 El techo de enteros

Con PHP compilado a 32 bits, `PHP_INT_MAX` ronda los **2.147 mil millones** en vez de los ~9.2 trillones de 64 bits (`composer check:termux` te dice en cuál estás). Para una app con SQLite y autoincrement no es un problema. Si alguna vez manejás IDs muy grandes o timestamps crudos como enteros, es un techo duro a tener en cuenta.

### 18.6 SQLite en WAL

`PRAGMA journal_mode=WAL` (ya seteado en `config/container.php`) te da lecturas concurrentes sin bloquear escrituras — suficiente para el patrón típico de HTMX con muchos GET parciales y algún POST ocasional. Ojo que WAL deja archivos `-wal` y `-shm` al lado de la base: si alguna vez respaldás `database.sqlite` copiando solo ese archivo, el backup va a estar incompleto. Por eso el `.gitignore` los excluye por separado y nunca van al repo.

## 19. Antes de exponer la app fuera de tu teléfono

Checklist corto. La mayoría de estos defaults son seguros **mientras la app esté en `127.0.0.1` con `APP_DEBUG=true`**; dejan de serlo en cuanto la exponés.

- [ ] `APP_DEBUG=false` en `.env`. Con `true`, Whoops muestra stack traces, rutas y variables de entorno a cualquiera que abra la URL.
- [ ] `session_set_cookie_params()` con `httponly` y `samesite` (cap. 8). Con `secure => true` solo si efectivamente servís por HTTPS — si no, el navegador no manda la cookie y la app falla en silencio.
- [ ] `127.0.0.1` en vez de `0.0.0.0`, salvo que sepas exactamente quién está en esa red.
- [ ] `.env` fuera de git (viene de `.env.example`, nunca se commitea). `composer.lock` adentro.
- [ ] HTTPS o un túnel. Sin TLS, el token CSRF y la cookie de sesión viajan en claro.
- [ ] `session.use_strict_mode=1` activo (cap. 8).
- [ ] `storage/logs/app.log` existe y lo mirás de vez en cuando. Con el error handler del capítulo 7, los errores de producción sí quedan registrados; sin mirarlos, no sirve de nada.
- [ ] `APP_DEBUG=false` también apaga Whoops: la respuesta 500 es un mensaje genérico. Eso es correcto, no un bug.
- [ ] `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy` presentes en las respuestas, incluidas las 404 y 500 (cap. 7.2).
- [ ] Assets vendorizados pineados con `integrity` SRI (cap. 14). Sin esto, un publish del mantenedor te cambia el runtime sin deploy.

### Sobre Content-Security-Policy (y por qué no está)

No incluimos CSP en el esqueleto, y no es por ignorancia ni por pereza. Es porque en este stack exacto una CSP estricta **no es alcanzable sin romper cosas que querés**. Esto es exactamente lo que cuesta al intentarlo:

| Lo que exige CSP estricta | Lo que cuesta en este stack |
|---|---|
| `script-src` sin `'unsafe-eval'` | El build browser de Alpine usa declaraciones `Function`, que también violan la política. Hay que cambiar la forma de servir Alpine o relajar la directiva. |
| `style-src` sin `'unsafe-inline'` | `@tailwindcss/browser` inyecta `<style>` en runtime y **no soporta nonce**. La única salida es compilar Tailwind a un CSS estático. |
| Todo lo anterior, y encima | `frame-ancestors` sí lo vas a tener, pero recién adoptando la CSP entera. |

Lo que sí hace la lista del capítulo 7.2 es bloquear lo que importa a coste cero: `nosniff`, clickjacking y fuga de `Referer`. Y lo que cierra el agujero de supply chain son los `integrity` del capítulo 14 sobre assets vendorizados.

**Cuándo reconsiderar CSP:** el día que la app sea alcanzable por alguien que no controlás **y** renderice input de usuario. Ahí XSS deja de ser teórico. Ese día el orden correcto es compilar Tailwind a CSS estático primero, y recién después adoptar CSP.

## 20. Testing: testear el esqueleto, no la demo

**El reframe que lo ordena todo: testeá el esqueleto, no el ejemplo.** `TaskController` es descartable: lo vas a borrar el día que hagas tu app real. El pipeline de middlewares, el `Validator`, el `Flash`, la configuración de sesión, el `Guard` de CSRF y el contrato del panel HTMX se quedan para siempre. Los tests existen para proteger el *plumbing*, no para verificar que tu lista de tareas ande.

### 20.1 Instalación

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

// La sesion se abre UNA vez, aca, y no en el primer request. En PHP >= 8.5
// session_start() vuelve a dejar $_SESSION en [] (antes conservaba lo que el
// llamador habia puesto), asi que los tests que siembran $_SESSION['user_id']
// en setUp() llegaban a /tareas sin sesion: 303 a /login y cuerpo vacio, que
// el helper de tokens reportaba como "no encontre el hidden csrf_name".
// Abriendo la sesion antes, el middleware ve PHP_SESSION_ACTIVE y no la
// reinicia, con lo cual los valores de cada setUp() sobreviven al request.
session_name($_ENV['SESSION_NAME'] ?? 'mi_app_session');
session_start();

register_shutdown_function(static function () use ($db): void {
    foreach ([$db, $db . '-wal', $db . '-shm'] as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
});
```

`tests/Support/AppFactory.php` — el helper que evita el bug más tonto de esta configuración:

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use Slim\App;

/**
 * La app se construye una vez y se comparte entre todos los tests.
 * El esquema NO se define aca: lo aplican las migraciones de Phinx desde
 * tests/bootstrap.php. Duplicar el CREATE TABLE a mano dejaba a la suite
 * verde contra un esquema que la app nunca usa (y sin la FK de remember_tokens).
 */
final class AppFactory
{
    private static ?App $app = null;

    public static function make(): App
    {
        return self::$app ??= require __DIR__ . '/../../bootstrap/app.php';
    }

    /**
     * Descarta la app cacheada para que el proximo make() vuelva a bootstrapear.
     * Solo para tests que cambian el entorno ANTES del arranque: el prefijo de
     * ruta (APP_BASE_PATH) se fija una vez, en `$app->setBasePath()`, y Twig
     * resuelve la funcion `url()` contra ese mismo prefijo. Sin este reset el
     * test correria contra una app armada con el entorno anterior y el verde no
     * diria nada. Ningun test de produccion deberia llamarlo: la app es una.
     */
    public static function reset(): void
    {
        self::$app = null;
    }
}
```

> **Un solo `require` de `bootstrap/app.php`, siempre.** `AppFactory::make()` cachea. Como el schema vive en memoria y se crea una sola vez, los tests que **escriben** filas tienen que limpiar después de sí mismos (`Task::query()->delete()` en `tearDown()`). Si no, dependés de la orden de ejecución.

Agregá `/.phpunit.cache/` y `/tests/.phpunit.result.cache` al `.gitignore` (ya están).

Correr:

```bash
vendor/bin/phpunit
composer test
# esperado: OK (la cifra la imprime este comando)
```

### 20.2 El `Validator`, con tabla

Este es el test que más retorno da por línea, y el que habría atrapado el bug de `required` del capítulo 12.

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

Fijate en la quinta fila: `required` con `'   '` **sí falla** — esa fila es la que separa `notEmpty()` de `notOptional()` y documenta la decisión de forma ejecutable.

### 20.3 El flash: consume-una-vez

El contrato de `Flash::get()` es que **borra**. Si mañana alguien "optimiza" el `unset`, esta suite se da vuelta.

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

### 20.4 El flujo CSRF de punta a punta

Ejercita en una sola request real: el pipeline completo, la sesión abierta **antes** que el `Guard`, el body parsing, Twig, routing, el controlador, Eloquent y el flash. Si alguien reordena `config/middleware.php`, este test falla.

`tests/Http/CsrfFlowTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

final class CsrfFlowTest extends TestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        // La capa de auth en sí se testea en AuthTest, no acá.
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        unset($_SESSION['user_id']);
        User::query()->delete();
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

### 20.4.1 Aislamiento: `$_SESSION` es global

`FlashTest` hace `$_SESSION = []` en `setUp()`, y PHPUnit corre todos los tests en **un solo proceso**: esa línea borra la sesión que otros tests necesitan. Dos salidas limpias: aislar por proceso (`processIsolation="true"` en `phpunit.xml`, más lento pero cada test tiene su propio `$_SESSION`) o guardar y restaurar en vez de pisar. No dejes el `$_SESSION = []` a secas sin el `tearDown()` que restaura: es el tipo de detalle que hace que un equipo odie los tests.

### 20.5 Errores: el 404 tiene que seguir siendo 404

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
     * (medido) en vez del ~1 KB de errors/error.twig. En 32 bits ese
     * pico de memoria se paga caro (cap. 17), y de paso dejás de mirar la
     * página de error real porque todo 404 se ve igual de apocalyptic.
     *
     * También fija el contrato de S7: el mensaje sale en español (no el
     * "Not found." en inglés que manda el vendor) y la página carga el bundle
     * de daisyUI; sin ese <link> todas sus clases son inertes y el código de
     * estado vuelve a verse como texto plano.
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
        self::assertStringContainsString('No encontramos esa página.', $html);
        self::assertStringContainsString('/assets/daisyui-5.7.46.css', $html);
        self::assertLessThan(4096, strlen($html), 'El 404 deberia pesar bytes, no cientos de KB.');
    }
}
```

Ese segundo test existe por una razón concreta: el `SecurityHeadersMiddleware` tiene que ir **por fuera** del `ErrorMiddleware`. Si alguien lo mueve adentro, este test falla. El tercero atrapa la regresión de Whoops (y ojo: en SAPI `cli` el PrettyPageHandler no renderiza, así que el `assertStringContainsString('Not found.')` es el que realmente muerde ahí, no el de Whoops).

### 20.6 Routing: la raíz y el link que nadie testeaba

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

**Un link es un contrato**: cuando tu app muestre una URL en pantalla, un test de que esa URL **responde** es más barato que cualquier test de que la pantalla se ve bien. Comprobá el destino, no el origen.

### 20.7 Borrar y recargar el panel: lo que htmx espera recibir

`tests/Http/DeleteTaskTest.php` (la D del CRUD; antes de este archivo, `grep -r "delete" tests/` no devolvía ningún DELETE — el botón más usado sin un solo test):

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
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
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        unset($_SESSION['user_id']);
        User::query()->delete();
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

    public function test_eliminar_pide_confirmacion_con_modal_y_sin_confirm_nativo(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $html = (string) self::app()->handle($this->request('GET', '/tareas'))->getBody();

        self::assertStringNotContainsString('hx-confirm', $html, 'El confirm() nativo tiene que estar muerto.');
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('¿Borrar esta tarea?', $html);
        // El query string de posicion lo fija tests/Http/ListStateContractTest;
        // aca solo importa que el borrado apunte al recurso correcto.
        self::assertStringContainsString('hx-delete="/tareas/' . $task->id . '?', $html);
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

El segundo test no assertea lo deseado: assertea el bug. Si algún día `Guard` leyera el query string, este test daría 200 y habría que releerlo. Los dos juntos dejan escrito el contrato: los tokens del DELETE viajan en headers.

`tests/Http/PartialRenderTest.php` (qué recibe HTMX con `HX-Request` — protege el branch de `index()`):

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

/**
 * Qué recibe htmx y qué recibe el navegador en la misma ruta.
 *
 * Con `HX-Request` la respuesta tiene que ser el panel y nada más: el botón
 * "Cancelar" apunta a `/tareas` con `hx-target="#tareas-panel"` y
 * `hx-swap="outerHTML"`, así que si el servidor devuelve `index.twig` (que
 * extiende el layout) htmx recibe un documento completo, se queda con el
 * `<body>` y lo inserta dentro del panel: quedan dos `<h1>`, dos
 * `id="alta-tarea"` y el padding del layout dos veces.
 *
 * Sin `HX-Request` es un navegador pidiendo una página (form con JS apagado,
 * URL directa, curl): ahí el fragmento es el bug opuesto —markup sin `<head>`
 * ni hoja de estilos— y la respuesta tiene que ser el documento entero (S9).
 */
final class PartialRenderTest extends TestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        // El schema vive en un SQLite compartido: si no limpiás, la suite
        // depende del orden de ejecución.
        unset($_SESSION['user_id']);
        User::query()->delete();
        Task::query()->delete();
    }

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

    /**
     * S9: una mutación invocada SIN htmx (form normal con JS apagado, URL
     * directa, un curl) no puede devolver `tasks/_panel.twig`. Ese fragmento
     * viaja sin `<head>`, sin hoja de estilos y sin shell: el navegador lo
     * pinta como texto plano y la acción, aunque haya funcionado, parece
     * rota. El contrato es el mismo que ya cumple `index()`: la región para
     * htmx, la página entera para todo lo demás.
     */
    public function test_post_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $token = $this->token();

        $response = self::app()->handle(
            self::request(false, 'POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, Task::count(), 'La tarea no se creó: la respuesta no es la que se está probando.');

        $this->assertDocumentoCompleto((string) $response->getBody());
    }

    /**
     * El par del test de arriba: la rama de htmx no cambió. Sin esto, un
     * `respond()` que devolviera siempre la página entera rompería cada swap
     * (el `<body>` entero dentro del panel) y la suite de toasts no lo vería:
     * ella mira los avisos, no la forma del documento.
     */
    public function test_post_con_hx_request_devuelve_solo_el_panel(): void
    {
        $token = $this->token();

        $response = self::app()->handle(
            self::request(true, 'POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un <html> completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
    }

    public function test_delete_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            self::request(false, 'DELETE', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id), 'La tarea no se borró: la respuesta no es la que se está probando.');

        $this->assertDocumentoCompleto((string) $response->getBody());
    }

    /**
     * Lo que hace que el navegador pueda pintar la respuesta por su cuenta.
     * El link de la hoja de estilos es el assert que de verdad separa una
     * página de un fragmento: `<!DOCTYPE html>` podría venir de un `_panel`
     * mal armado, pero sin `<link rel="stylesheet">` la página sigue sin
     * estilos y el bug de S9 continúa.
     */
    private function assertDocumentoCompleto(string $html): void
    {
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'Sin doctype el navegador queda en modo quirks.');
        self::assertStringContainsString('<html', $html, 'La mutación devolvió un fragmento, no una página.');
        self::assertStringContainsString('rel="stylesheet"', $html, 'Sin la hoja de estilos la página se ve sin CSS.');
        self::assertStringContainsString('/assets/daisyui-5.7.46.css', $html);
    }

    /**
     * @return array{csrf_name: string, csrf_value: string}
     */
    private function token(): array
    {
        $html = (string) self::app()->handle(self::request(false))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    /**
     * @param array<string, string> $body
     */
    private static function request(
        bool $htmx,
        string $method = 'GET',
        string $path = '/tareas',
        array $body = []
    ): ServerRequestInterface {
        // URI absoluta, igual que en el resto de la suite: con path-only el
        // host queda vacío y Slim se porta distinto en los casos borde.
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            // Body crudo con su Content-Type: así pasa por el Body Parsing
            // Middleware igual que en producción. withParsedBody() saltearía
            // justo la capa que rompen los cambios de orden del pipeline.
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

### 20.8 Edición: la U del CRUD

`tests/Http/EditTaskTest.php` (cubre render del form, persistencia del PUT, validación que rechaza **conservando lo tipeado**, id inexistente y token ausente):

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
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
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        unset($_SESSION['user_id']);
        User::query()->delete();
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

        // El query string de posicion lo fija tests/Http/ListStateContractTest;
        // aca solo importa que el guardado apunte al recurso correcto.
        self::assertStringContainsString('hx-put="/tareas/' . $task->id . '?', $html);
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
        self::assertStringContainsString('alert-error', (string) $response->getBody(), 'El flash de error no se renderizó.');
        // La fila se queda en modo formulario: si volviera a la lista, el
        // usuario pierde el lugar que estaba editando junto con el error.
        self::assertStringContainsString('hx-put=', (string) $response->getBody());
    }

    public function test_put_invalido_conserva_lo_tipeado_en_lugar_del_guardado(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();
        $tipeado = str_repeat('b', 200);

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => $tipeado,
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title, 'La validación falló: el título no cambia.');
        self::assertStringContainsString(
            'value="' . $tipeado . '"',
            (string) $response->getBody(),
            'El input se repintó con el valor guardado y perdió lo tipeado.'
        );
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

Notá el `alert-error` en el test del flash: el panel renderiza `alert alert-error` de daisyUI, y el test asserta esa clase (antes era `bg-red-100`). El contrato es el mismo ("el flash se renderiza"); el selector cambió con el rework visual. Y el test de `editing_title` deja por escrito que en fallo de validación el input conserva lo tipeado, no el valor guardado.

### 20.9 Auth: login, remember-me y la puerta

`tests/Http/AuthTest.php`. Los tests comparten proceso (y `$_SESSION`) con el resto de la suite: cada test parte de sesión limpia y deja las tablas vacías. La cookie remember se pasa a mano por header (el cliente de tests no guarda cookies solo) y hay que poblar `$_COOKIE` a mano porque el `ServerRequest` no lo hace:

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Controllers\AuthController;
use App\Models\RememberToken;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

/**
 * Auth v1: login/logout + remember-me + puerta de /tareas.
 *
 * Los tests comparten proceso (y $_SESSION) con el resto de la suite: cada
 * test parte de sesión limpia y deja las tablas vacías. La cookie remember se
 * pasa a mano por header: el cliente de tests no guarda cookies solo.
 */
final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RememberToken::query()->delete();
        User::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_login_muestra_el_formulario(): void
    {
        $response = self::app()->handle(self::request('GET', '/login'));

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('type="password"', $html);
        self::assertStringContainsString('name="remember"', $html);
    }

    public function test_dummy_hash_es_un_bcrypt_valido_de_coste_12(): void
    {
        $info = password_get_info(AuthController::DUMMY_HASH);

        self::assertSame('bcrypt', $info['algoName']);
        self::assertSame(12, $info['options']['cost']);
        self::assertSame(60, strlen(AuthController::DUMMY_HASH));
        // El dummy nunca autentica, pero igual paga el coste de bcrypt.
        self::assertFalse(password_verify('cualquier-clave', AuthController::DUMMY_HASH));
    }

    public function test_login_con_credenciales_malas_no_entra_ni_dice_cual_fallo(): void
    {
        $this->createUser('yo@ejemplo.com', 'secreto123');
        $token = $this->csrf();

        foreach ([
            ['email' => 'otro@ejemplo.com', 'password' => 'secreto123'], // email inexistente
            ['email' => 'yo@ejemplo.com', 'password' => 'clave-errada'], // clave errada
        ] as $body) {
            $response = self::app()->handle(
                self::request('POST', '/login', $body + $token)
            );

            self::assertSame(200, $response->getStatusCode());
            // El MISMO mensaje en ambos casos: decir cuál falló es enumeración.
            self::assertStringContainsString('Credenciales inválidas.', (string) $response->getBody());
        }

        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    /**
     * El login no es una pantalla de la app: dentro del shell ofrecia
     * navegacion a /tareas, que sin sesion redirige de vuelta a este mismo
     * formulario. Era una puerta que no lleva a ningun lado, y ademas mostraba
     * el header, el toggle de tema y el footer de una app a la que todavia no
     * se entro. Vive en layouts/auth.twig, no en layouts/app.twig.
     */
    public function test_el_login_no_se_renderiza_dentro_del_shell(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/login'))->getBody();

        self::assertStringContainsString('action="/login"', $html);
        self::assertStringNotContainsString('id="sidebar"', $html);
        self::assertStringNotContainsString('href="/tareas"', $html);
    }

    public function test_login_valido_redirige_y_abre_sesion(): void
    {
        $user = $this->createUser();
        $token = $this->csrf();

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => 'yo@ejemplo.com',
                'password' => 'secreto123',
            ] + $token)
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/tareas', $response->getHeaderLine('Location'));
        self::assertSame($user->id, $_SESSION['user_id'] ?? null);
    }

    public function test_login_con_remember_emite_cookie_y_guarda_solo_el_hash(): void
    {
        $this->createUser();
        $token = $this->csrf();

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => 'yo@ejemplo.com',
                'password' => 'secreto123',
                'remember' => '1',
            ] + $token)
        );

        $pair = $this->rememberPair($response);
        self::assertMatchesRegularExpression('/^[0-9a-f]{24}:[0-9a-f]{64}$/', $pair);

        [$selector, $validator] = explode(':', $pair);
        $row = RememberToken::query()->where('selector', $selector)->first();

        self::assertNotNull($row, 'El token no se persistió.');
        // En base solo el hash: si se filtra la base, el par no sirve.
        self::assertNotSame($validator, $row->hashed_validator);
        self::assertTrue(password_verify($validator, $row->hashed_validator));
    }

    public function test_remember_reanuda_sesion_y_rota_el_token(): void
    {
        $user = $this->createUser();
        $pair = $this->loginWithRemember($user);

        // Request nueva sin sesión pero con la cookie.
        $_SESSION = [];
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], false, ['remember' => $pair])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($user->id, $_SESSION['user_id'] ?? null, 'La cookie no reanudó la sesión.');

        // Rotación: el selector presentado murió y nació otro.
        [$oldSelector] = explode(':', $pair);
        self::assertNull(RememberToken::query()->where('selector', $oldSelector)->first());
        self::assertSame(1, RememberToken::query()->where('user_id', $user->id)->count());

        $newPair = $this->rememberPair($response);
        self::assertNotSame($pair, $newPair);
    }

    public function test_remember_adulterado_no_entra_y_quema_todo(): void
    {
        $user = $this->createUser();
        $pair = $this->loginWithRemember($user);
        [$selector] = explode(':', $pair);

        $_SESSION = [];
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], false, ['remember' => $selector . ':' . str_repeat('0', 64)])
        );

        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertSame(0, RememberToken::query()->where('user_id', $user->id)->count());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_remember_expirado_no_entra_y_se_limpia(): void
    {
        $user = $this->createUser();
        $validator = str_repeat('a', 64);

        RememberToken::create([
            'selector'         => 'expiretest1234567890abcd',
            'user_id'          => $user->id,
            'hashed_validator' => password_hash($validator, PASSWORD_DEFAULT),
            'expires_at'       => time() - 60,
        ]);

        $_SESSION = [];
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], false, ['remember' => 'expiretest1234567890abcd:' . $validator])
        );

        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertNull(RememberToken::query()->where('selector', 'expiretest1234567890abcd')->first());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_ruta_protegida_sin_login_redirige(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function test_ruta_protegida_htmx_devuelve_hx_redirect(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas', [], true));

        // 200 + HX-Redirect (no 303): un redirect rompería el swap de HTMX.
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('HX-Redirect'));
    }

    public function test_logout_cierra_quema_remember_y_redirige(): void
    {
        $user = $this->createUser();
        // El token de logout se scrapea ANTES de entrar: logueado, GET /login
        // redirige a /tareas y ya no hay form.
        $token = $this->csrf();
        $this->loginWithRemember($user);

        $response = self::app()->handle(
            self::request('POST', '/logout', $token)
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertSame(0, RememberToken::query()->where('user_id', $user->id)->count());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_login_estando_logueado_redirige_a_tareas(): void
    {
        $user = $this->createUser();
        $_SESSION['user_id'] = $user->id;

        $response = self::app()->handle(self::request('GET', '/login'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/tareas', $response->getHeaderLine('Location'));
    }

    private function createUser(string $email = 'yo@ejemplo.com', string $password = 'secreto123'): User
    {
        return User::create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /** Token CSRF fresco de la página de login. */
    private function csrf(): array
    {
        $html = (string) self::app()->handle(self::request('GET', '/login'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name en /login');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value en /login');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    /** Login con remember y devuelve el par crudo de la cookie emitida. */
    private function loginWithRemember(User $user): string
    {
        $token = $this->csrf();

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => $user->email,
                'password' => 'secreto123',
                'remember' => '1',
            ] + $token)
        );

        self::assertSame(303, $response->getStatusCode());

        return $this->rememberPair($response);
    }

    private function rememberPair(ResponseInterface $response): string
    {
        $raw = $this->rememberRaw($response);

        self::assertNotSame('', $raw, 'No se emitió cookie remember.');

        return explode(';', $raw, 2)[0];
    }

    private function rememberRaw(ResponseInterface $response): string
    {
        foreach ($response->getHeader('Set-Cookie') as $line) {
            if (str_starts_with($line, 'remember=')) {
                return substr($line, strlen('remember='));
            }
        }

        return '';
    }

    private static function request(
        string $method,
        string $path,
        array $body = [],
        bool $htmx = false,
        array $cookies = []
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        if ($cookies !== []) {
            $pairs = [];
            foreach ($cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }
            $request = $request->withHeader('Cookie', implode('; ', $pairs));
        }

        // La cookie remember llega por header: hay que poblar $_COOKIE a mano,
        // el ServerRequest no lo hace solo.
        if (isset($cookies['remember'])) {
            $_COOKIE['remember'] = $cookies['remember'];
        } else {
            unset($_COOKIE['remember']);
        }

        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
```

Dos consecuencias de proteger `/tareas` que te van a morder si no las sabés:

1. **Los tests viejos entran logueados.** CsrfFlow, Delete, Edit y Partial pegan a `/tareas`: cada uno crea un usuario en `setUp()` e inyecta `$_SESSION['user_id']` (la auth en sí se testea acá, no ahí), y lo limpia en `tearDown()` junto con las tablas. Sin eso, todo lo viejo da 303.
2. **`AppFactory` crea las tres tablas** en `:memory:` (`tasks`, `users`, `remember_tokens` espejando las migraciones). Si agregás una migración y olvidás su `CREATE TABLE` acá, los tests corren contra un esquema viejo en verde.

### 20.10 Paginación: matemática pura + flujo con 12 filas

`tests/Support/PaginationTest.php` (sin HTTP: clamp, ventana y slices contra `:memory:` — si esto está verde, lo raro en pantalla es maquetación, no datos):

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Task;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La matemática de la paginación, sin HTTP: clamp, ventana y slices.
 * Si esto está verde, lo que se ve raro en pantalla es maquetación, no datos.
 */
final class PaginationTest extends TestCase
{
    protected function tearDown(): void
    {
        Task::query()->delete();
    }

    public function test_clamp_acepta_solo_tamanos_permitidos(): void
    {
        self::assertSame(10, Pagination::clampPerPage(10));
        self::assertSame(15, Pagination::clampPerPage(15));
        self::assertSame(25, Pagination::clampPerPage(25));
        self::assertSame(50, Pagination::clampPerPage(50));
        self::assertSame(10, Pagination::clampPerPage(7));
        self::assertSame(10, Pagination::clampPerPage(0));
        self::assertSame(10, Pagination::clampPerPage(-5));
        self::assertSame(10, Pagination::clampPerPage('abc'));
        self::assertSame(10, Pagination::clampPerPage(null));
    }

    /** @return array<string, array{0: int, 1: int, 2: array}> */
    public static function windows(): array
    {
        return [
            'una sola'            => [1, 1, [1]],
            'pocas sin ellipsis'  => [1, 5, [1, 2, 3, 4, 5]],
            'inicio con ellipsis' => [1, 10, [1, null, 10]],
            'medio con dos'       => [5, 10, [1, null, 5, null, 10]],
            'final con una'       => [9, 10, [1, null, 9, 10]],
            'última exacta'       => [10, 10, [1, null, 10]],
            'vecina del inicio'   => [2, 10, [1, 2, null, 10]],
            'seis paginas, medio' => [3, 6, [1, null, 3, null, 6]],
        ];
    }

    #[DataProvider('windows')]
    public function test_ventana(int $current, int $last, array $expected): void
    {
        self::assertSame($expected, Pagination::window($current, $last));
    }

    /**
     * R7: un punto de decisión no muestra más de cuatro opciones visibles, así
     * que la ventana tiene que entrar en cinco entradas contando el «…».
     * Se barre todo el espacio que la paginación real puede producir en vez de
     * un caso suelto: el techo es la regla, no el ejemplo.
     */
    public function test_la_ventana_nunca_pasa_de_cinco_entradas(): void
    {
        foreach (range(1, 12) as $last) {
            foreach (range(1, $last) as $current) {
                $pages = Pagination::window($current, $last);
                $caso = sprintf('ventana de %d en %d', $current, $last);

                self::assertLessThanOrEqual(5, count($pages), $caso);
                self::assertSame(1, $pages[0], $caso . ': empieza en la primera');
                self::assertSame($last, $pages[array_key_last($pages)], $caso . ': termina en la última');
            }
        }
    }

    public function test_pagina_intermedia_trae_su_slice_y_contadores(): void
    {
        $this->seedTasks(25);

        $p = Pagination::paginate($this->query(), 2, 10, '/tareas');

        self::assertSame(25, $p['total']);
        self::assertSame(10, $p['per_page']);
        self::assertSame(2, $p['current_page']);
        self::assertSame(3, $p['last_page']);
        self::assertSame(11, $p['from']);
        self::assertSame(20, $p['to']);
        self::assertCount(10, $p['items']);
        self::assertSame('/tareas', $p['base_url']);
    }

    public function test_pagina_fuera_de_rango_se_clampea_a_la_ultima(): void
    {
        $this->seedTasks(25);

        $p = Pagination::paginate($this->query(), 99, 10, '/tareas');

        self::assertSame(3, $p['current_page']);
        self::assertCount(5, $p['items']);
        self::assertSame(21, $p['from']);
        self::assertSame(25, $p['to']);
    }

    public function test_pagina_cero_o_negativa_es_la_primera(): void
    {
        $this->seedTasks(5);

        self::assertSame(1, Pagination::paginate($this->query(), 0, 10, '/t')['current_page']);
        self::assertSame(1, Pagination::paginate($this->query(), -3, 10, '/t')['current_page']);
    }

    public function test_sin_filas_no_rompe_los_contadores(): void
    {
        $p = Pagination::paginate($this->query(), 1, 10, '/tareas');

        self::assertSame(0, $p['total']);
        self::assertSame(1, $p['last_page']);
        self::assertSame(0, $p['from']);
        self::assertSame(0, $p['to']);
        self::assertCount(0, $p['items']);
    }

    public function test_per_page_invalido_cae_al_default(): void
    {
        $this->seedTasks(25);

        $p = Pagination::paginate($this->query(), 1, 999, '/tareas');

        self::assertSame(10, $p['per_page']);
        self::assertSame(3, $p['last_page']);
    }

    private function query(): Builder
    {
        return Task::orderByDesc('id');
    }

    private function seedTasks(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
    }
}
```

Este archivo ya pagó su costo: la primera versión de `window()` deduplicaba con `array_unique` y borraba un ellipsis doble válido — el test `medio con dos` lo agarró antes que cualquier ojo.

`tests/Http/PaginationFlowTest.php` (12 filas a propósito: con pocas, todo cae en página 1 y estos tests serían verdes sin paginar nada):

```php
<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * Paginación de punta a punta: 12 filas, slices, tamaño y contadores.
 * Con pocas filas todo cae en la página 1 y estos tests serían verdes sin
 * paginar nada: por eso se siembran 12.
 */
final class PaginationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth).
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;

        for ($i = 1; $i <= 12; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        User::query()->delete();
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_pagina_dos_trae_las_mas_viejas_y_contadores(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?page=2'));

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        // Orden desc por id: la 1 y la 2 caen en la página 2, la 12 queda en la 1.
        self::assertStringContainsString('Tarea 01', $html);
        self::assertStringNotContainsString('Tarea 12', $html);
        self::assertStringContainsString('Mostrando 11 al 12 de 12 tareas', $html);
    }

    public function test_per_page_grande_mete_todo_en_una(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?per_page=25'));

        $html = (string) $response->getBody();

        self::assertStringContainsString('Tarea 01', $html);
        self::assertStringContainsString('Tarea 12', $html);
        self::assertStringContainsString('Mostrando 1 al 12 de 12 tareas', $html);
    }

    public function test_per_page_invalido_cae_al_default(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?per_page=999&page=2'));

        $html = (string) $response->getBody();

        self::assertStringContainsString('Mostrando 11 al 12 de 12 tareas', $html);
    }

    public function test_los_links_apuntan_al_panel_y_conservan_el_tamano(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?page=2&per_page=10'));

        $html = (string) $response->getBody();

        self::assertStringContainsString('hx-get="/tareas?page=1&per_page=10"', $html);
        self::assertStringContainsString('hx-target="#tareas-panel"', $html);
    }

    private static function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);
    }
}
```

### 20.11 Qué NO testear

- **Eloquent.** Es de Laravel. `Task::create()` inserta una fila: eso es un test de SQLite, no tuyo.
- **Twig.** Es de Twig. Un test de render de plantillas testea el motor de plantillas.
- **Slim.** El framework ya tiene su propia suite.
- **daisyUI.** Las clases (`btn`, `alert-error`) son del sistema: testear que existen es testear la librería. Lo que SÍ se testea es que tu HTML las mencione donde corresponde (flash) y que los contratos (panel, tokens, branch HX) sigan vivos bajo el shell nuevo.

Y una regla de costo: **cada test que escribas tiene que pagar su mantenimiento**. Un test que asserta comportamiento trivial o que se rompe con cada refactor es peor que nada, porque entrenás a ignorar rojas. Si no te da miedo que falle, no lo escribas.

## 21. Troubleshooting: síntomas, no theory

Cada entrada lleva un tag que dice de dónde sale:

| Tag | Significado |
|---|---|
| **[v]** | Verificado acá: lo corrí contra el proyecto de referencia de esta guía y te paso la salida real. |
| **[t]** | **No verificado en dispositivo.** Es lo más probable en un teléfono real, pero nadie lo ejecutó todavía. Tratá la salida esperada como hipótesis, no como hecho. |
| **[d]** | Comportamiento documentado por el proyecto upstream. No lo probé yo. |

### 21.1 La app no instala: `Unable to locate package`

**[t]** Toda la guía asume que `pkg` tiene paquetes `arm`.

```bash
uname -m                        # armv7l = 32-bit, aarch64 = 64-bit
pkg update
pkg install php -y
```

Si sigue sin encontrar el paquete, cambiá de mirror y reintentá (`termux-change-repo`). Si ni así, no sigas con el cap. 2: sin PHP no hay Composer que valga.

### 21.2 PHP no trae SQLite

```bash
php -m | grep -i sqlite
# esperado: pdo_sqlite y sqlite3
```

En Termux el paquete `php` normalmente los trae adentro. **[t]** Comprobá con `which php` que esté bajo `$PREFIX/bin`.

### 21.3 La base no se crea o apunta a otro lado

**[v]** La versión vieja de esta guía moría acá con `QueryException` cruda. Ahora el container tiene default relativo + autocreación + `RuntimeException` accionable (cap. 6): si ves ese mensaje, corré `pwd` en la raíz y corregí `DB_DATABASE`.

Dos casos que el mensaje no cubre, a saber:

1. **Seteaste `DB_DATABASE` a un absoluto y Phinx migra otro archivo** (cap. 11: Phinx usa path fijo, la app usa el env). Si `/tareas` dice `no such table: tasks` justo después de migrar, es esto: o borrá la variable (default relativo para ambos) o migrá consciente del path.
2. **`no such table: tasks` en un clon fresco** significa que nunca corriste `composer migrate`. No es un bug: la base se autocrea vacía, el schema lo pone Phinx.

```bash
ls -la database/                # ¿existe el archivo? ¿cuánto pesa?
vendor/bin/phinx status         # up o down
```

### 21.4 Composer muere sin decir nada

**[t]** `composer install` no imprime error de PHP y tu shell vuelve. OOM killer del kernel.

```bash
composer install:termux; echo "exit=$?"
# exit=137 es la firma del OOM killer (128 + 9 = SIGKILL)
```

La solución no es `-1`, es `install:termux` (cap. 18.1).

### 21.5 `Class "Respect\Validation\Validator" not found` o `V::create()` falla

**[v]** Aparece **en la primera request**, no en el `composer install`:

```bash
composer show respect/validation | head -2
# versions : * 2.5.0     <- correcto
# versions : * 3.x.x     <- 3.x, donde Validator pasó a ser interface
```

Causa: `composer require respect/validation` sin el `^2.0`. Corregí el `require` y `composer update respect/validation`.

### 21.6 Página de error vacía (0 bytes)

**[v]** `Twig::render()` escribe en el stream **sin rebobinar**: con el cursor al final, `getContents()` devuelve `''`. El fix (`$stream->rewind()`) ya está en el cap. 7, pero lo vas a ver si tocaste esa parte. Otro camino al mismo síntoma: `errors/error.twig` con error de sintaxis (degrada a texto plano a propósito). Para descartar la cache:

```bash
rm -rf storage/cache/twig/*
```

### 21.7 Un 404 que devuelve 500, o un 404 que pesa 154 KB

**[v]** Bug de Whoops, dos síntomas: 404 como 500 (`allowQuit(false)`/`sendHttpCode(false)` ausentes) y 404 de 154 KB (Whoops atendiendo 4xx; la rama correcta es `$status >= 500`).

```bash
curl -sI http://127.0.0.1:8080/no-existe | head -5
# esperás HTTP/1.1 404 + X-Frame-Options: DENY + X-Content-Type-Options: nosniff
```

### 21.8 La sesión no persiste

**[t]** Sin error visible: deslogueos o CSRF mismatch. Tres causas en orden: `session.save_path` limpiado por Android (cap. 18.4), `secure => true` sin HTTPS (la cookie no viaja), directorio inexistente (el bootstrap ya crea `storage/sessions/`).

```bash
curl -sI http://127.0.0.1:8080/tareas | grep -i set-cookie
# esperado: mi_app_session=...; path=/; HttpOnly; SameSite=Lax
```

### 21.9 El toast de CSRF no aparece

**[v]** El backend emite `HX-Trigger: {"csrf":"..."}` y el navegador lo ignora: el listener tiene que estar en el layout (el panel se reemplaza con cada swap).

```bash
curl -sI -X POST http://127.0.0.1:8080/tareas | grep -i hx-trigger
curl -s http://127.0.0.1:8080/tareas | grep -c 'csrf.window'
# esperado: 1 o más
```

### 21.10 La página se ve sin estilos o sin interactividad

**Ya no es la red**: los assets están vendorizados (cap. 14), así que "el CDN no cargó" dejó de existir como causa. Quedan dos:

1. **El archivo no se sirve**: `curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/assets/daisyui-5.7.46.css` tiene que dar 200. Si da 404, `public/assets/` no viajó (revisá que no lo hayas metido al `.gitignore`).
2. **El SRI no matchea**: el navegador rechaza el archivo entero y solo lo dice en la consola (`chrome://inspect` con el cable). Causa típica: CRLF tras un checkout en Windows — para eso está el `.gitattributes` (cap. 5). Verificá con el one-liner PHP del cap. 14.1.

### 21.11 La app se muere cuando apagás la pantalla

**[t]** Android suspende el proceso: `termux-wake-lock` (cap. 17.1). Si muere al **cerrar la terminal**, es `tmux` (cap. 17.2). Son dos muertes distintas.

### 21.12 `Address already in use`

**[v]** Otro proceso tiene el puerto (`tmux ls`, `lsof -i :8080` o `ss -ltnp | grep 8080`). Matá el proceso viejo en vez de abrir un puerto nuevo cada vez.

### 21.13 HTMX se cuelga o dos requests se pisan

**[v]** `php -S` single-threaded: `PHP_CLI_SERVER_WORKERS=4` + `session.lazy_write=1` (caps. 17.4 y 18.2).

### 21.14 Cómo leer el log cuando todo lo anterior falló

**[v]** El error handler loguea **5xx como `error` y 4xx como `info`** a propósito.

```bash
tail -f storage/logs/app.log
mkdir -p storage/logs storage/cache storage/sessions   # si Monolog calla, es esto
```

### 21.15 "No puede conectarse": `localhost` no es `127.0.0.1`

**[v]** `php -S localhost:8080` puede quedar escuchando solo en IPv6 (`::1`) mientras el navegador pide `127.0.0.1`. **No uses `localhost` como bind**: `127.0.0.1` o `0.0.0.0`, que es lo que ya hace `php -S 0.0.0.0:8080 -t public` (cap. 17).

### 21.16 Clases `is-drawer-*` sin efecto

**[v]** El ejemplo plegable de la doc de daisyUI usa `is-drawer-open:`/`is-drawer-close:`. Esas variantes **no existen en el CSS linkeado** (cero ocurrencias en `daisyui-5.7.46.css`; necesitan el compilador con daisyUI como plugin). Si las copiás, quedan como clases muertas que *parecen* funcionar. Por eso el collapse de esta guía es Alpine + utilities estándar (cap. 15).

### 21.17 El icono de plegado no cambia de dirección

**[v]** `rotate-180` de Tailwind v4 usa la propiedad CSS `rotate`, que **se suma** al `transform` manual (180+180=360): con ambos, el icono vuelve a `<<` siempre. Una sola fuente de verdad: la rotación sale del CSS pre-pintado (`data-sidebar-mini`), nunca de una clase Alpine a la vez.

### 21.18 Phinx se queja del `.env` o la tabla no existe

**[v]** `Unable to read any of the environment file(s)` en un clon sin `.env`: `phinx.php` ya usa `safeLoad()`. Y `no such table` = corré `composer migrate` (cap. 21.3).

### 21.19 Flash animado al recargar con el menú plegado

**[v]** Alpine aplica el estado de `localStorage` post-paint con la transición activa: el sidebar se ve abrir y cerrarse. La fix es pre-pintar (script + CSS en `<head>`, cap. 15): misma receta que el flash del tema oscuro.

### 21.20 `requires php-64bit` al instalar en el teléfono

**[v]** Este es el error literal que te escupe el solver en 32 bits (armv7l), y el único de esta guía que NO se arregla en el teléfono:

```
Problem 1
  - Root composer.json requires robmorgan/phinx ^0.16.12 -> satisfiable by robmorgan/phinx[0.16.12].
  - robmorgan/phinx 0.16.12 requires php-64bit >=8.1 -> the php-64bit package is disabled by your platform config.
```

Leelo bien: no es tu PHP viejo ni tu mirror. Phinx 0.14/0.15/0.16 exigen `php-64bit` por arquitectura (verificado paquete por paquete contra Packagist; la 0.16.12 ni pide `php` a secas). En 32 bits es imposible, punto. Y NO lo arreglés con `composer update` en el teléfono: el solver no puede inventar un Phinx 0.16 de 32 bits que no existe.

La fix vive en el repo, no en tu teléfono (cap. 4: pin `^0.13`, última línea sin el requisito):

```bash
cd ~/slim4-homemade-framework
git pull
composer install:termux
composer migrate
php -S 0.0.0.0:8080 -t public
```

Si el `install` te sugiere `update`, es porque tu clon es anterior al fix: el `pull` lo resuelve. Regla de oro que este bug deja escrita: **en 32 bits el teléfono nunca resuelve dependencias** — instala (`install`) lo que se resolvió en 64 bits y vino en el lock. El `update` en Termux no es una herramienta, es el síntoma de que algo hay que traer de otro lado.

### 21.21 `Invalid CSRF storage` al bootear en tests (o al registrar rutas)

**[v]** Mensaje completo: `Invalid CSRF storage. Use session_start() before instantiating the Guard middleware or provide array storage.` Pasa si resolvés `Guard` del container al **registrar** rutas (como hacía el cierre de `/` para el form de logout): al construirse exige sesión iniciada y en registro todavía no hay ninguna. La fix es resolverlo **en request**, dentro del cierre (cap. 9) — en ejecución la sesión ya la abrió `SessionMiddleware`.

### 21.22 Suite roja de golpe tras proteger una ruta

**[v]** Protegés `/tareas` con `RequireAuth` y 12 tests se ponen rojos con 303: no es regresión, es la puerta funcionando. Los tests viejos tienen que entrar logueados (inyectar `$_SESSION['user_id']` en `setUp()` + limpiar en `tearDown()`); la auth en sí se testea en `AuthTest`, no ahí. Si un test de `/tareas` falla con 303 después de un cambio de middleware, lo primero es mirar la sesión, no el controlador.

### 21.23 `Duplicate migration` con versión `2147483647`

**[v]** Si tu error dice `has the same version as "2147483647"` (precedido de warnings `The float-string "2026..." is not representable as an int, cast occurred ... Util.php on line 93`), **no tenés archivos duplicados**: tenés saturación de enteros de 32 bits. Phinx castea el prefijo `YYYYMMDDHHMMSS` a `int`; en `armv7l` todo prefijo moderno vale `PHP_INT_MAX`, así que las N migraciones son "la misma" para el tracker. La fix es versiones secuenciales (`001_`, `002_`...), que ya trae el repo (cap. 11.1): `git pull` + `composer migrate`. Si el duplicado es entre dos archivos con el MISMO prefijo creado por vos (dos `create` en el mismo segundo), renombrá a prefijo único respetando el orden — pero en 32 bits renombrá siempre, no solo en colisión.

### 21.24 `The process ... exceeded the timeout of 300 seconds` al levantar el servidor

**[v]** Si el servidor de dev se muere solo a los 5 minutos y ves `The process "php -S 0.0.0.0:8080 -t public" exceeded the timeout of 300 seconds`, no lo mató PHP ni Android: **lo mató Composer**. `composer run-script` ejecuta cada script como subproceso con un `process-timeout` configurable, cuyo default son 300 s (`composer config process-timeout` → `300`). Al vencer, el proceso hijo se mata y el servidor queda muerto aunque tu terminal siga viva.

La fix no es subir el timeout: es no envolver un servidor de larga vida en un runner de scripts. Directo, sin intermediario:

```bash
php -S 0.0.0.0:8080 -t public
```

Por eso el repo ya no tiene `composer serve` funcional: quedó como guarda (`scripts/serve.php`) que aborta con esta explicación. Si por alguna razón necesitás forzar el camino viejo, `COMPOSER_PROCESS_TIMEOUT=0` desactiva el límite — pero entonces el servidor vive dentro de Composer igual, con una capa de subproceso que no aporta nada (y los `Ctrl+C` se comportan peor).

## 22. Cómo verificar esta guía vos mismo

Todo lo que la guía afirma es reproducible. Los capítulos 4 a 20 son código y se **ejecutan**; los capítulos 1 a 3 y 17 a 19 son afirmaciones sobre tu teléfono y **no** se verifican desde afuera. Por eso este capítulo está partido en dos.

### 22.1 Nivel A — cinco minutos, los chequeos que pagan

```bash
# 1. Dependencias: 2.x o la app revienta en la primera request (cap. 4)
composer show respect/validation | head -2
# esperado: versions : * 2.5.0

# 2. Suite completa: el esqueleto, no la demo (cap. 20)
composer test
# esperado: OK (la cifra la imprime este comando)

# 2b. Las copias de archivos de esta guía coinciden con el repo (intro)
composer docs:check
# esperado: Docs OK (... N copias de archivo verificadas)
# si falla: composer docs:sync y volvé a mirar el diff

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

# 5. La cookie de sesión tiene que viajar (cap. 8). Ojo: /tareas ahora exige
# login, así que la cookie se verifica contra /login (pública, con sesión).
curl -sI http://127.0.0.1:8080/login | grep -i set-cookie
# esperado: mi_app_session=...; path=/; HttpOnly; SameSite=Lax

# 5b. La puerta (cap. 9.2): sin login, /tareas no muestra nada.
curl -s -o /dev/null -w "%{http_code} %{redirect_url}\n" http://127.0.0.1:8080/tareas
# esperado: 303 .../login
curl -sI -H "HX-Request: true" http://127.0.0.1:8080/tareas | grep -i hx-redirect
# esperado: HX-Redirect: /login (200, no 303: el swap no sigue redirects)
```

Chequeos extra del frontend vendorizado (cap. 14) y el login (cap. 9.2):

```bash
curl -s http://127.0.0.1:8080/login | wc -c
# esperado: ~10910 (varía unos bytes con cada token CSRF).
# Lo que no puede salir es 0.
curl -s http://127.0.0.1:8080/login | grep -c 'csrf.window'
# esperado: 1 o más — el listener de CSRF tiene que estar en el layout
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8080/assets/daisyui-5.7.46.css
# esperado: 200
curl -s http://127.0.0.1:8080/ | wc -c
# esperado: ~12686 (varía con los tokens)
```

Y el flujo de login punta a punta con cookie-jar (cap. 9.2) — la prueba que el Nivel A no cubre pero vos sí deberías hacer una vez:

```bash
curl -s -c jar.txt http://127.0.0.1:8080/login -o login.html
# extraé csrf_name/csrf_value del HTML y:
curl -s -b jar.txt -c jar.txt --data-urlencode "csrf_name=..." \
  --data-urlencode "csrf_value=..." --data-urlencode "email=TU_EMAIL" \
  --data-urlencode "password=TU_CLAVE" --data-urlencode "remember=1" \
  -i http://127.0.0.1:8080/login | grep -E "^(HTTP|Location|Set-Cookie)"
# esperado: 303, Location: /tareas, Set-Cookie de sesión + remember=... (HttpOnly, Max-Age)
curl -s -b jar.txt http://127.0.0.1:8080/tareas | wc -c
# esperado: >0 (con jar logueado; sin jar es el 303 del chequeo 5b)
```

### 22.2 Nivel B — cuarenta y cinco minutos, el que de verdad importa

La guía se verificó contra un proyecto de referencia (el código es correcto), pero eso no garantiza que no haya un salto raro o un comando que haya que improvisar. Para detectarlo, sé un lector ciego de tu propia guía:

```bash
mkdir -p ~/verificacion && cd ~/verificacion
```

Seguí los capítulos 1 a 20 **de corrido, sin abrir el proyecto de referencia**. Anotá cada comando que tengas que improvisar (es el hueco), cada error que no esté en el capítulo 21 (sumalo) y cada paso que no entiendas sin contexto. Si no anotaste nada, la guía está completa.

### 22.3 Nivel C — el script que usé para encontrar los huecos

Compara cada línea no trivial de tu proyecto contra el texto de la guía y te dice qué no está documentado:

```bash
G=guia-stack-php-termux.md
for f in $(find app config routes tests resources bootstrap -type f \
           \( -name '*.php' -o -name '*.twig' \) 2>/dev/null); do
  miss=$(grep -vE '^\s*(//|\*|#|\{#)' "$f" | grep -vE '^\s*$' | sed 's/^\s*//' \
         | while read -r l; do grep -qF -- "$l" "$G" || echo x; done | wc -l)
  [ "$miss" -gt 0 ] && echo "$f: $miss lineas NO documentadas"
done
```

Dos detalles que no son opcionales: el `--` en `grep -qF --` (sin él, toda línea que empiece con `->` la interpreta como opción) y el descarte de comentarios a propósito (**el script no verifica comentarios, solo código**).

Salida esperada: **nada**. Y el alcance honesto: el `find` cubre `app`, `config`, `routes`, `tests`, `resources` y `bootstrap` — `composer.json`, `.env`, `.gitignore` y `.gitattributes` están fuera del escaneo y se verifican a ojo contra los caps. 4 y 5. Los assets vendorizados (`.js`/`.css`) también están fuera: no se copian bytes a una guía, se verifican con el SRI del cap. 14.1.

### 22.4 El chequeo de cinco segundos que sí depende de tu teléfono

```bash
uname -m                        # armv7l = 32-bit
pkg install php -y              # ¿existe php para tu arquitectura?
php -m | grep -i sqlite         # pdo_sqlite y sqlite3 tienen que aparecer
php -r "echo ini_get('memory_limit'), PHP_EOL;"
```

Si `pkg install php` responde `Unable to locate package`, volvé al 21.1.

### 22.5 Lo que esta guía NO puede verificar por vos

| No verificado | Por qué | Cómo lo cerrás |
|---|---|---|
| Que `pkg` tenga paquetes `arm` | Estado de un repo ajeno y cambiante | 22.4, o 21.1 si falla |
| Que funcione `termux-wake-lock` | Necesita Android suspendiendo el proceso | 21.11, en el teléfono |
| Consumo real de RAM bajo OOM killer | Es el OOM killer del kernel, no PHP | Cap. 18.1, con `echo "exit=$?"` |
| Que SQLite ande bien con WAL en Android | El backup tiene que llevar `-wal` y `-shm` | Cap. 18.6 |
| Render visual del shell (sidebar, temas) | Sin ojo no hay verificación de diseño | Abrir `/` y `/tareas` en el teléfono, claro y oscuro |

Todo lo demás — lógica PHP, pipeline, sesiones, CSRF, tests, headers, SRI, contratos HTMX — está verificado por ejecución, y el Nivel A lo reproduce en cinco minutos.

## 23. Archivos que existen pero NO se replican

Para que el cruce `git ls-files` vs. esta guía cierre sin fantasmas, esto es lo que hay en el repo y por qué no tiene capítulo con código para copiar:

| Archivo(s) | Por qué no se replica |
|---|---|
| `composer.lock`, `vendor/` | El lock se genera con `composer require` al seguir los caps. 4 y 20; `vendor/` se instala, nunca se escribe a mano. El lock SÍ viaja en git (cap. 18.1), vendor nunca. |
| `.env` | Local y gitignored: se **genera** con `cp .env.example .env` (cap. 5), no se copia de esta guía. |
| `database/database.sqlite` (+ `-wal`/`-shm`) | Lo crea la app sola (cap. 6) y el schema lo pone `composer migrate` (cap. 11). Nunca va a git. |
| `storage/` | Lo crea el bootstrap (cap. 7). Nunca va a git. |
| `odd/tasks/*.md` | Registros de trabajo del desarrollo (decisiones, evidencia, próximos pasos). Útiles para entender *por qué*, innecesarios para replicar el *qué*. |
| `README.md` | La puerta de entrada del repo (arranque, receta CRUD, comandos). Se lee, no se programa — igual que esta guía. |
| `.phpunit.cache/` | Cache local de PHPUnit. |
| `docs/guia-stack-php-termux.md` | Esta guía. Se lee, no se programa. |
| `app/Support/Docs/CodeBlocks.php`, `scripts/docs-check.php` (parte nueva), `scripts/docs-sync.php`, `tests/Support/CodeBlocksTest.php` | El candado anti-pudrición de esta misma guía: parsean los fences, comparan cada copia contra el repo y las reescriben. No se replican porque son la herramienta que verifica este documento, no parte del framework (intro, y `composer docs:check`). |

> Todo lo demás (`app/Models/User.php`, `RememberToken.php`, `app/Support/Auth.php`, `RememberMe.php`, `CsrfTokens.php`, los dos middlewares de auth, `AuthController.php`, `resources/views/auth/login.twig`, `scripts/seed-admin.php`, `tests/Http/AuthTest.php`, las dos migraciones de auth) tiene capítulo con código copiable: caps. 7.3, 8.1, 9.2, 10, 11.1, 15.2 y 20.9. El cruce del cap. 22.3 los cubre.






