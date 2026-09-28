<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

final class CsrfFlowTest extends TestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        // La capa de auth en sí se testea en AuthTest, no acá.
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        unset($_SESSION['user_id']);
        User::query()->delete();
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig.
     *
     * Ojo con una trampa: `csrf_name_key` es una variable de Twig, NO aparece
     * en el HTML renderizado. El template es
     *   <input name="{{ csrf_name_key }}" value="{{ csrf_name }}">
     * y `getTokenNameKey()` devuelve justo el string literal 'csrf_name'. Así
     * que el name real del input es `csrf_name`, y buscar "csrf_name_key" en
     * el HTML no matchea nunca.
     */
    private function scrapeToken(string $html): array
    {
        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    public function test_get_renderiza_el_formulario_con_token(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="csrf_name"', (string) $response->getBody());
    }

    public function test_post_con_token_valido_crea_la_tarea(): void
    {
        $app = self::app();

        // 1. El GET abre la sesión y deja el token en ella.
        $getResponse = $app->handle(self::request('GET', '/tareas'));
        $token = $this->scrapeToken((string) $getResponse->getBody());

        // 2. El POST reutiliza ese token.
        $postResponse = $app->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $postResponse->getStatusCode());
        self::assertStringContainsString('Comprar pan', (string) $postResponse->getBody());
    }

    public function test_post_con_token_invalido_redirige_al_inicio(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => 'basura',
                'csrf_value' => 'basura',
                'title'      => 'No deberia guardarse',
            ])
        );

        // Sin el failureHandler del capítulo 6 esto devolvería 400
        // "Failed CSRF check!" en text/plain. El 303 comprueba que nuestro
        // handler está conectado en la posición correcta del constructor.
        self::assertSame(303, $response->getStatusCode());
        self::assertStringNotContainsString('No deberia guardarse', (string) $response->getBody());
    }

    public function test_post_htmx_con_token_invalido_devuelve_400_y_hx_trigger(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', ['title' => 'x'], htmx: true)
        );

        self::assertSame(400, $response->getStatusCode());
        // El valor es {"csrf":"..."} — asserteamos contra el valor, no contra
        // el nombre del header (getHeader() devuelve los valores).
        $hxTrigger = $response->getHeaderLine('HX-Trigger');
        self::assertStringContainsString('csrf', $hxTrigger);

        // El header tiene que ser JSON con la clave `csrf`: es lo que lee
        // `@csrf.window` del layout. Un texto plano no tiene claves y el
        // listener se queda con undefined.
        $payload = json_decode($hxTrigger, true);
        self::assertIsArray($payload, 'HX-Trigger tiene que ser JSON.');
        self::assertArrayHasKey('csrf', $payload);
    }

    public function test_el_hx_trigger_del_csrf_tiene_alguien_que_lo_escuche(): void
    {
        // Existe por un bug real que ningún otro test veía: el failureHandler
        // emite HX-Trigger:{csrf:...} y no había NINGÚN listener en las
        // plantillas. El test de arriba assertea que el header salga — y sale,
        // perfecto. Pero llegaba al vacío: HTMX recibe el 400, no hace swap
        // (no es 2xx), no dispara nada visible y la tarea desaparece sin
        // error. Test verde, producción rota.
        //
        // Asserteamos el lado de la RECEPCIÓN, que es el que se rompe. Si
        // tocás layouts/app.twig y te llevás el @csrf.window, este test falla
        // antes que alguien pierda una tarea en silencio.
        $layout = file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/layouts/app.twig'
        );

        self::assertNotFalse($layout, 'No se pudo leer resources/views/layouts/app.twig');
        self::assertStringContainsString(
            '@csrf.',
            $layout,
            'El failureHandler emite HX-Trigger:{csrf} pero nada lo escucha: el fallo CSRF queda silencioso.'
        );
    }

    public function test_el_listener_de_csrf_lee_el_valor_del_evento(): void
    {
        // Regresión del bug 12: con `String($event.detail)` el toast mostraba
        // "[object Object]". El texto vive en `.value`, no en el objeto
        // entero, y gretear solo `@csrf.` pasaba con las dos variantes.
        $layout = file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/layouts/app.twig'
        );

        self::assertNotFalse($layout, 'No se pudo leer resources/views/layouts/app.twig');
        self::assertStringContainsString(
            '$event.detail.value',
            $layout,
            'El listener de CSRF tiene que leer $event.detail.value: con $event.detail el aviso dice [object Object].'
        );
        self::assertStringContainsString('@csrf.window=', $layout);
    }

    private static function request(string $method, string $path, array $body = [], bool $htmx = false): ServerRequestInterface
    {
        // URI absoluta: con path-only el host queda vacío y Slim se porta
        // distinto en los casos borde. En producción siempre hay host.
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            // Deliberadamente NO usamos withParsedBody(): mandamos el body crudo
            // con su Content-Type para que pase por el Body Parsing Middleware
            // igual que en producción. Si inyectás el array ya parseado, el test
            // deja de cubrir el body parsing — que es justo lo que rompen los
            // cambios de orden en el pipeline.
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        // Solo lo seteamos cuando corresponde: detectarlo con getHeaderLine()
        // hace que un header presente con valor vacío cuente como "no HTMX".
        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
