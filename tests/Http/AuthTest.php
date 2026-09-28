<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\RememberToken;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

/**
 * Auth v1: login/logout + remember-me + puerta de /tareas.
 *
 * Los tests comparten proceso (y $_SESSION) con el resto de la suite: cada
 * test parte de sesión limpia y deja las tablas vacías. La cookie remember se
 * pasa a mano por header: el cliente de tests no guarda cookies solo.
 */
final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        RememberToken::query()->delete();
        User::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_login_muestra_el_formulario(): void
    {
        $response = self::app()->handle(self::request('GET', '/login'));

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('type="password"', $html);
        self::assertStringContainsString('name="remember"', $html);
    }

    public function test_login_con_credenciales_malas_no_entra_ni_dice_cual_fallo(): void
    {
        $this->createUser('yo@ejemplo.com', 'secreto123');
        $token = $this->csrf();

        foreach ([
            ['email' => 'otro@ejemplo.com', 'password' => 'secreto123'], // email inexistente
            ['email' => 'yo@ejemplo.com', 'password' => 'clave-errada'], // clave errada
        ] as $body) {
            $response = self::app()->handle(
                self::request('POST', '/login', $body + $token)
            );

            self::assertSame(200, $response->getStatusCode());
            // El MISMO mensaje en ambos casos: decir cuál falló es enumeración.
            self::assertStringContainsString('Credenciales inválidas.', (string) $response->getBody());
        }

        self::assertArrayNotHasKey('user_id', $_SESSION);
    }

    public function test_login_valido_redirige_y_abre_sesion(): void
    {
        $user = $this->createUser();
        $token = $this->csrf();

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => 'yo@ejemplo.com',
                'password' => 'secreto123',
            ] + $token)
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/tareas', $response->getHeaderLine('Location'));
        self::assertSame($user->id, $_SESSION['user_id'] ?? null);
    }

    public function test_login_con_remember_emite_cookie_y_guarda_solo_el_hash(): void
    {
        $this->createUser();
        $token = $this->csrf();

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => 'yo@ejemplo.com',
                'password' => 'secreto123',
                'remember' => '1',
            ] + $token)
        );

        $pair = $this->rememberPair($response);
        self::assertMatchesRegularExpression('/^[0-9a-f]{24}:[0-9a-f]{64}$/', $pair);

        [$selector, $validator] = explode(':', $pair);
        $row = RememberToken::query()->where('selector', $selector)->first();

        self::assertNotNull($row, 'El token no se persistió.');
        // En base solo el hash: si se filtra la base, el par no sirve.
        self::assertNotSame($validator, $row->hashed_validator);
        self::assertTrue(password_verify($validator, $row->hashed_validator));
    }

    public function test_remember_reanuda_sesion_y_rota_el_token(): void
    {
        $user = $this->createUser();
        $pair = $this->loginWithRemember($user);

        // Request nueva sin sesión pero con la cookie.
        $_SESSION = [];
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], false, ['remember' => $pair])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($user->id, $_SESSION['user_id'] ?? null, 'La cookie no reanudó la sesión.');

        // Rotación: el selector presentado murió y nació otro.
        [$oldSelector] = explode(':', $pair);
        self::assertNull(RememberToken::query()->where('selector', $oldSelector)->first());
        self::assertSame(1, RememberToken::query()->where('user_id', $user->id)->count());

        $newPair = $this->rememberPair($response);
        self::assertNotSame($pair, $newPair);
    }

    public function test_remember_adulterado_no_entra_y_quema_todo(): void
    {
        $user = $this->createUser();
        $pair = $this->loginWithRemember($user);
        [$selector] = explode(':', $pair);

        $_SESSION = [];
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], false, ['remember' => $selector . ':' . str_repeat('0', 64)])
        );

        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertSame(0, RememberToken::query()->where('user_id', $user->id)->count());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_remember_expirado_no_entra_y_se_limpia(): void
    {
        $user = $this->createUser();
        $validator = str_repeat('a', 64);

        RememberToken::create([
            'selector'         => 'expiretest1234567890abcd',
            'user_id'          => $user->id,
            'hashed_validator' => password_hash($validator, PASSWORD_DEFAULT),
            'expires_at'       => time() - 60,
        ]);

        $_SESSION = [];
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], false, ['remember' => 'expiretest1234567890abcd:' . $validator])
        );

        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertNull(RememberToken::query()->where('selector', 'expiretest1234567890abcd')->first());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_ruta_protegida_sin_login_redirige(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function test_ruta_protegida_htmx_devuelve_hx_redirect(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas', [], true));

        // 200 + HX-Redirect (no 303): un redirect rompería el swap de HTMX.
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('HX-Redirect'));
    }

    public function test_logout_cierra_quema_remember_y_redirige(): void
    {
        $user = $this->createUser();
        // El token de logout se scrapea ANTES de entrar: logueado, GET /login
        // redirige a /tareas y ya no hay form.
        $token = $this->csrf();
        $this->loginWithRemember($user);

        $response = self::app()->handle(
            self::request('POST', '/logout', $token)
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertSame(0, RememberToken::query()->where('user_id', $user->id)->count());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_login_estando_logueado_redirige_a_tareas(): void
    {
        $user = $this->createUser();
        $_SESSION['user_id'] = $user->id;

        $response = self::app()->handle(self::request('GET', '/login'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/tareas', $response->getHeaderLine('Location'));
    }

    private function createUser(string $email = 'yo@ejemplo.com', string $password = 'secreto123'): User
    {
        return User::create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /** Token CSRF fresco de la página de login. */
    private function csrf(): array
    {
        $html = (string) self::app()->handle(self::request('GET', '/login'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name en /login');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value en /login');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    /** Login con remember y devuelve el par crudo de la cookie emitida. */
    private function loginWithRemember(User $user): string
    {
        $token = $this->csrf();

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => $user->email,
                'password' => 'secreto123',
                'remember' => '1',
            ] + $token)
        );

        self::assertSame(303, $response->getStatusCode());

        return $this->rememberPair($response);
    }

    private function rememberPair(ResponseInterface $response): string
    {
        $raw = $this->rememberRaw($response);

        self::assertNotSame('', $raw, 'No se emitió cookie remember.');

        return explode(';', $raw, 2)[0];
    }

    private function rememberRaw(ResponseInterface $response): string
    {
        foreach ($response->getHeader('Set-Cookie') as $line) {
            if (str_starts_with($line, 'remember=')) {
                return substr($line, strlen('remember='));
            }
        }

        return '';
    }

    private static function request(
        string $method,
        string $path,
        array $body = [],
        bool $htmx = false,
        array $cookies = []
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        if ($cookies !== []) {
            $pairs = [];
            foreach ($cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }
            $request = $request->withHeader('Cookie', implode('; ', $pairs));
        }

        // La cookie remember llega por header: hay que poblar $_COOKIE a mano,
        // el ServerRequest no lo hace solo.
        if (isset($cookies['remember'])) {
            $_COOKIE['remember'] = $cookies['remember'];
        } else {
            unset($_COOKIE['remember']);
        }

        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
