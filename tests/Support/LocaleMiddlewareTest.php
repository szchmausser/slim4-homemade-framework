<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\I18n\TranslatorFactory;
use Framework\Support\Env;
use Slim\Views\Twig;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Contrato del locale por request: el middleware que resuelve, fija y recuerda.
 *
 * El estado incómodo de este contrato es del proceso, no del objeto: el
 * traductor es un singleton del contenedor y `php -S`/FPM atienden varias
 * peticiones con el mismo, así que el test que importa es el de dos requests
 * seguidas. Y hay dos ramas que esta suite no puede alcanzar —sin sesión, y el
 * orden real Session → Locale— porque `tests/bootstrap.php` abre la sesión una
 * sola vez: para ésas se arranca un proceso PHP nuevo, que es el único estado
 * limpio posible.
 */
final class LocaleMiddlewareTest extends HttpTestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['locale'], $_SESSION['flash']);

        // El locale y el global de Twig son estado del proceso: la última
        // request los dejó en el idioma que resolvió, y el test siguiente
        // renderizaría en inglés sin haberlo pedido.
        $container = self::app()->getContainer();
        $default = TranslatorFactory::defaultLocale();

        $container->get(LocaleAwareInterface::class)->setLocale($default);
        $container->get(Twig::class)->getEnvironment()->addGlobal('app_locale', $default);

        parent::tearDown();
    }

    public function test_un_pedido_explicito_cambia_el_idioma_en_la_misma_request(): void
    {
        $response = self::app()->handle(self::request('GET', '/?lang=en'));
        $html = (string) $response->getBody();
        $env = (string) Env::get('APP_ENV', 'local');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html lang="en"', $html, 'El <html lang> no siguió al locale resuelto.');
        self::assertStringContainsString('environment ' . $env, $html, 'La misma request no se renderizó en inglés.');
        self::assertStringNotContainsString('entorno ' . $env, $html);
        self::assertSame('en', $_SESSION['locale'] ?? null, 'La elección explícita no quedó en la sesión.');
        self::assertStringContainsString(
            'Accept-Language',
            $response->getHeaderLine('Vary'),
            'Sin Vary: Accept-Language un caché puede servir el idioma equivocado.'
        );
    }

    public function test_sin_explicito_manda_la_sesion(): void
    {
        $_SESSION['locale'] = 'en';

        $html = (string) self::app()->handle(
            self::request('GET', '/', [], ['Accept-Language' => 'es'])
        )->getBody();

        self::assertStringContainsString('<html lang="en"', $html);
        self::assertStringContainsString('environment', $html);
    }

    public function test_sin_nada_resuelve_el_default(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        self::assertStringContainsString('<html lang="es"', $html);
        self::assertStringContainsString('entorno', $html);
    }

    public function test_la_request_siguiente_no_hereda_el_idioma_de_la_anterior(): void
    {
        unset($_SESSION['locale']);

        $primera = (string) self::app()->handle(
            self::request('GET', '/', [], ['Accept-Language' => 'en'])
        )->getBody();

        $segunda = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        self::assertStringContainsString('<html lang="en"', $primera);
        self::assertStringContainsString(
            '<html lang="es"',
            $segunda,
            'La segunda request heredó el inglés: el locale no se fija en cada petición (estado del proceso).'
        );
    }

    /**
     * Caso API: sin sesión, la cabecera gana y no se intenta abrirla.
     *
     * El proceso nuevo es obligatorio: la sesión de la suite está abierta desde
     * `tests/bootstrap.php` y no hay forma de volver a `PHP_SESSION_NONE`.
     */
    public function test_sin_sesion_gana_accept_language_y_no_se_abre_una_sesion(): void
    {
        $script = <<<'PHP'
        require 'vendor/autoload.php';

        use Framework\Http\Middleware\LocaleMiddleware;
        use Framework\I18n\Trans;
        use Framework\I18n\TranslatorFactory;
        use Psr\Http\Message\ResponseInterface;
        use Psr\Http\Message\ServerRequestInterface;
        use Psr\Http\Server\RequestHandlerInterface;
        use Slim\Psr7\Factory\ResponseFactory;
        use Slim\Psr7\Factory\ServerRequestFactory;
        use Slim\Views\Twig;

        $translator = TranslatorFactory::create();
        Trans::register($translator);

        $middleware = new LocaleMiddleware($translator, Twig::create(getcwd() . '/resources/views'));

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = (new ResponseFactory())->createResponse();
                $response->getBody()->write(Trans::t('pagination.first'));

                return $response;
            }
        };

        $factory = new ServerRequestFactory();
        $first = $middleware->process(
            $factory->createServerRequest('GET', 'http://localhost/api')->withHeader('Accept-Language', 'en'),
            $handler
        );
        $second = $middleware->process(
            $factory->createServerRequest('GET', 'http://localhost/api'),
            $handler
        );

        echo json_encode([
            'first'   => (string) $first->getBody(),
            'vary'    => $first->getHeaderLine('Vary'),
            'session' => session_status(),
            'second'  => (string) $second->getBody(),
        ]);
        PHP;

        $run = self::isolated($script);

        self::assertSame(0, $run['exit'], 'El middleware falló sin sesión: ' . $run['stderr']);
        self::assertStringNotContainsString('Warning', $run['stderr'], $run['stderr']);
        self::assertStringNotContainsString('Fatal', $run['stderr'], $run['stderr']);
        self::assertStringNotContainsString('session_start', $run['stderr']);

        $data = json_decode($run['stdout'], true);

        self::assertIsArray($data, 'La salida del proceso no es JSON: ' . $run['stdout']);
        self::assertSame('First', $data['first'], 'Sin sesión y con Accept-Language: en tenía que salir inglés.');
        self::assertSame(
            'Primera',
            $data['second'],
            'La segunda request del mismo proceso heredó el inglés: el locale no se fija siempre.'
        );
        self::assertSame(PHP_SESSION_NONE, $data['session'], 'El middleware abrió una sesión que no existía.');
        self::assertContains('Accept-Language', explode(',', (string) $data['vary']));
    }

    /**
     * El orden real del pipeline: la sesión se abre ANTES de resolver el locale.
     *
     * En un proceso nuevo `session_status()` es `PHP_SESSION_NONE`. Si
     * `LocaleMiddleware` corriera antes de `SessionMiddleware`, no podría
     * escribir la elección en la sesión (la guarda lo impide, y un
     * `session_start()` posterior pisa `$_SESSION`), así que ver `en` en
     * `$_SESSION` al final de la request prueba el orden —sin leer el archivo de
     * configuración— y a la vez que el render salió en inglés.
     */
    public function test_la_sesion_se_abre_antes_de_resolver_el_locale(): void
    {
        $script = <<<'PHP'
        $app = require 'bootstrap/app.php';

        $request = (new Slim\Psr7\Factory\ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost/?lang=en');
        $response = $app->handle($request);

        echo json_encode([
            'session' => session_status(),
            'locale'  => $_SESSION['locale'] ?? null,
            'english' => str_contains((string) $response->getBody(), '<html lang="en"'),
        ]);
        PHP;

        $dir = sys_get_temp_dir() . '/locale-order-' . bin2hex(random_bytes(6));

        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            self::fail("No se pudo crear el directorio temporal '{$dir}'.");
        }

        try {
            $run = self::isolated($script, [
                'SESSION_SAVE_PATH' => $dir,
                'SESSION_NAME'      => 'locale_order_test',
                'DB_DATABASE'       => $dir . '/app.sqlite',
            ]);
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }

        self::assertSame(0, $run['exit'], 'La app falló en el proceso nuevo: ' . $run['stderr']);

        $data = json_decode($run['stdout'], true);

        self::assertIsArray($data, 'La salida del proceso no es JSON: ' . $run['stdout']);
        self::assertTrue($data['english'], 'La request no se renderizó en inglés.');
        self::assertSame(PHP_SESSION_ACTIVE, $data['session'], 'La sesión no quedó abierta durante la request.');
        self::assertSame(
            'en',
            $data['locale'],
            'La elección explícita no llegó a la sesión abierta: LocaleMiddleware corre antes de SessionMiddleware.'
        );
    }

    /**
     * Corre un script PHP en un proceso nuevo con el repo como cwd.
     *
     * @param array<string, string> $env variables que se suman al entorno actual
     *
     * @return array{stdout: string, stderr: string, exit: int}
     */
    private static function isolated(string $code, array $env = []): array
    {
        $script = tempnam(sys_get_temp_dir(), 'locale-test-');

        if ($script === false) {
            self::fail('No se pudo crear el script temporal del proceso aislado.');
        }

        $environment = array_merge(getenv(), [
            'APP_ENV'   => 'testing',
            'APP_DEBUG' => 'false',
        ], $env);

        // El locale de la suite no puede decidir el resultado del subproceso.
        unset($environment['APP_LOCALE'], $environment['APP_FALLBACK_LOCALE']);

        file_put_contents($script, "<?php\n\ndeclare(strict_types=1);\n\n" . $code);

        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__, 2),
            $environment
        );

        if (!is_resource($process)) {
            @unlink($script);
            self::fail('No se pudo ejecutar PHP en un proceso nuevo.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        @unlink($script);

        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }
}
