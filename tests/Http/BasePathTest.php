<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AppFactory;
use Tests\Support\HttpTestCase;

/**
 * S13: la app servida bajo un prefijo de ruta (`APP_BASE_PATH=/app`).
 *
 * El caso real: el despliegue no vive en la raíz del dominio sino en una
 * carpeta (`https://dominio.com/app/`). Con todas las URLs raíz-absolutas, cada
 * `href="/tareas"` y cada `src="/assets/..."` resolvía al dominio y daba 404:
 * la app se veía sin estilos y sin poder navegar.
 *
 * Se verifica lo que el usuario ve, no la implementación: la request entra con
 * el prefijo, la respuesta trae las URLs prefijadas, las que no lo llevan no
 * existen, y los redirects no sacan al navegador del prefijo.
 */
final class BasePathTest extends HttpTestCase
{
    /** Dos letras alcanzan y hacen el marcador legible en los asserts. */
    private const PREFIJO = '/app';

    protected function setUp(): void
    {
        self::prefijo(self::PREFIJO);

        // El App fija el prefijo al construirse (setBasePath) y AppFactory lo
        // cachea: sin este reset el test correría contra una app armada sin
        // prefijo y su verde no diría nada sobre APP_BASE_PATH.
        AppFactory::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // El resto de la suite asume la app en la raíz: se deja el proceso como
        // estaba, con una app nueva que se armara al próximo make().
        self::prefijo('');
        AppFactory::reset();
    }

    public function test_la_raiz_responde_bajo_el_prefijo_y_ninguna_url_queda_en_la_raiz(): void
    {
        $response = self::app()->handle(self::request('GET', '/app/'));

        self::assertSame(200, $response->getStatusCode(), 'el prefijo no llegó al router');
        $html = (string) $response->getBody();

        // Assets: son los que hacen que la app se VEA (sin ellos, daisyUI y el
        // JIT de Tailwind no cargan y la página sale como texto plano).
        self::assertStringContainsString('href="/app/assets/daisyui-5.7.46.css"', $html);
        self::assertStringContainsString('src="/app/assets/htmx-2.0.11.min.js"', $html);
        self::assertStringContainsString('href="/app/favicon.svg"', $html);

        // La regla de S13 en una sola línea: ninguna URL de atributo empieza en
        // `/` sin el prefijo. `/app/` sí, que es la raíz del despliegue.
        self::assertDoesNotMatchRegularExpression(
            '#(?:href|src|action|hx-get|hx-post|hx-put|hx-delete)="/(?!app/)#',
            $html,
            'quedó una URL raíz-absoluta que no lleva el prefijo'
        );
    }

    public function test_sin_el_prefijo_la_ruta_no_existe(): void
    {
        $response = self::app()->handle(self::request('GET', '/'));

        // 404 y no 200: el prefijo es parte de la ruta registrada. Si esto
        // devolviera 200, el prefijo sería cosmético y el despliegue en /app
        // seguiría sirviendo la app (y sus assets) en la raíz del dominio.
        self::assertSame(404, $response->getStatusCode());

        $html = (string) $response->getBody();

        // El 404 sale por errors/error.twig, que no extiende el layout: también
        // tiene que perder la raíz del dominio, o la página de error de un
        // despliegue en /app es la única que se ve sin estilos. Y "volver al
        // inicio" apunta a la raíz DEL PREFIJO (`/app/`), que es donde vive
        // esta app: `/` es el dominio, que no la sirve.
        self::assertStringContainsString('No encontramos esa página.', $html);
        self::assertStringContainsString('href="/app/assets/daisyui-5.7.46.css"', $html);
        self::assertStringContainsString('href="/app/"', $html);
    }

    public function test_los_redirects_llevan_el_prefijo(): void
    {
        // Sin sesión: la ruta protegida de test en sus dos dialectos. Un 303 o
        // un HX-Redirect a `/login` dejaría al usuario fuera de la app, en el
        // login (inexistente) de la raíz del dominio.
        $normal = self::app()->handle(self::request('GET', '/app/_test/protected'));
        self::assertSame(303, $normal->getStatusCode());
        self::assertSame('/app/login', $normal->getHeaderLine('Location'));

        $htmx = self::app()->handle(
            self::request('GET', '/app/_test/protected')->withHeader('HX-Request', 'true')
        );
        self::assertSame('/app/login', $htmx->getHeaderLine('HX-Redirect'));

        // El token se pide ANTES de entrar: logueado, /login redirige y ya no
        // hay formulario del que scrapearlo.
        $token = self::token('/app/login');

        // Ya logueado, /login no muestra el formulario: manda al inicio.
        self::loginAs();

        $yaAdentro = self::app()->handle(self::request('GET', '/app/login'));
        self::assertSame(303, $yaAdentro->getStatusCode());
        self::assertSame('/app/', $yaAdentro->getHeaderLine('Location'));

        // Y salir vuelve al login, igual que entrar.
        $salida = self::app()->handle(
            self::request('POST', '/app/logout')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(303, $salida->getStatusCode());
        self::assertSame('/app/login', $salida->getHeaderLine('Location'));
    }

    /**
     * Prefijo para las DOS fuentes que lee el helper: `$_ENV` (Dotenv y
     * tests/bootstrap.php) y el entorno inline. `''` es "sin prefijo", así que
     * el reset no depende de poder desetear una variable de entorno.
     */
    private static function prefijo(string $valor): void
    {
        $_ENV['APP_BASE_PATH'] = $valor;
        putenv('APP_BASE_PATH=' . $valor);
    }
}
