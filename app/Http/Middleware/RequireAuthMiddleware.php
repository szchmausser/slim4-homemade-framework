<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth;
use App\Support\Flash;
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
                ->withHeader('HX-Redirect', '/login');
        }

        Flash::set('error', 'Iniciá sesión para continuar.');

        return $this->responseFactory->createResponse(303)
            ->withHeader('Location', '/login');
    }
}
