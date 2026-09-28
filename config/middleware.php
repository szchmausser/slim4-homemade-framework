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
