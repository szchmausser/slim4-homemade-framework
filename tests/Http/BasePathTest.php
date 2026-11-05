<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

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
final class BasePathTest extends TestCase
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
        unset($_SESSION['user_id']);
        Task::query()->delete();
        User::query()->delete();

        // El resto de la suite asume la app en la raíz: se deja el proceso como
        // estaba, con una app nueva que se armara al próximo make().
        self::prefijo('');
        AppFactory::reset();
    }

    public function test_el_listado_responde_bajo_el_prefijo_y_ninguna_url_queda_en_la_raiz(): void
    {
        $this->autenticar();

        $response = self::app()->handle(self::request('GET', '/app/tareas'));

        self::assertSame(200, $response->getStatusCode(), 'el prefijo no llegó al router');
        $html = (string) $response->getBody();

        // Assets: son los que hacen que la app se VEA (sin ellos, daisyUI y el
        // JIT de Tailwind no cargan y la página sale como texto plano).
        self::assertStringContainsString('href="/app/assets/daisyui-5.7.46.css"', $html);
        self::assertStringContainsString('src="/app/assets/htmx-2.0.11.min.js"', $html);

        // Navegación y favicon.
        self::assertStringContainsString('href="/app/tareas"', $html);
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
        $this->autenticar();

        $response = self::app()->handle(self::request('GET', '/tareas'));

        // 404 y no 200: el prefijo es parte de la ruta registrada. Si esto
        // devolviera 200, el prefijo sería cosmético y el despliegue en /app
        // seguiría sirviendo la app (y sus assets) en la raíz del dominio.
        self::assertSame(404, $response->getStatusCode());

        $html = (string) $response->getBody();
        self::assertStringNotContainsString('Mis tareas', $html);

        // El 404 sale por errors/error.twig, que no extiende el layout: también
        // tiene que perder la raíz del dominio, o la página de error de un
        // despliegue en /app es la única que se ve sin estilos. Y "volver al
        // inicio" apunta a la raíz DEL PREFIJO (`/app/`), que es donde vive
        // esta app: `/` es el dominio, que no la sirve.
        self::assertStringContainsString('No encontramos esa página.', $html);
        self::assertStringContainsString('href="/app/assets/daisyui-5.7.46.css"', $html);
        self::assertStringContainsString('href="/app/"', $html);
    }

    public function test_la_paginacion_y_la_url_empujada_llevan_el_prefijo(): void
    {
        $this->autenticar();

        // 11 filas: con 10 por página existe una página 2, y sin eso la
        // paginación no emite ningún <a href> (son botones apagados).
        for ($i = 1; $i <= 11; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }

        $html = (string) self::app()->handle(self::request('GET', '/app/tareas'))->getBody();

        // base_url lo arma el controller con el helper: si la paginación no lo
        // hereda, el segundo click sale del prefijo y da 404.
        self::assertMatchesRegularExpression(
            '#<a[^>]*href="/app/tareas\?page=2&(?:amp;)?per_page=10"#',
            $html,
            'la paginación tiene que llevar el prefijo'
        );

        $task = Task::orderByDesc('id')->first();
        self::assertNotNull($task, 'el fixture tiene que haber creado tareas');

        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', sprintf('/app/tareas/%d/toggle?page=1&per_page=10', $task->id))
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());
        // HX-Push-Url sale de base_url: prefijado o no, es la diferencia entre
        // recargar la lista en su carpeta y volver a la raíz del dominio.
        self::assertSame(
            '/app/tareas?page=1&per_page=10',
            $response->getHeaderLine('HX-Push-Url')
        );
    }

    public function test_los_redirects_llevan_el_prefijo(): void
    {
        // Sin sesión: la puerta de /tareas en sus dos dialectos. Un 303 o un
        // HX-Redirect a `/login` dejaría al usuario fuera de la app, en el
        // login (inexistente) de la raíz del dominio.
        $normal = self::app()->handle(self::request('GET', '/app/tareas'));
        self::assertSame(303, $normal->getStatusCode());
        self::assertSame('/app/login', $normal->getHeaderLine('Location'));

        $htmx = self::app()->handle(
            self::request('GET', '/app/tareas')->withHeader('HX-Request', 'true')
        );
        self::assertSame('/app/login', $htmx->getHeaderLine('HX-Redirect'));

        // Ya logueado, /login no muestra el formulario: manda al listado.
        $this->autenticar();

        $yaAdentro = self::app()->handle(self::request('GET', '/app/login'));
        self::assertSame(303, $yaAdentro->getStatusCode());
        self::assertSame('/app/tareas', $yaAdentro->getHeaderLine('Location'));

        // Y salir vuelve al login, igual que entrar.
        $token = self::token();

        $salida = self::app()->handle(
            self::request('POST', '/app/logout')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(303, $salida->getStatusCode());
        self::assertSame('/app/login', $salida->getHeaderLine('Location'));
    }

    private function autenticar(): void
    {
        $_SESSION['user_id'] = User::create([
            'email'         => 'prefijo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    /**
     * Token CSRF de la página protegida, scrapeado como en AuthTest: el Guard
     * corre en persistentTokenMode, así que el par sirve para las DOS
     * peticiones del test (toggle y salir, en tests distintos).
     *
     * @return array{csrf_name: string, csrf_value: string}
     */
    private static function token(): array
    {
        $html = (string) self::app()->handle(self::request('GET', '/app/tareas'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
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

    private static function app(): App
    {
        return AppFactory::make();
    }

    private static function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $path);
    }
}
