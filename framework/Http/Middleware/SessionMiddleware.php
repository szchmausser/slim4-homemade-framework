<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Support\Env;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Abre la sesión nativa de PHP y aplica sus políticas de vencimiento.
 *
 * Corre antes que cualquier middleware que lea `$_SESSION` —la sesión de auth, el
 * remember-me y el almacén del Guard del CSRF— y una sola vez por petición: si la
 * sesión ya está activa no hace nada. Configura nombre, cookie y ruta de
 * almacenamiento desde el entorno, y vence las sesiones autenticadas por
 * inactividad o por antigüedad absoluta.
 */
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
     * Nombre de sesión saneado: PHP solo acepta letras, números, guion y guion
     * bajo.
     *
     * Un valor con espacios o `;` puede romper la cookie o inyectar atributos, y
     * uno vacío deja la sesión sin nombre; por eso hay un fallback fijo.
     */
    public static function sessionName(): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) Env::get('SESSION_NAME', 'mi_app_session'));

        return is_string($name) && $name !== '' ? $name : 'mi_app_session';
    }

    /**
     * Directorio de los archivos de sesión.
     *
     * `SESSION_SAVE_PATH` gana si está definido; si no, `storage/sessions` en la
     * raíz del proyecto, que el bootstrap crea en cada clon.
     */
    public static function savePath(): string
    {
        $configured = (string) Env::get('SESSION_SAVE_PATH', '');

        return $configured !== ''
            ? $configured
            : dirname(__DIR__, 3) . '/storage/sessions';
    }

    /**
     * Segundos de inactividad tolerados; `0` desactiva el vencimiento por
     * inactividad.
     */
    public static function idleTimeout(): int
    {
        return max(0, Env::int('SESSION_IDLE_TIMEOUT', self::DEFAULT_IDLE_TIMEOUT));
    }

    /**
     * Tope absoluto de vida de la sesión, en segundos; `0` lo desactiva.
     *
     * También se usa como `gc_maxlifetime` para que el recolector de PHP no borre
     * una sesión que la aplicación todavía considera válida.
     */
    public static function absoluteTimeout(): int
    {
        return max(0, Env::int('SESSION_ABSOLUTE_TIMEOUT', self::DEFAULT_ABSOLUTE_TIMEOUT));
    }

    /**
     * Arranca la sesión si nadie la abrió todavía y vence la que corresponda;
     * después delega en el resto del pipeline.
     *
     * La configuración se aplica antes de `session_start()` porque PHP no acepta
     * cambiar nombre ni cookie después, y el vencimiento se evalúa al final porque
     * los datos de la sesión recién están disponibles con `session_start()`.
     */
    public function process(Request $request, Handler $handler): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            $savePath = self::savePath();
            if (!is_dir($savePath)) {
                // El bootstrap crea `storage/sessions`, pero un path configurado
                // puede no existir todavía: se crea con 0700 y el `@` silencia el
                // fallo de creación.
                @mkdir($savePath, 0700, true);
            }

            session_name(self::sessionName());
            session_save_path($savePath);
            session_set_cookie_params(self::cookieParams());

            // Modo estricto: rechaza ids que PHP nunca emitió (fijación de sesión).
            // `use_only_cookies` mantiene el id fuera de la URL.
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            $absolute = self::absoluteTimeout();
            if ($absolute > 0) {
                // El recolector no debe borrar una sesión que la app todavía
                // considera válida; el vencimiento real lo aplica este middleware.
                ini_set('session.gc_maxlifetime', (string) $absolute);
            }

            session_start();
            $this->expireIfStale();
        }

        return $handler->handle($request);
    }

    /**
     * Vence la sesión por inactividad o por tope absoluto, y refresca la marca de
     * actividad.
     *
     * Solo se aplica a sesiones autenticadas: un formulario público abierto toda la
     * tarde no debería perder el CSRF por la mitad. Al vencer vacía los datos y
     * regenera el id, así el usuario vuelve a entrar.
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
