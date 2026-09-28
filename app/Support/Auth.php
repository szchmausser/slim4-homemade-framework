<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Sesión de autenticación sobre la sesión nativa de PHP (que abre
 * SessionMiddleware). Solo guarda el id: el usuario se lee de la base en
 * cada request (una query indexada; si el usuario se borra, la sesión muere).
 */
final class Auth
{
    public static function id(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function user(): ?User
    {
        $id = self::id();
        return $id === null ? null : User::find($id);
    }

    /** Establece la sesión. Regenera el ID (fijación de sesión). */
    public static function login(int $id): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $id;
    }

    /**
     * Vacía la sesión y regenera el ID. No la destruye: el CSRF vive ahí y
     * el redirect siguiente lo necesita.
     */
    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
