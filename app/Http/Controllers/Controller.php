<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Auth;
use App\Support\CsrfTokens;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

/**
 * Controlador base: toda respuesta HTML sale con los datos comunes (flash
 * consumido + tokens CSRF). Cada controlador suma lo suyo por dos vías:
 * viewDefaults() para lo que repite en todas sus acciones, $extra para lo
 * puntual de una acción. Nada de payloads copiados entre controladores.
 */
abstract class Controller
{
    public function __construct(protected Twig $view, protected Guard $guard) {}

    protected function render(Request $request, Response $response, string $template, array $extra = []): Response
    {
        // user_* en cada render (los parciales lo ignoran): una PK indexada
        // cuando hay sesión, nada cuando no la hay. Lo usa el bloque de
        // usuario/salir del layout.
        $user = Auth::user();

        return $this->view->render($response, $template, [
            'flash_error' => Flash::get('error'),
            'user_id'     => $user?->id,
            'user_email'  => $user?->email,
            ...$this->csrf($request),
            ...$this->viewDefaults(),
            ...$extra,
        ]);
    }

    protected function viewDefaults(): array
    {
        return [];
    }

    protected function csrf(Request $request): array
    {
        return CsrfTokens::fields($request, $this->guard);
    }
}
