<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Lectura uniforme de configuracion por entorno.
 *
 * `variables_order=GPCS` (el default de muchos SAPIs, incluido Termux) deja
 * `$_ENV` vacio aunque la variable exista. Dotenv puebla `$_ENV`, pero un
 * contenedor o un systemd unit solo deja la variable en `getenv()`/`$_SERVER`.
 * Este helper mira los tres sin cambiar la precedencia historica: primero
 * `.env` ($_ENV), despues $_SERVER y por ultimo el entorno real.
 */
final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }
}
