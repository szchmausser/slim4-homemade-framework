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
    /**
     * Orden en que se muestran los avisos cuando una acción deja más de uno
     * (ej.: falla la validación y además había un info pendiente).
     */
    private const FLASH_ORDER = ['error', 'success', 'info'];

    public function __construct(protected Twig $view, protected Guard $guard) {}

    protected function render(Request $request, Response $response, string $template, array $extra = []): Response
    {
        // user_* en cada render (los parciales lo ignoran): una PK indexada
        // cuando hay sesión, nada cuando no la hay. Lo usa el bloque de
        // usuario/salir del layout.
        $user = Auth::user();

        // ÚNICO consumidor del flash. Antes cada controlador hacía además su
        // propio Flash::get('info') para pasarlo a la plantilla: el que llegaba
        // primero se llevaba el mensaje y el otro recibía null, así que el
        // aviso se perdía sin error. Si se suma un tipo nuevo, va acá.
        $flash = [];
        foreach (self::FLASH_ORDER as $type) {
            $flash[$type] = Flash::get($type);
        }

        // Un aviso, un canal, elegido por el request:
        // - htmx: el aviso viaja en `HX-Trigger-After-Swap` y el HTML sale sin
        //   el banner. Si viajara por los dos lados, se vería dos veces.
        // - página completa: banner inline (login, redirect de RequireAuth)
        //   porque ahí no hay evento de htmx que escuchar.
        if ($request->hasHeader('HX-Request')) {
            $response = $this->withToasts($response, $flash);
            $flash = ['error' => null, 'success' => null, 'info' => null];
        }

        return $this->view->render($response, $template, [
            'flash_error'   => $flash['error'],
            'flash_success' => $flash['success'],
            'flash_info'    => $flash['info'],
            'user_id'       => $user?->id,
            'user_email'    => $user?->email,
            ...$this->csrf($request),
            ...$this->viewDefaults(),
            ...$extra,
        ]);
    }

    /**
     * Emite los avisos de una acción htmx como evento `toasts`.
     *
     * - `After-Swap` y no `HX-Trigger` a secas: en un ARM de 32 bits el swap
     *   puede tardar y con el evento temprano el toast aparecería antes que la
     *   lista nueva.
     * - El payload es un OBJETO: htmx entrega `detail` tal cual solo cuando el
     *   valor del header es un objeto; un array lo envuelve en `{value: [...]}`
     *   (verificado en public/assets/htmx-2.0.11.min.js, handleTriggerHeader).
     *   Por eso el layout lee `$event.detail.items` y no `.value`.
     * - JSON_UNESCAPED_UNICODE: los mensajes van en castellano; escapados a
     *   `\u00xx` el header crece al doble sin ganar nada. El SUBSTITUTE es la
     *   red: un mensaje con bytes inválidos no puede tumbar la respuesta con un
     *   TypeError de withHeader(false).
     *
     * @param array<string, string|null> $flash
     */
    private function withToasts(Response $response, array $flash): Response
    {
        $items = [];
        foreach (self::FLASH_ORDER as $type) {
            if ($flash[$type] !== null && $flash[$type] !== '') {
                $items[] = ['message' => $flash[$type], 'type' => $type];
            }
        }

        if ($items === []) {
            return $response;
        }

        return $response->withHeader('HX-Trigger-After-Swap', (string) json_encode(
            ['toasts' => ['items' => $items]],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        ));
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
