<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;

/**
 * Las 4 vars que espera partials/_csrf.twig. Una sola fuente para
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
}
