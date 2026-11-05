<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * El scaffolding que tests/Http repetía clase por clase: `app()`, `request()`,
 * `token()`, el login manual del setUp y la limpieza del tearDown.
 *
 * Las copias ya habían divergido donde más duele: la versión mínima de
 * `request()` no mandaba Content-Type ni StreamFactory con el body, así que un
 * POST armado con ella salteaba `BodyParsingMiddleware`, `getParsedBody()`
 * llegaba vacío y la validación fallaba sin mensaje. Acá vive el superset
 * —body de verdad, headers arbitrarios— y con dos argumentos se comporta
 * exactamente igual que la copia mínima.
 *
 * Lo que NO vive acá es lo que necesita una sola clase: la IP del throttle
 * (LoginThrottleTest arma su propio request) y el prefijo de ruta
 * (BasePathTest resetea AppFactory). Un base que sirve a todos volviéndose un
 * dios es peor que la duplicación que evita.
 */
abstract class HttpTestCase extends TestCase
{
    /**
     * La app es una: la arma AppFactory y la cachea. Solo BasePathTest la
     * resetea, porque el prefijo de ruta se fija al construirla.
     */
    protected static function app(): App
    {
        return AppFactory::make();
    }

    /**
     * Request como la que llega del navegador.
     *
     * URI absoluta: con path-only el host queda vacío y Slim se porta distinto
     * en los casos borde. Con body, se manda crudo y con su Content-Type
     * —nunca `withParsedBody()`— para que lo parsee `BodyParsingMiddleware`
     * igual que en producción: inyectar el array ya parseado deja de cubrir
     * justo la capa que rompen los cambios de orden del pipeline.
     *
     * Con dos argumentos devuelve lo mismo que devolvía la copia mínima: un
     * GET sin body ni headers.
     *
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    protected static function request(
        string $method,
        string $path,
        array $body = [],
        array $headers = []
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // RememberMe lee la cookie de la superglobal, no del request PSR-7: el
        // header se refleja en $_COOKIE. Sin cookies en el header la superglobal
        // queda vacía, para que ningún test herede la cookie del anterior.
        $cookie = $headers['Cookie'] ?? null;
        $_COOKIE = $cookie === null ? [] : self::cookies($cookie);

        return $request;
    }

    /**
     * Tokens CSRF frescos de una página que ya los renderiza.
     *
     * Por defecto /tareas, la página protegida. El Guard corre en
     * persistentTokenMode: el mismo par sirve para varias peticiones del test,
     * así que alcanza con pedirlo una vez.
     *
     * @return array{csrf_name: string, csrf_value: string}
     */
    protected static function token(string $path = '/tareas'): array
    {
        return self::scrapeToken(
            (string) self::app()->handle(self::request('GET', $path))->getBody()
        );
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig.
     *
     * Ojo con la trampa: `csrf_name_key` es una variable de Twig y NO aparece
     * en el HTML renderizado. El template es
     *   <input name="{{ csrf_name_key }}" value="{{ csrf_name }}">
     * y `getTokenNameKey()` devuelve el literal 'csrf_name', así que el name
     * real del input es `csrf_name`. Buscar "csrf_name_key" en el HTML no
     * matchea nunca.
     *
     * @return array{csrf_name: string, csrf_value: string}
     */
    protected static function scrapeToken(string $html): array
    {
        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    /**
     * Crea (o reusa, si le pasás uno) el usuario y siembra la sesión: /tareas
     * exige login y RequireAuth mira `$_SESSION['user_id']`.
     *
     * La sesión ya viene abierta por tests/bootstrap.php, así que el valor
     * sobrevive al request: en PHP >= 8.5 abrirla otra vez vuelve a dejar
     * `$_SESSION` en [] y el login manual se perdería.
     */
    protected static function loginAs(?User $user = null): User
    {
        $user ??= User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ]);

        $_SESSION['user_id'] = $user->id;

        return $user;
    }

    /**
     * El esquema y la base son uno solo para toda la suite: sin limpiar, cada
     * test depende del orden de ejecución.
     *
     * Quien tenga algo más que limpiar (tokens, intentos de login, entorno)
     * lo hace primero y después llama a parent::tearDown().
     */
    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        Task::query()->delete();
        User::query()->delete();
    }

    /**
     * @return array<string, string>
     */
    private static function cookies(string $header): array
    {
        $cookies = [];

        foreach (explode(';', $header) as $pair) {
            [$name, $value] = array_pad(explode('=', trim($pair), 2), 2, '');
            $cookies[$name] = $value;
        }

        return $cookies;
    }
}
