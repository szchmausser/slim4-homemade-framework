<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Estado de autenticación de la petición, guardado en la sesión nativa que abre
 * SessionMiddleware.
 *
 * Guarda solo el id del usuario: el modelo se relee en cada request con
 * UserProvider, así que un usuario borrado deja la sesión sin efecto y ningún
 * dato suyo queda congelado en la sesión. Lo consultan RequireAuthMiddleware, el
 * render de los controladores y el handler de la portada; lo escribe el login.
 */
final class Auth
{
    /**
     * Id del usuario autenticado, o null si la petición es anónima.
     *
     * Lee `$_SESSION['user_id']` y lo castea a entero: la sesión es un array sin
     * tipo declarado, así que el valor no viene garantizado.
     */
    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    /**
     * ¿Hay usuario en la sesión? Es el atajo booleano que consulta
     * RequireAuthMiddleware para decidir si deja pasar la petición.
     */
    public static function check(): bool
    {
        return self::id() !== null;
    }

    /**
     * Modelo del usuario de la petición, o null si no hay sesión.
     *
     * Delega en UserProvider, que es quien sabe qué clase de usuario usa la app;
     * cuesta una query por clave primaria indexada.
     */
    public static function user(): ?Model
    {
        $id = self::id();
        return $id === null ? null : UserProvider::find($id);
    }

    /**
     * Marca la sesión como autenticada.
     *
     * Regenera el id de sesión antes de escribir el usuario: un id conocido de
     * antemano deja de servir (fijación de sesión). Anota `created_at` y
     * `last_activity`, que SessionMiddleware mide para el vencimiento absoluto y
     * por inactividad.
     *
     * @param int $id clave primaria del usuario que acaba de autenticarse
     */
    public static function login(int $id): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $now = time();
        $_SESSION['user_id'] = $id;
        $_SESSION['created_at'] = $now;
        $_SESSION['last_activity'] = $now;
    }

    /**
     * Borra los datos de la sesión y regenera el id, sin destruirla.
     *
     * La sesión sigue activa a propósito: el aviso que el controlador escribe
     * después de llamar aquí se guarda al cerrar el request, y el próximo GET
     * emite su par CSRF en esta misma sesión. `session_destroy()` cortaría las
     * dos cosas.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
