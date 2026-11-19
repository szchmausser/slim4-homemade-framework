<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Límite de intentos de login por email + IP.
 *
 * Trabaja con el query builder y no con un modelo: las filas son descartables, se
 * insertan y se cuentan en bloque, sin eventos ni casts. No hay contador que se
 * pueda quedar a medio actualizar: cada intento es una fila y la ventana se mide
 * contra `attempted_at`. El login responde lo mismo que ante credenciales
 * inválidas, así que el bloqueo no revela que la cuenta está frenada.
 */
final class Throttle
{
    /** Fallos por email + IP dentro de la ventana antes de bloquear. */
    public const MAX_ATTEMPTS = 5;

    /** Fallos por IP dentro de la ventana, sin importar la cuenta. */
    public const MAX_ATTEMPTS_PER_IP = 20;

    /** Ventana de conteo: 15 minutos, en segundos. */
    public const WINDOW = 900;

    private static function normalizeEmail(string $email): string
    {
        // El límite cuenta cuentas, no formas de escribir el email: `A@B` y
        // `a@b` son la misma.
        return mb_strtolower(trim($email));
    }

    /**
     * Anota un intento fallido del par email + IP y de paso poda la ventana
     * vencida.
     *
     * La poda viaja en el camino de escritura: si nadie falla la tabla no crece,
     * así que no hace falta una limpieza programada.
     *
     * @param string $email el que escribió el usuario, se normaliza aquí
     * @param string $ip    origen del intento, tal como lo ve el servidor
     */
    public static function hit(string $email, string $ip): void
    {
        self::prune();

        Capsule::table('login_attempts')->insert([
            'email'        => self::normalizeEmail($email),
            'ip'           => $ip,
            'attempted_at' => time(),
        ]);
    }

    /**
     * ¿El par email + IP llegó al máximo de fallos dentro de la ventana?
     *
     * El login lo consulta antes de buscar al usuario y de gastar bcrypt: una
     * cuenta frenada no llega a comparar el hash ni a delatar si el email existe.
     */
    public static function tooMany(string $email, string $ip): bool
    {
        $count = Capsule::table('login_attempts')
            ->where('email', self::normalizeEmail($email))
            ->where('ip', $ip)
            ->where('attempted_at', '>=', time() - self::WINDOW)
            ->count();

        return $count >= self::MAX_ATTEMPTS;
    }

    /**
     * Mismo techo, contado solo por IP.
     *
     * Corta a quien prueba muchas cuentas desde un mismo origen: así nunca activa
     * el límite por email, que mira una cuenta a la vez.
     */
    public static function tooManyByIp(string $ip): bool
    {
        $count = Capsule::table('login_attempts')
            ->where('ip', $ip)
            ->where('attempted_at', '>=', time() - self::WINDOW)
            ->count();

        return $count >= self::MAX_ATTEMPTS_PER_IP;
    }

    /**
     * Borra los fallos del par email + IP.
     *
     * El login lo llama al autenticar, para que el usuario legítimo no arrastre su
     * propio historial. No toca las filas de otras IP ni de otras cuentas.
     */
    public static function clear(string $email, string $ip): void
    {
        Capsule::table('login_attempts')
            ->where('email', self::normalizeEmail($email))
            ->where('ip', $ip)
            ->delete();
    }

    /** Borra los intentos más viejos que la ventana; es idempotente. */
    public static function prune(): void
    {
        Capsule::table('login_attempts')
            ->where('attempted_at', '<', time() - self::WINDOW)
            ->delete();
    }
}
