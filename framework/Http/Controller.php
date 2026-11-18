<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Auth\Auth;
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

    /**
     * Canal del aviso en una página completa (sin htmx). `true` por defecto
     * porque el shell pinta `toast_items` y NO pinta banner: con `false`, un
     * controlador nuevo perdería el aviso de `Flash::set()` sin ningún error.
     * Poné `false` sólo si tu layout renderiza el banner inline (`flash_*`);
     * es el caso de la pantalla de acceso.
     */
    protected bool $toastFlash = true;

    public function __construct(protected Twig $view, protected Guard $guard) {}

    protected function render(Request $request, Response $response, string $template, array $extra = []): Response
    {
        // user_* en cada render (los parciales lo ignoran): una PK indexada
        // cuando hay sesión, nada cuando no la hay. Lo usa el bloque de
        // usuario/salir del layout.
        $user = Auth::user();

        // ÚNICO consumidor del flash: si se suma un tipo nuevo, va aquí.
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

        $toastItems = [];
        if (!$request->hasHeader('HX-Request') && $this->toastFlash) {
            foreach (self::FLASH_ORDER as $type) {
                if ($flash[$type] !== null && $flash[$type] !== '') {
                    $toastItems[] = ['message' => $flash[$type], 'type' => $type];
                }
            }
        }

        return $this->view->render($response, $template, [
            'flash_error'   => $flash['error'],
            'flash_success' => $flash['success'],
            'flash_info'    => $flash['info'],
            'toast_items'   => $toastItems,
            'user_id'       => $user?->getKey(),
            'user_email'    => $user?->getAttribute('email'),
            ...$this->csrf($request),
            ...$this->viewDefaults(),
            ...$extra,
        ]);
    }

    /**
     * Redirección de después de una mutación: 303 + `Location`.
     *
     * 303 (See Other) y no 302: fuerza a que el siguiente pedido sea un `GET`,
     * así refrescar la pantalla de destino no reenvía el formulario. Es el
     * patrón POST/Redirect/GET, y por eso todas las mutaciones del proyecto
     * vuelven por acá en vez de responder la página directamente.
     *
     * @param string $to ruta destino, sin base path: se resuelve con Url::path()
     * @param int    $status 303 por defecto; 301/302 sólo si sabés por qué
     */
    protected function redirect(Response $response, string $to, int $status = 303): Response
    {
        return $response->withStatus($status)->withHeader('Location', Url::path($to));
    }

    /**
     * Dos migas: Inicio y la pantalla actual.
     *
     * La forma la lee el header del shell (`{label, href?, icon?, current?}`):
     * `icon` viaja sólo cuando hay icono —una miga con `icon => null` pinta un
     * hueco— y `current` marca la última para que se pinte como texto y no como
     * enlace.
     *
     * @param string      $label texto de la pantalla, sin prefijo de marca
     * @param string|null $icon  nombre del icono; null cuando la pantalla no lleva
     *
     * @return list<array<string, mixed>>
     */
    protected function breadcrumbs(string $label, ?string $icon = null): array
    {
        $current = ['label' => $label];

        if ($icon !== null) {
            $current['icon'] = $icon;
        }

        $current['current'] = true;

        return [
            ['label' => 'Inicio', 'href' => Url::path('/')],
            $current,
        ];
    }

    /**
     * Tres migas: Inicio, el listado padre y la acción actual.
     *
     * El `href` entra tal cual: el llamador decide si es un `Url::path()` o una
     * URL propia, igual que hacía a mano. Se separó de breadcrumbs() en vez de
     * agregarle parámetros opcionales porque las dos formas se leen distintas y
     * con tres argumentos posicionales una llamada no dice cuál se está armando.
     *
     * @param string $href destino del listado padre, ya resuelto con Url::path()
     *
     * @return list<array<string, mixed>>
     */
    protected function breadcrumbsFor(string $label, string $href, string $action): array
    {
        return [
            ['label' => 'Inicio', 'href' => Url::path('/')],
            ['label' => $label, 'href' => $href],
            ['label' => $action, 'current' => true],
        ];
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

    /**
     * Variables que este controlador agrega a TODAS sus vistas sin repetir
     * `$extra` en cada acción (marca, menú, flags). Vacío por defecto: es un
     * punto de extensión, no una utilidad.
     *
     * Precedencia en `render()`: el `$extra` de la acción gana sobre esto, y
     * esto gana sobre los datos comunes y el CSRF. El contrato lo fija
     * `tests/Support/ControllerViewDefaultsTest.php`.
     *
     * @return array<string, mixed>
     */
    protected function viewDefaults(): array
    {
        return [];
    }

    protected function csrf(Request $request): array
    {
        return CsrfTokens::fields($request, $this->guard);
    }
}
