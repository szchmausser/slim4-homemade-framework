<?php

declare(strict_types=1);

namespace Framework\Http;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;

/**
 * Los cuatro valores que espera `components/form/csrf.twig`, en un solo lugar.
 *
 * Los controladores llegan por `Controller::csrf()` y las closures de ruta lo
 * llaman directo cuando renderizan sin controlador (la portada, que igual necesita
 * el formulario de logout del layout). Sin este punto único, cada pantalla tendría
 * que conocer las claves del Guard y el nombre del atributo que deja el middleware.
 */
final class CsrfTokens
{
    /**
     * Lee del request el par que emitió el Guard y lo devuelve con los nombres que
     * usa la plantilla.
     *
     * No genera tokens: el middleware `Guard` ya los dejó como atributos de la
     * petición; aquí solo se renombran a `csrf_name_key`, `csrf_name`,
     * `csrf_value_key` y `csrf_value`.
     *
     * @return array<string, mixed>
     */
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
     * Invalida el par emitido antes del login.
     *
     * El id de sesión ya se regenera en `Auth::login()`; vaciar el almacén del
     * Guard cierra la ventana de un token capturado en un formulario público (la
     * pantalla de acceso en un equipo compartido). El próximo GET emite uno nuevo.
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
