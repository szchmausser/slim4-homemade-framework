<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Framework\Support\Env;

/**
 * Remember-me con split-token. ESTE es el archivo security-critical del login:
 * cualquier cambio aquí merece relectura y sus tests (AuthTest) en verde.
 *
 * - Cookie `selector:validator`. En base: selector en claro (lookup) y SOLO
 *   EL HASH del validator (bcrypt, como un password). Jamás el token en claro.
 * - Rotación en cada uso: el token presentado muere y nace otro, las dos
 *   sentencias en UNA transacción (ver `resumeFromCookie`). Achica la ventana
 *   de replay de un token copiado.
 * - Vencimiento normal: se borra esa fila y la cookie, sin alarma.
 * - Selector conocido + validator que NO matchea: posible robo → se queman
 *   TODOS los tokens del usuario y se borra la cookie.
 * - La cookie es HttpOnly + SameSite=Lax, igual que la de sesión. `Secure`
 *   se activa con SESSION_SECURE=true (HTTPS real): ver cap. 19.
 */
final class RememberMe
{
    public const COOKIE = 'remember';

    public const TTL = 2592000; // 30 días, en segundos

    public const ROTATION_GRACE = 3600; // 1 hora de tombstone

    /**
     * Crea un token para el usuario y devuelve el par crudo "selector:validator"
     * para la cookie. El validator sale de aquí una sola vez: no se loguea.
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
        $secure = Env::bool('SESSION_SECURE')
            ? '; Secure'
            : '';

        if ($clear) {
            return self::COOKIE . '=; Path=/' . $secure . '; HttpOnly; SameSite=Lax; Max-Age=0';
        }

        return self::COOKIE . '=' . $pair . '; Path=/' . $secure
            . '; HttpOnly; SameSite=Lax; Max-Age=' . self::TTL;
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

        self::pruneRotated();

        $row = RememberToken::query()->where('selector', $selector)->first();
        if ($row === null) {
            return null; // selector desconocido: nada que quemar, solo ignorar
        }

        if ($row->rotated_at !== null) {
            // Replay de un token que ya se rotó: el ladrón tiene una copia
            // vieja. Se quema la familia completa, incluido el token nuevo.
            RememberToken::query()->where('user_id', (int) $row->user_id)->delete();
            return self::cookieHeader(clear: true);
        }

        $user = UserProvider::find((int) $row->user_id);
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
            RememberToken::query()->where('user_id', (int) $user->getKey())->delete();
            return self::cookieHeader(clear: true);
        }

        $userId = (int) $user->getKey();

        // Rotación atómica (S8). El delete de la fila presentada y el insert
        // del reemplazo tienen que entrar JUNTOS: si el proceso cae entre las
        // dos sentencias, la fila vieja ya no está y la nueva no existe, la
        // cookie del browser deja de resolver y el usuario queda deslogueado en
        // silencio (no hay error que ver: un selector desconocido simplemente
        // se ignora). `transaction()` va por la MISMA conexión que usan los
        // modelos —Eloquent ya está booteado sobre este Capsule, ver
        // config/container.php—, así que el delete del modelo y el create del
        // reemplazo caen los dos adentro. La closure recibe esa conexión y no
        // la usa —los modelos ya están en ella—: el tipo está para dejar
        // explícito cuál es la conexión y que aquí no se abre una aparte.
        //
        // La sesión se abre DESPUÉS del commit a propósito: si la transacción
        // revierte, no queda una sesión abierta sin cookie que la respalde.
        // El par que sale de aquí es el mismo `selector:validator` de siempre y
        // el header lo arma `cookieHeader()`, que no cambió.
        //
        // Lo que este cambio NO trae es un test de la atomicidad, y no es un
        // olvido: la atomicidad no se puede verificar de forma directa sin
        // inyectar una falla justo entre las dos sentencias, y no hay hook
        // público para abortar ahí sin tocar el código de producción. AuthTest
        // fija el contrato observable —rotación, header idéntico, conexión sin
        // transacción abierta al salir— y no simula el corte.
        $pair = Capsule::connection()->transaction(
            static function (Connection $connection) use ($row, $userId): string {
                // Tombstone en vez de delete: si el token viejo reaparece,
                // `resumeFromCookie()` detecta el replay y quema la familia.
                $row->rotated_at = time();
                $row->save();

                return self::create($userId);
            }
        );

        Auth::login($userId);

        return self::cookieHeader($pair);
    }

    /**
     * Los tombstones no son eternos: pasada la ventana de gracia se borran.
     * Sin esto la tabla crecería con cada rotación.
     */
    public static function pruneRotated(): void
    {
        RememberToken::query()
            ->whereNotNull('rotated_at')
            ->where('rotated_at', '<', time() - self::ROTATION_GRACE)
            ->delete();
    }

    public static function clearUser(int $userId): void
    {
        RememberToken::query()->where('user_id', $userId)->delete();
    }
}
