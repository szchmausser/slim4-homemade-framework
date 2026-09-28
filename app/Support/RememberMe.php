<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\RememberToken;
use App\Models\User;

/**
 * Remember-me con split-token. ESTE es el archivo security-critical del login:
 * cualquier cambio acá merece relectura y sus tests (AuthTest) en verde.
 *
 * - Cookie `selector:validator`. En base: selector en claro (lookup) y SOLO
 *   EL HASH del validator (bcrypt, como un password). Jamás el token en claro.
 * - Rotación en cada uso: el token presentado muere y nace otro. Achica la
 *   ventana de replay de un token copiado.
 * - Vencimiento normal: se borra esa fila y la cookie, sin alarma.
 * - Selector conocido + validator que NO matchea: posible robo → se queman
 *   TODOS los tokens del usuario y se borra la cookie.
 * - La cookie es HttpOnly + SameSite=Lax, igual que la de sesión. `secure`
 *   queda en false por el mismo motivo (solo HTTPS real): ver cap. 19.
 */
final class RememberMe
{
    public const COOKIE = 'remember';

    public const TTL = 2592000; // 30 días, en segundos

    /**
     * Crea un token para el usuario y devuelve el par crudo "selector:validator"
     * para la cookie. El validator sale de acá una sola vez: no se loguea.
     */
    public static function create(int $userId): string
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        RememberToken::create([
            'selector'         => $selector,
            'user_id'          => $userId,
            'hashed_validator' => password_hash($validator, PASSWORD_DEFAULT),
            'expires_at'       => time() + self::TTL,
        ]);

        return $selector . ':' . $validator;
    }

    /** Header Set-Cookie completo para el par, o para borrarlo con $clear. */
    public static function cookieHeader(string $pair = '', bool $clear = false): string
    {
        if ($clear) {
            return self::COOKIE . '=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0';
        }

        return self::COOKIE . '=' . $pair . '; Path=/; HttpOnly; SameSite=Lax; Max-Age=' . self::TTL;
    }

    /**
     * Intenta sesión desde la cookie. Devuelve el header Set-Cookie a aplicar
     * en la respuesta de salida (rotación o limpieza), o null si no hay nada
     * que hacer. Nunca lanza: un token roto simplemente no loguea.
     */
    public static function resumeFromCookie(): ?string
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if ($raw === '' || !str_contains($raw, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $raw, 2);

        $row = RememberToken::query()->where('selector', $selector)->first();
        if ($row === null) {
            return null; // selector desconocido: nada que quemar, solo ignorar
        }

        $user = User::find($row->user_id);
        if ($user === null) {
            $row->delete();
            return self::cookieHeader(clear: true);
        }

        if ((int) $row->expires_at <= time()) {
            $row->delete(); // vencimiento normal: sin alarma, solo limpieza
            return self::cookieHeader(clear: true);
        }

        if (!password_verify($validator, $row->hashed_validator)) {
            // Posible robo: el selector existe pero el secreto no matchea.
            RememberToken::query()->where('user_id', $user->id)->delete();
            return self::cookieHeader(clear: true);
        }

        $row->delete();
        Auth::login($user->id);

        return self::cookieHeader(self::create($user->id));
    }

    public static function clearUser(int $userId): void
    {
        RememberToken::query()->where('user_id', $userId)->delete();
    }
}
