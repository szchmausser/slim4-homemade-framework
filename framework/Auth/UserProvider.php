<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Punto único donde el framework auth resuelve el modelo de usuario de la app.
 * La clase concreta se configura desde config/auth.php en el bootstrap: el
 * framework no importa App\Models\User.
 */
final class UserProvider
{
    /** @var class-string<Model>|null */
    private static ?string $model = null;

    /** @param class-string<Model> $model */
    public static function configure(string $model): void
    {
        self::$model = $model;
    }

    /** @return class-string<Model> */
    public static function model(): string
    {
        if (self::$model === null) {
            throw new \RuntimeException(
                'UserProvider no configurado: carga config/auth.php en bootstrap/app.php.'
            );
        }

        return self::$model;
    }

    public static function find(int $id): ?Model
    {
        $model = self::model();

        return $model::query()->find($id);
    }

    public static function findByEmail(string $email): ?Model
    {
        $model = self::model();

        return $model::query()->where('email', $email)->first();
    }
}
