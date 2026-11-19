<?php

declare(strict_types=1);

namespace Framework\Support;

/**
 * Lectura uniforme de la configuración por entorno.
 *
 * Con `variables_order=GPCS` (el valor por defecto de varios SAPIs) `$_ENV` puede
 * quedar vacío aunque la variable exista, y un contenedor o un servicio systemd
 * puede dejarlo solo en `getenv()` o en `$_SERVER`. Este helper mira las tres
 * fuentes en orden fijo —`$_ENV`, `$_SERVER`, `getenv()`— para que el mismo nombre
 * devuelva lo mismo se ejecute donde se ejecute.
 */
final class Env
{
    /**
     * Valor de la variable, o el default si no está definida o está vacía.
     *
     * Un string vacío cuenta como ausente: `SESSION_NAME=` en el `.env` no debe
     * producir un nombre de sesión vacío. Un valor que no es escalar también cae
     * en el default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Interpreta la variable como booleano (`true`, `1`, `on`, `yes`).
     *
     * Ausente o vacía usa el default; presente pero no reconocida se resuelve a
     * false, no al default: el valor está ahí y dice que no.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Entero de la variable, o el default si no está definida o no es numérica.
     *
     * No valida rangos: los límites los aplica quien la lee.
     */
    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }
}
