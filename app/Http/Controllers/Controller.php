<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        return $this->view->render($response, $template, [
            'flash_error' => Flash::get('error'),
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
        $nameKey  = $this->guard->getTokenNameKey();
        $valueKey = $this->guard->getTokenValueKey();

        return [
            'csrf_name_key'  => $nameKey,
            'csrf_name'      => $request->getAttribute($nameKey),
            'csrf_value_key' => $valueKey,
            'csrf_value'     => $request->getAttribute($valueKey),
        ];
    }
}
