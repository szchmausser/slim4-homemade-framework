<?php

declare(strict_types=1);

use Framework\Http\Middleware\LocaleMiddleware;
use Framework\Http\Middleware\RememberMeMiddleware;
use Framework\Http\Middleware\SecurityHeadersMiddleware;
use Framework\Http\Middleware\SessionMiddleware;
use Framework\I18n\Trans;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Exception\HttpException;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Framework\Support\Env;

return function (App $app) {
    $container = $app->getContainer();
    $debug = Env::bool('APP_DEBUG');
    $isLocal = Env::get('APP_ENV', 'local') === 'local';

    // Valida el token CSRF
    $app->add(Guard::class);

    // Parsea form-urlencoded/JSON antes de que Guard lo necesite
    $app->addBodyParsingMiddleware();

    // Inyecta helpers de ruta a Twig
    $app->add(TwigMiddleware::create($app, $container->get(Twig::class)));

    // Resuelve la ruta solicitada
    $app->addRoutingMiddleware();

    // Entre sesión y routing: resume remember-me con la sesión ya abierta.
    // En orden de ejecución corre justo después de SessionMiddleware.
    $app->add(RememberMeMiddleware::class);

    // Locale por request. Necesita la sesión ya abierta (ahí se recuerda la
    // elección del visitante) y tiene que fijar el idioma antes de que se
    // renderice cualquier vista. add() es LIFO: se registra ANTES de
    // SessionMiddleware para ejecutarse DESPUÉS de ella. Orden real de
    // ejecución, de afuera hacia adentro: SecurityHeaders → error → Session →
    // Locale → RememberMe → routing → Twig → BodyParsing → Guard → ruta.
    $app->add(LocaleMiddleware::class);

    // Arranca la sesión nativa para toda la app
    $app->add(SessionMiddleware::class);

    // Captura errores de todas las capas de abajo
    $errorMiddleware = $app->addErrorMiddleware($debug, true, true);

    // Los headers tienen que ENVOLVER al error middleware,
    // porque es él quien genera las respuestas 404 y 500 — y esas también
    // necesitan los headers. Como add() es LIFO, lo que se agrega ÚLTIMO es lo
    // más externo: por eso esta línea va aquí abajo y no arriba del todo.
    $app->add(SecurityHeadersMiddleware::class);

    // Whoops solo en desarrollo. En producción el mensaje va al log y la
    // respuesta es genérica: sin registro, un error es indistinguible de que
    // la app simplemente no funcionara.
    $whoops = null;
    if ($debug && $isLocal && class_exists(\Whoops\Run::class)) {
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
            // que seguir siendo 404: si devuelves 500, los navegadores, los
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
                    $twig = $container->get(Twig::class);

                    // El locale del error. Este handler corre FUERA de
                    // `LocaleMiddleware` (el error middleware se agrega después
                    // y envuelve a la sesión y al locale) y la respuesta que
                    // arma es nueva, así que no hereda ni el idioma ni el
                    // `Vary` que el middleware puso en la suya. Se resuelve acá
                    // con la MISMA función —misma precedencia— y el `Vary` se
                    // agrega a mano abajo. Si la excepción fue tan temprana que
                    // no hay sesión ni cabecera usable, el resolutor cae en
                    // `APP_LOCALE`: la página de error sale en el idioma por
                    // defecto, nunca en el que dejó la petición anterior.
                    $twig->getEnvironment()->addGlobal(
                        'app_locale',
                        LocaleMiddleware::apply($request, $container->get(LocaleAwareInterface::class))
                    );

                    // El mensaje sale del catálogo y del CÓDIGO de estado, no
                    // del texto de la excepción: el del vendor viene en inglés
                    // ("Not found.") y puede arrastrar detalles internos. El
                    // código queda a la vista para que soporte lo pueda pedir.
                    // Un código sin texto propio cae al genérico, como antes.
                    $message = Trans::t(match ($status) {
                        404 => 'error.not_found',
                        403 => 'error.forbidden',
                        405 => 'error.method_not_allowed',
                        400, 419 => 'error.bad_request',
                        default => 'error.server',
                    });

                    $template = $request->hasHeader('HX-Request')
                        ? 'pages/errors/_htmx.twig'
                        : 'pages/errors/error.twig';

                    $rendered = $twig->render(
                        $app->getResponseFactory()->createResponse($status),
                        $template,
                        [
                            'code'    => $status,
                            'message' => $message,
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
                    // Si la plantilla de error también falla, no conviene lanzar otra
                    // excepción desde aquí: se entra en un bucle. Degradamos a texto.
                    $logger->critical('No se pudo renderizar la vista de error', [
                        'exception' => $renderFailure,
                    ]);
                    $body = 'Error ' . $status . '.';
                }
            }

            $response = $app->getResponseFactory()->createResponse($status);
            $response->getBody()->write($body);

            // Ver el comentario del locale, arriba: esta respuesta no pasó por
            // `LocaleMiddleware`, así que sin el `Vary` explícito un caché
            // guarda el 404 en un idioma y lo sirve a todos.
            return $response->withHeader('Vary', 'Accept-Language');
        }
    );
};
