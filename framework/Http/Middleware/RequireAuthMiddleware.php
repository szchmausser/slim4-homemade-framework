<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Auth\Auth;
use Framework\Http\Flash;
use Framework\Http\Url;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Puerta de las rutas protegidas: sin usuario en la sesión no se llega al
 * controlador.
 *
 * Se agrega por grupo de rutas (`->add(RequireAuthMiddleware::class)`) y no al
 * pipeline global, porque el login y la portada tienen que ser públicos. En htmx
 * una redirección no produce una navegación: el XHR la sigue y el HTML del login
 * terminaría cambiado dentro de la región, así que ahí responde 200 con
 * `HX-Redirect` para que el navegador abra la página completa.
 */
final class RequireAuthMiddleware implements MiddlewareInterface
{
    /**
     * Recibe la fábrica de respuestas porque al cortar el pipeline no hay respuesta
     * previa que modificar.
     */
    public function __construct(private ResponseFactoryInterface $responseFactory) {}

    /**
     * Deja pasar si hay sesión; si no, manda al login con el aviso que explica por
     * qué.
     *
     * El flash se escribe antes del 303 y lo consume la pantalla de acceso en el GET
     * siguiente. La rama htmx usa `HX-Redirect` y no `Location` porque el XHR sigue
     * el redirect y el login se cambiaría dentro de la región.
     */
    public function process(Request $request, Handler $handler): Response
    {
        if (Auth::check()) {
            return $handler->handle($request);
        }

        if ($request->getHeaderLine('HX-Request') !== '') {
            return $this->responseFactory->createResponse(200)
                ->withHeader('HX-Redirect', Url::path('/login'));
        }

        Flash::set('error', 'Inicia sesión para continuar.');

        return $this->responseFactory->createResponse(303)
            ->withHeader('Location', Url::path('/login'));
    }
}
