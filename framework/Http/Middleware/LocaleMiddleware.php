<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Http\Url;
use Framework\I18n\LocaleResolver;
use Framework\I18n\TranslatorFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Views\Twig;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Locale por request: resuelve, fija en el traductor y recuerda la elección.
 *
 * Es el adaptador HTTP de `LocaleResolver`. La precedencia —explícito (`?lang=`)
 * > sesión > `Accept-Language` > `APP_LOCALE`— vive en el resolutor; acá sólo se
 * leen las fuentes y se aplica el resultado. Depende de
 * `LocaleAwareInterface` y no de `TranslatorInterface` porque el contrato de
 * traducción sólo promete `getLocale()`: cambiar el idioma en caliente es una
 * capacidad aparte, que el `Translator` concreto sí implementa (el contenedor
 * expone esa interfaz apuntando al mismo objeto que consumen Twig y `Trans`).
 *
 * Corre después de `SessionMiddleware` (en `config/middleware.php` se registra
 * antes porque `add()` es LIFO) para que la sesión ya esté abierta, y antes de
 * que cualquier vista se renderice, así el `t()` de la plantilla y el
 * `Trans::t()` de un controlador de la MISMA petición salen en el mismo idioma.
 *
 * Fija el locale siempre, no sólo cuando cambia. El traductor es un singleton
 * del contenedor y `php -S` o un worker de FPM atienden varias peticiones con el
 * mismo proceso: confiar en el valor anterior es exactamente cómo una visita en
 * inglés deja en inglés la siguiente, que no pidió nada.
 *
 * Sin sesión —una API montada sobre un pipeline sin `SessionMiddleware`— no
 * falla ni intenta abrirla: sólo negocia con la cabecera. Con sesión, un pedido
 * explícito se guarda para las próximas peticiones; un valor sin catálogo no se
 * guarda, porque no puede quedar «pegado» un idioma que no existe.
 */
final class LocaleMiddleware implements MiddlewareInterface
{
    /** Clave de sesión de la elección del visitante. La comparte `routes/web.php`. */
    public const SESSION_KEY = 'locale';

    public function __construct(
        private readonly LocaleAwareInterface $translator,
        private readonly Twig $twig,
    ) {
    }

    /**
     * Resuelve el locale, lo aplica y delega; al volver, asegura el `Vary`.
     */
    public function process(Request $request, Handler $handler): Response
    {
        $available = TranslatorFactory::availableLocales();
        $explicit = self::explicit($request);

        $locale = LocaleResolver::resolve(
            $explicit,
            $this->sessionLocale(),
            $request->getHeaderLine('Accept-Language'),
            TranslatorFactory::defaultLocale(),
            $available
        );

        $this->translator->setLocale($locale);

        if ($explicit !== null && LocaleResolver::find($explicit, $available) !== null) {
            $this->remember($locale);
        }

        // Los dos globals se fijan al construir el contenedor; acá se
        // reemplazan por los valores de ESTA petición. `app_locale` alimenta el
        // `lang` del `<html>`; `current_path`, el campo oculto del selector del
        // footer, que tiene que volver a la página desde la que se eligió.
        $environment = $this->twig->getEnvironment();
        $environment->addGlobal('app_locale', $locale);
        $environment->addGlobal('current_path', self::currentPath($request));

        return self::vary($handler->handle($request));
    }

    /** `?lang=` como string; cualquier otra forma (array, ausente) se ignora. */
    private static function explicit(Request $request): ?string
    {
        $value = $request->getQueryParams()['lang'] ?? null;

        return is_string($value) ? $value : null;
    }

    /** Locale recordado por el selector, sólo si la sesión está abierta. */
    private function sessionLocale(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $value = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Guarda la elección explícita en la sesión, si hay una abierta.
     *
     * No abre la sesión a propósito: intentarlo desde un pipeline de API
     * ensucia la respuesta con la cookie de sesión y puede fallar por headers ya
     * enviados.
     */
    private function remember(string $locale): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[self::SESSION_KEY] = $locale;
        }
    }

    /**
     * Ruta de la petición sin el prefijo de despliegue.
     *
     * La base se descarta porque el redirect del `POST /locale` se arma con
     * `Url::path()`, que la vuelve a poner: si viajara con prefijo, el redirect
     * saldría duplicado (`/app/app/tasks`).
     */
    private static function currentPath(Request $request): string
    {
        $path = $request->getUri()->getPath();
        $base = Url::base();

        if ($base !== '' && $path === $base) {
            $path = '/';
        } elseif ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * `Vary: Accept-Language` sin pisar las variantes que ya haya puesto otro.
     *
     * Sin esta cabecera un caché intermedio guarda la primera respuesta y la
     * sirve a todos, con el idioma del primer visitante.
     */
    private static function vary(Response $response): Response
    {
        $values = array_values(array_filter(
            array_map('trim', explode(',', $response->getHeaderLine('Vary'))),
            static fn (string $value): bool => $value !== ''
        ));

        foreach ($values as $value) {
            if (strcasecmp($value, 'Accept-Language') === 0) {
                return $response;
            }
        }

        $values[] = 'Accept-Language';

        return $response->withHeader('Vary', implode(', ', $values));
    }
}
