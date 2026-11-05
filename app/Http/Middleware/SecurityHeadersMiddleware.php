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
