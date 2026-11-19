<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Framework\Support\Env;

/**
 * Mantiene la sesión entre visitas con un par selector/validator en cookie.
 *
 * La cookie lleva `selector:validator`. El selector es la clave de la fila y
 * viaja en claro porque es el índice de búsqueda; el validator se guarda solo
 * como hash bcrypt, así que una copia de la tabla no alcanza para armar una
 * cookie válida. Cada uso rota el par: el anterior queda como tombstone y, si
 * reaparece, se interpreta como robo y se queman todos los tokens del usuario.
 */
final class RememberMe
{
    public const COOKIE = 'remember';

    /** Vida del token y `Max-Age` de la cookie: 30 días, en segundos. */
    public const TTL = 2592000;

    /** Cuánto sobrevive un tombstone antes de la poda: 1 hora, en segundos. */
    public const ROTATION_GRACE = 3600;

    /**
     * Emite un par nuevo para el usuario y lo devuelve en claro, listo para la
     * cookie.
     *
     * Los dos secretos salen de `random_bytes`; de la base solo se guarda el hash
     * bcrypt del validator. El par en claro vive únicamente en el valor de
     * retorno: quien llama lo entrega como cookie y no lo registra.
     *
     * @param int $userId dueño del token, por clave primaria
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

    /**
     * Header `Set-Cookie` completo para el par, o el que borra la cookie.
     *
     * `HttpOnly` y `SameSite=Lax` son fijos; `Secure` se agrega con
     * SESSION_SECURE porque sobre HTTP local el navegador descartaría la cookie.
     * El `Path` es `/`: la cookie cubre todo el dominio, no solo el prefijo de
     * despliegue.
     *
     * @param string $pair  valor crudo que devolvió create()
     * @param bool   $clear true emite la cookie ya vencida
     */
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
     * Intenta abrir la sesión desde la cookie y devuelve el `Set-Cookie` que
     * corresponde a esta respuesta, o null si no hay nada que hacer.
     *
     * El orden de los cortes importa: primero los tombstones vencidos, después el
     * selector, después el tombstone (replay), el usuario, el vencimiento y por
     * último el validator. Una cookie mal formada o un selector desconocido se
     * ignoran; un vencimiento, un usuario borrado o un replay devuelven el header
     * que limpia la cookie. Un fallo de base de datos sube al error handler.
     *
     * La rotación va en una transacción sobre la conexión de Eloquent y la
     * sesión se abre después del commit, para que un rollback no deje una sesión
     * autenticada sin cookie detrás.
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
            return null; // selector desconocido: no hay fila que quemar, se ignora
        }

        if ($row->rotated_at !== null) {
            // Replay: el par presentado ya se rotó, así que existen dos copias
            // vivas. Se quema la familia entera, incluido el token nuevo que
            // emitió la rotación; la cookie que trajo el replay también se borra.
            RememberToken::query()->where('user_id', (int) $row->user_id)->delete();
            return self::cookieHeader(clear: true);
        }

        $user = UserProvider::find((int) $row->user_id);
        if ($user === null) {
            // Usuario borrado: la fila quedó huérfana, se limpia y no se loguea.
            $row->delete();
            return self::cookieHeader(clear: true);
        }

        if ((int) $row->expires_at <= time()) {
            $row->delete(); // vencimiento normal: solo esa fila, sin alarma
            return self::cookieHeader(clear: true);
        }

        if (!password_verify($validator, $row->hashed_validator)) {
            // El selector existe pero el secreto no coincide: ese par no lo emitió
            // la app. Se queman todos los tokens del usuario y se limpia la cookie.
            RememberToken::query()->where('user_id', (int) $user->getKey())->delete();
            return self::cookieHeader(clear: true);
        }

        $userId = (int) $user->getKey();

        // Rotación atómica: el tombstone de la fila presentada y el alta del
        // reemplazo entran juntos. Si el proceso cae entre las dos sentencias, la
        // cookie del navegador deja de resolver y el usuario queda deslogueado en
        // silencio, porque un selector inexistente se ignora sin error.
        // `transaction()` usa la misma conexión que los modelos —Eloquent ya está
        // booteado sobre este Capsule, ver config/container.php—, así que las dos
        // operaciones caen adentro. La closure recibe la conexión y no la usa: el
        // tipo está para dejar explícito que aquí no se abre una aparte.
        //
        // La sesión se abre después del commit a propósito: si la transacción
        // revierte, no queda una sesión autenticada sin cookie que la respalde.
        $pair = Capsule::connection()->transaction(
            static function (Connection $connection) use ($row, $userId): string {
                // Tombstone en vez de delete: si el token viejo reaparece,
                // `resumeFromCookie()` lo reconoce como replay y quema la familia.
                $row->rotated_at = time();
                $row->save();

                return self::create($userId);
            }
        );

        Auth::login($userId);

        return self::cookieHeader($pair);
    }

    /**
     * Borra los tombstones más viejos que la ventana de gracia.
     *
     * Se llama al comienzo de cada resume: sin esto la tabla crecería con cada
     * rotación. La ventana también acota la detección de replay, porque pasado
     * ese plazo el selector viejo ya no está y un replay se ve como desconocido.
     */
    public static function pruneRotated(): void
    {
        RememberToken::query()
            ->whereNotNull('rotated_at')
            ->where('rotated_at', '<', time() - self::ROTATION_GRACE)
            ->delete();
    }

    /**
     * Elimina todos los tokens de un usuario: cualquier cookie emitida antes deja
     * de resolver, en este navegador y en cualquier otro.
     *
     * Lo llama el logout, que además limpia la cookie de la respuesta.
     */
    public static function clearUser(int $userId): void
    {
        RememberToken::query()->where('user_id', $userId)->delete();
    }
}
