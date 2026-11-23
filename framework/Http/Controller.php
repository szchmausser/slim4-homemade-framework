<?php

declare(strict_types=1);

namespace Framework\Http;

use Framework\Auth\Auth;
use Framework\I18n\Trans;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

/**
 * Base de los controladores: toda respuesta HTML sale con los datos comunes de la
 * aplicación.
 *
 * `render()` compone el payload que esperan las vistas —usuario, flash ya
 * consumido, tokens CSRF y lo que agregue `viewDefaults()`— y suma el `$extra` de
 * la acción. `redirect()` es el cierre estándar de una mutación y los dos
 * `breadcrumbs*()` arman la forma que lee el header del shell. Ningún controlador
 * arma esas piezas por su cuenta.
 */
abstract class Controller
{
    /**
     * Orden en que se muestran los avisos cuando una acción deja más de uno (por
     * ejemplo, falla la validación y además había un info pendiente).
     */
    private const FLASH_ORDER = ['error', 'success', 'info'];

    /**
     * Si el flash de una página completa (sin htmx) se pinta como toast y no como
     * banner.
     *
     * `true` por defecto porque el layout del shell pinta `toast_items` y no pinta
     * banner: con `false`, un controlador nuevo perdería el aviso sin ningún error.
     * Pon `false` solo si tu layout renderiza el banner inline desde `flash_*`.
     */
    protected bool $toastFlash = true;

    /**
     * Recibe Twig y el Guard por inyección de dependencias; el controlador nunca
     * los resuelve del container. El Guard entra porque `render()` emite el par
     * CSRF de cada formulario.
     */
    public function __construct(protected Twig $view, protected Guard $guard) {}

    /**
     * Renderiza la plantilla con los datos comunes de la aplicación.
     *
     * Consume el flash —`Flash::get()` lo borra: un aviso se ve una sola vez—,
     * emite el par CSRF y arma la precedencia de variables: el `$extra` de la
     * acción gana sobre `viewDefaults()`, y este sobre los datos comunes. Con
     * `HX-Request` el aviso viaja como evento y el HTML sale sin `toast_items`,
     * para que no se vea dos veces.
     *
     * @param array<string, mixed> $extra variables de esta acción
     */
    protected function render(Request $request, Response $response, string $template, array $extra = []): Response
    {
        // `user_*` viaja en cada render y los parciales lo ignoran: una PK
        // indexada cuando hay sesión, null cuando no. Lo usa el bloque de
        // usuario/salir del layout.
        $user = Auth::user();

        // Único consumidor del flash: si se agrega un tipo de aviso, entra en
        // FLASH_ORDER y se lee aquí.
        $flash = [];
        foreach (self::FLASH_ORDER as $type) {
            $flash[$type] = Flash::get($type);
        }

        // Un aviso, un canal, elegido por el request:
        // - htmx: viaja en `HX-Trigger-After-Swap` y el HTML sale sin banner. Si
        //   viajara por los dos lados, se vería dos veces.
        // - página completa: banner inline, porque ahí no hay evento de htmx que
        //   escuchar.
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
     * 303 (See Other) y no 302: le indica al cliente que el siguiente pedido sea
     * un `GET`, así refrescar la pantalla de destino no reenvía el formulario. Es
     * el patrón POST/Redirect/GET, y por eso las mutaciones cierran con un
     * redirect y no con la página ya renderizada.
     *
     * @param string $to     ruta destino, sin base path: se resuelve con Url::path()
     * @param int    $status 303 por defecto; 301/302 solo si tienes una razón
     */
    protected function redirect(Response $response, string $to, int $status = 303): Response
    {
        return $response->withStatus($status)->withHeader('Location', Url::path($to));
    }

    /**
     * Dos migas: Inicio y la pantalla actual.
     *
     * La forma la lee el header del shell (`{label, href?, icon?, current?}`):
     * `icon` viaja solo cuando hay icono —una miga con `icon => null` pinta un
     * hueco— y `current` marca la última para que se pinte como texto y no como
     * enlace.
     *
     * La miga raíz sale del catálogo (`nav.home`) y NO es una clave: el shell
     * pinta `crumb.label` tal cual, así que acá tiene que llegar el texto del
     * locale de la petición. `Trans::t()` corre en cada render, después de que
     * `LocaleMiddleware` fijó el idioma; `$label`/`$action` los pasa quien llama
     * ya traducidos (los scaffolds usan `Trans::t('nav.tasks')`).
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
            ['label' => Trans::t('nav.home'), 'href' => Url::path('/')],
            $current,
        ];
    }

    /**
     * Tres migas: Inicio, el listado padre y la acción actual.
     *
     * La miga raíz se traduce igual que en `breadcrumbs()`.
     *
     * El `href` entra tal cual: quien llama decide si es un `Url::path()` o una URL
     * propia. Es un método aparte y no un parámetro opcional de `breadcrumbs()`
     * porque las dos formas se leen distinto y con tres argumentos posicionales una
     * llamada no dice cuál se está armando.
     *
     * @param string $href destino del listado padre, ya resuelto con Url::path()
     *
     * @return list<array<string, mixed>>
     */
    protected function breadcrumbsFor(string $label, string $href, string $action): array
    {
        return [
            ['label' => Trans::t('nav.home'), 'href' => Url::path('/')],
            ['label' => $label, 'href' => $href],
            ['label' => $action, 'current' => true],
        ];
    }

    /**
     * Emite los avisos de una acción htmx como evento `toasts`.
     *
     * `After-Swap` y no `HX-Trigger` a secas: htmx lo dispara con el DOM nuevo ya
     * en su lugar, así el toast no puede aparecer antes que la lista. El payload
     * es un objeto porque htmx entrega `detail` tal cual solo en ese caso; un
     * array quedaría envuelto en `{value: [...]}`. El layout escucha
     * `$event.detail.items`.
     *
     * `JSON_UNESCAPED_UNICODE` acorta el header de los mensajes en castellano y
     * `JSON_INVALID_UTF8_SUBSTITUTE` es la red: un byte inválido no puede tumbar la
     * respuesta con un TypeError de `withHeader()`.
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
     * Variables que este controlador agrega a todas sus vistas sin repetir `$extra`
     * en cada acción (marca, menú, flags). Vacío por defecto: es un punto de
     * extensión, no una utilidad.
     *
     * Precedencia en `render()`: el `$extra` de la acción gana sobre esto, y esto
     * gana sobre los datos comunes y el CSRF. La resuelve el orden de los spreads
     * del array de la vista.
     *
     * @return array<string, mixed>
     */
    protected function viewDefaults(): array
    {
        return [];
    }

    /**
     * Los cuatro campos CSRF que espera `components/form/csrf.twig`, resueltos por
     * `CsrfTokens::fields()`.
     *
     * @return array<string, mixed>
     */
    protected function csrf(Request $request): array
    {
        return CsrfTokens::fields($request, $this->guard);
    }
}
