<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Resuelve el modelo de usuario de la aplicación para todo el código de auth.
 *
 * El framework no conoce `App\Models\User`: bootstrap/app.php lee
 * `config/auth.php` y llama a `configure()`, así que cambiar de modelo de usuario
 * es cambiar esa configuración. Auth, RememberMe y el login consultan a través de
 * aquí, y por eso hay un solo lugar donde se decide qué tabla es la de usuarios.
 */
final class UserProvider
{
    /** @var class-string<Model>|null */
    private static ?string $model = null;

    /**
     * Fija la clase que se va a consultar.
     *
     * La llama el bootstrap una vez por proceso, antes de construir el container:
     * cualquier pieza de auth que se resuelva después ya encuentra el modelo.
     *
     * @param class-string<Model> $model
     */
    public static function configure(string $model): void
    {
        self::$model = $model;
    }

    /**
     * La clase configurada, o una excepción si el bootstrap no la fijó.
     *
     * Prefiere lanzar a caer en un modelo por defecto: sin configuración no hay
     * tabla de usuarios que consultar y el error señala el paso que falta.
     *
     * @return class-string<Model>
     */
    public static function model(): string
    {
        if (self::$model === null) {
            throw new \RuntimeException(
                'UserProvider no configurado: carga config/auth.php en bootstrap/app.php.'
            );
        }

        return self::$model;
    }

    /**
     * Usuario por clave primaria, o null.
     *
     * Es la lectura que hace `Auth::user()` en cada render.
     */
    public static function find(int $id): ?Model
    {
        $model = self::model();

        return $model::query()->find($id);
    }

    /**
     * Usuario por email, o null.
     *
     * La usa el login para comparar el hash de la clave; la comparación exacta
     * queda a cargo de la base, que decide mayúsculas según su colación.
     */
    public static function findByEmail(string $email): ?Model
    {
        $model = self::model();

        return $model::query()->where('email', $email)->first();
    }
}
