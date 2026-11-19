<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Framework\Support\Env;

/**
 * Cabeceras de seguridad que este stack puede sostener.
 *
 * Es el middleware más externo del pipeline: envuelve al error middleware para que
 * las páginas 404 y 500 también salgan con las cabeceras. Se registra al final en
 * `config/middleware.php` porque `add()` es LIFO y lo último agregado es lo primero
 * en ejecutarse.
 *
 * No incluye Content-Security-Policy: el shell usa JS inline y Alpine/Tailwind en el
 * navegador, y una CSP estricta los rompería.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * Agrega las cabeceras a la respuesta que devuelve el resto del pipeline y, con
     * SESSION_SECURE, HSTS.
     */
    public function process(Request $request, Handler $handler): Response
    {
        $response = $handler->handle($request);

        $response = $response
            // El navegador deja de adivinar el tipo: sin esto, un archivo servido
            // como octet-stream que en realidad es HTML se ejecuta. Es la cabecera
            // más barata de todo el set.
            ->withHeader('X-Content-Type-Options', 'nosniff')

            // Nadie puede embeber la app en un iframe y hacerte hacer clic sin
            // querer (clickjacking). La alternativa moderna sería
            // `frame-ancestors 'none'`, pero eso exige adoptar una CSP entera.
            ->withHeader('X-Frame-Options', 'DENY')

            // Los assets son locales con SRI, pero la app puede enlazar a sitios
            // externos. Sin esto, cada salida manda la URL completa —con paths y
            // query string— en el header Referer.
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')

            // La app no usa cámara, micrófono ni geolocalización: negarlos por
            // defecto evita que una dependencia o un iframe los pidan.
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS solo cuando la app se sirve por HTTPS. Se gatea con el mismo flag
        // que las cookies `Secure`: activarlo en desarrollo local deja al navegador
        // recordando un HTTPS que el servidor no habla.
        if (Env::bool('SESSION_SECURE')) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000'
            );
        }

        return $response;
    }
}
