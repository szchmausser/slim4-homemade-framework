<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

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
