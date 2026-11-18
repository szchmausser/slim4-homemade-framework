<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Support\Env;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class SessionMiddleware implements MiddlewareInterface
{
    public const DEFAULT_IDLE_TIMEOUT = 7200;      // 2 horas

    public const DEFAULT_ABSOLUTE_TIMEOUT = 43200; // 12 horas

    /**
     * Parámetros de la cookie de sesión.
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
            'secure'   => Env::bool('SESSION_SECURE'),
        ];
    }

    /**
     * Nombre de sesión saneado: PHP solo acepta letras, números, guion y
     * guion bajo. Un valor con espacios o `;` puede romper la cookie o
     * inyectar atributos.
     */
    public static function sessionName(): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) Env::get('SESSION_NAME', 'mi_app_session'));

        return is_string($name) && $name !== '' ? $name : 'mi_app_session';
    }

    public static function savePath(): string
    {
        $configured = (string) Env::get('SESSION_SAVE_PATH', '');

        return $configured !== ''
            ? $configured
            : dirname(__DIR__, 3) . '/storage/sessions';
    }

    public static function idleTimeout(): int
    {
        return max(0, Env::int('SESSION_IDLE_TIMEOUT', self::DEFAULT_IDLE_TIMEOUT));
    }

    public static function absoluteTimeout(): int
    {
        return max(0, Env::int('SESSION_ABSOLUTE_TIMEOUT', self::DEFAULT_ABSOLUTE_TIMEOUT));
    }

    public function process(Request $request, Handler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            $savePath = self::savePath();
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0700, true);
            }

            session_name(self::sessionName());
            session_save_path($savePath);
            session_set_cookie_params(self::cookieParams());

            // Rechaza IDs de sesión que nunca se inicializaron (session fixation).
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            $absolute = self::absoluteTimeout();
            if ($absolute > 0) {
                // El GC no debe borrar una sesión que la app todavía considera
                // válida; el vencimiento real lo aplica este middleware.
                ini_set('session.gc_maxlifetime', (string) $absolute);
            }

            session_start();
            $this->expireIfStale();
        }

        return $handler->handle($request);
    }

    /**
     * Vencimiento por inactividad y tope absoluto. Solo se aplica a sesiones
     * autenticadas: un formulario público abierto toda la tarde no debería
     * perder el CSRF por la mitad.
     */
    private function expireIfStale(): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }

        $now = time();
        $idle = self::idleTimeout();
        $absolute = self::absoluteTimeout();

        $lastActivity = isset($_SESSION['last_activity']) ? (int) $_SESSION['last_activity'] : null;
        $createdAt = isset($_SESSION['created_at']) ? (int) $_SESSION['created_at'] : null;

        $idleExpired = $idle > 0 && $lastActivity !== null && ($now - $lastActivity) > $idle;
        $absoluteExpired = $absolute > 0 && $createdAt !== null && ($now - $createdAt) > $absolute;

        if ($idleExpired || $absoluteExpired) {
            $_SESSION = [];
            session_regenerate_id(true);

            return;
        }

        $_SESSION['last_activity'] = $now;
        $_SESSION['created_at'] ??= $now;
    }
}
