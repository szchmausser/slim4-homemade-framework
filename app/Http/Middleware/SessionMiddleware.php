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
