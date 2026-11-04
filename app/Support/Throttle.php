<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Limite de intentos de login por email + IP. No usa un modelo Eloquent:
 * las filas son descartables, se escriben y se cuentan en bloque, y no
 * necesitan eventos ni casts.
 *
 * - 5 fallos dentro de 15 minutos bloquean los intentos siguientes.
 * - La ventana se evalua contra attempted_at, no contra una fila de estado:
 *   no hay contador que se pueda quedar "pegado" si el proceso muere.
 * - El bloqueo es silencioso: el controlador responde lo mismo que ante
 *   credenciales invalidas, sin revelar que la cuenta esta throttled.
 */
final class Throttle
{
    public const MAX_ATTEMPTS = 5;

    public const WINDOW = 900; // 15 minutos, en segundos

    public static function hit(string $email, string $ip): void
    {
        // Oportunista: si nadie vuelve a fallar, la tabla no crece igual
        // porque solo se poda cuando hay actividad.
        self::prune();

        Capsule::table('login_attempts')->insert([
            'email'        => trim($email),
            'ip'           => $ip,
            'attempted_at' => time(),
        ]);
    }

    public static function tooMany(string $email, string $ip): bool
    {
        $count = Capsule::table('login_attempts')
            ->where('email', trim($email))
            ->where('ip', $ip)
            ->where('attempted_at', '>=', time() - self::WINDOW)
            ->count();

        return $count >= self::MAX_ATTEMPTS;
    }

    public static function clear(string $email, string $ip): void
    {
        Capsule::table('login_attempts')
            ->where('email', trim($email))
            ->where('ip', $ip)
            ->delete();
    }

    public static function prune(): void
    {
        Capsule::table('login_attempts')
            ->where('attempted_at', '<', time() - self::WINDOW)
            ->delete();
    }
}
