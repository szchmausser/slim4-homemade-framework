<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;

/**
 * Las 4 vars que espera components/form/csrf.twig. Una sola fuente para
 * controladores (vía Controller::csrf) y closures de ruta (p. ej. la home,
 * que renderiza directo y también necesita el form de logout del layout).
 */
final class CsrfTokens
{
    public static function fields(Request $request, Guard $guard): array
    {
        $nameKey  = $guard->getTokenNameKey();
        $valueKey = $guard->getTokenValueKey();

        return [
            'csrf_name_key'  => $nameKey,
            'csrf_name'      => $request->getAttribute($nameKey),
            'csrf_value_key' => $valueKey,
            'csrf_value'     => $request->getAttribute($valueKey),
        ];
    }

    /**
     * Invalida el token emitido antes del login. El ID de sesión ya se
     * regenera en Auth::login(); esto cierra la ventana de un token CSRF
     * capturado antes de autenticarse (equipo compartido, panel público).
     * El próximo GET genera un par nuevo.
     */
    public static function rotate(Guard $guard): void
    {
        $nameKey = $guard->getTokenNameKey();
        $prefix = str_ends_with($nameKey, '_name') ? substr($nameKey, 0, -5) : 'csrf';

        if (isset($_SESSION[$prefix]) && is_array($_SESSION[$prefix])) {
            $_SESSION[$prefix] = [];
        }
    }
}
