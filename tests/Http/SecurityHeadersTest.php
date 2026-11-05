<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Http\Middleware\SessionMiddleware;
use App\Models\RememberToken;
use App\Models\User;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\HttpTestCase;

/**
 * U5: el despliegue por HTTPS se activa con SESSION_SECURE=true.
 *
 * HSTS y la cookie remember-me viajan en la respuesta HTTP. La cookie de
 * sesión la emite PHP fuera del PSR-7, así que se verifica contra
 * session_get_cookie_params() cerrando la sesión compartida antes del request.
 */
final class SecurityHeadersTest extends HttpTestCase
{
    private const HSTS = 'max-age=31536000';

    /** @var array<string, mixed> */
    private array $env;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        unset($_ENV['SESSION_SECURE'], $_SESSION['user_id'], $_COOKIE['remember']);
    }

    protected function tearDown(): void
    {
        // Los tokens cuelgan del usuario por FK: se van antes que el usuario.
        RememberToken::query()->delete();
        unset($_COOKIE['remember']);
        parent::tearDown();
        $_ENV = $this->env;

        // La sesión nativa del proceso sigue viva: devolvé sus params al
        // default para no filtrar Secure al resto de la suite. No se pueden
        // cambiar con la sesión abierta, así que se cierra y se reabre.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            session_set_cookie_params(SessionMiddleware::cookieParams());
            session_start();
        }
    }

    public function test_por_defecto_no_hay_hsts_ni_secure(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $response = self::app()->handle(self::request('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Strict-Transport-Security'));
        self::assertFalse(SessionMiddleware::cookieParams()['secure']);
        self::assertFalse(
            session_get_cookie_params()['secure'],
            'Por defecto la cookie de sesión no debe ser Secure.'
        );

        $this->createUser();
        $login = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => 'security@ejemplo.com',
                'password' => 'secreto123',
                'remember' => '1',
            ] + self::token('/login'))
        );

        self::assertSame(303, $login->getStatusCode());
        self::assertStringNotContainsString('; Secure', $this->rememberRaw($login));
    }

    public function test_session_secure_activa_secure_y_hsts_tambien_en_errores(): void
    {
        $_ENV['SESSION_SECURE'] = 'true';

        self::assertTrue(SessionMiddleware::cookieParams()['secure']);

        // Cerrar la sesión compartida fuerza a que el próximo request pase por
        // session_set_cookie_params(), como en un arranque real.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $response = self::app()->handle(self::request('GET', '/'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(self::HSTS, $response->getHeaderLine('Strict-Transport-Security'));
        self::assertTrue(
            session_get_cookie_params()['secure'],
            'SessionMiddleware no aplicó Secure a la cookie de sesión.'
        );

        // El middleware de headers envuelve al de errores: un 404 también lo lleva.
        $error = self::app()->handle(self::request('GET', '/no-existe'));
        self::assertSame(404, $error->getStatusCode());
        self::assertSame(self::HSTS, $error->getHeaderLine('Strict-Transport-Security'));
    }

    public function test_remember_me_lleva_secure_en_alta_rotacion_y_borrado(): void
    {
        $_ENV['SESSION_SECURE'] = 'true';

        $user = $this->createUser();
        $token = self::token('/login');

        $login = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => $user->email,
                'password' => 'secreto123',
                'remember' => '1',
            ] + $token)
        );

        self::assertSame(303, $login->getStatusCode());
        $loginRaw = $this->rememberRaw($login);
        self::assertStringContainsString('; Secure', $loginRaw);
        $pair = $this->rememberPair($login);

        // Rotación: sin sesión, la cookie presentada se cambia por otra Secure.
        $_SESSION = [];
        $rotated = self::app()->handle(
            self::request('GET', '/tareas', [], ['Cookie' => 'remember=' . $pair])
        );

        self::assertSame(200, $rotated->getStatusCode());
        self::assertStringContainsString('; Secure', $this->rememberRaw($rotated));
        self::assertNotSame($pair, $this->rememberPair($rotated));

        // Borrado: logout quema el token y manda una cookie Secure con Max-Age=0.
        $logout = self::app()->handle(
            self::request('POST', '/logout', self::scrapeToken((string) $rotated->getBody()))
        );

        self::assertSame(303, $logout->getStatusCode());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($logout));
        self::assertStringContainsString('; Secure', $this->rememberRaw($logout));
    }

    private function createUser(): User
    {
        return User::create([
            'email'         => 'security@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ]);
    }

    private function rememberPair(ResponseInterface $response): string
    {
        return explode(';', $this->rememberRaw($response), 2)[0];
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
}
