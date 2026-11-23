<?php

declare(strict_types=1);

use Framework\Auth\Auth;
use Framework\Http\CsrfTokens;
use Framework\Http\Middleware\LocaleMiddleware;
use Framework\Http\Url;
use Framework\I18n\LocaleResolver;
use Framework\I18n\Trans;
use Framework\I18n\TranslatorFactory;
use App\Http\Controllers\Auth\AuthController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

return function (App $app) {
    /**
     * Ruta local segura para el redirect del selector de idioma.
     *
     * El campo `redirect_to` lo escribe el cliente y el servidor lo devuelve
     * como `Location`: aceptar `//host` o una URL absoluta sería un redirect
     * abierto, el vector clásico para mandar a un usuario ya autenticado a una
     * copia del login. Se exige una ruta que empiece con UNA barra y se
     * rechazan barras invertidas (los navegadores las normalizan a `/`, así que
     * `/\evil.example` termina en `//evil.example`) y caracteres de control
     * (inyección de cabecera). Todo lo demás —ausente incluido— cae en la
     * portada.
     */
    $localPath = static function (mixed $value): string {
        if (!is_string($value)) {
            return '/';
        }

        $path = trim($value);

        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        if (str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            return '/';
        }

        return $path;
    };

    // Selector de idioma del footer. POST y no GET: cambia la elección guardada
    // en la sesión, así que es una mutación y el Guard la protege con CSRF como
    // a cualquier otro POST. Vive en web.php porque es parte del shell, no de
    // una entidad.
    $app->post('/locale', function (ServerRequestInterface $request, ResponseInterface $response) use ($localPath) {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $requested = $data['lang'] ?? null;
        $locale = is_string($requested)
            ? LocaleResolver::find($requested, TranslatorFactory::availableLocales())
            : null;

        // Un código sin catálogo se ignora: la sesión no puede quedar apuntando
        // a un idioma que no existe. Sin sesión abierta no hay dónde recordarlo
        // y tampoco es un error: el redirect sigue, simplemente no persiste.
        if ($locale !== null && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[LocaleMiddleware::SESSION_KEY] = $locale;
        }

        return $response
            ->withStatus(303)
            ->withHeader('Location', Url::path($localPath($data['redirect_to'] ?? null)));
    });

    // Ruta raiz: pagina de presentacion del stack, con status 200. Sin ella
    // GET / devuelve 404 y el boton "Volver al inicio" de pages/errors/error.twig
    // vuelve a ser un link muerto. Pública a propósito (la pide la app, no ella).
    // El layout necesita csrf (form de logout) y usuario: como aquí se renderiza
    // directo sin Controller, van explícitos con la misma fuente (CsrfTokens).
    $app->get('/', function ($request, $response) use ($app) {
        $container = $app->getContainer();

        // Guard se resuelve aquí (en request), no al registrar: al construirse
        // exige sesión iniciada y en registro todavía no hay ninguna.
        return $container->get(Twig::class)->render($response, 'pages/home.twig', [
            'active'      => 'home',
            'user_id'     => Auth::id(),
            'user_email'  => Auth::user()?->getAttribute('email'),
            'breadcrumbs' => [
                // La miga la pinta el header del shell tal cual, así que acá va el
                // TEXTO y no la clave: este closure corre en cada petición, con el
                // locale ya resuelto por `LocaleMiddleware`. Una clave acá
                // imprimiría `nav.home` en el header.
                ['label' => Trans::t('nav.home'), 'icon' => 'home', 'current' => true],
            ],
            ...CsrfTokens::fields($request, $container->get(Guard::class)),
        ]);
    });

    $app->get('/login', [AuthController::class, 'show']);
    $app->post('/login', [AuthController::class, 'store']);
    $app->post('/logout', [AuthController::class, 'destroy']);
};
