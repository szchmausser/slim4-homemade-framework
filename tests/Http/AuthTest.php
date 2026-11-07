<?php

declare(strict_types=1);

namespace Tests\Http;

use Framework\Auth\RememberToken;
use App\Http\Controllers\Auth\AuthController;
use App\Models\User;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\HttpTestCase;

/**
 * Auth v1: login/logout + remember-me + puerta de rutas protegidas.
 *
 * Los tests comparten proceso (y $_SESSION) con el resto de la suite: cada
 * test parte de sesión limpia y deja las tablas vacías. La cookie remember se
 * pasa a mano por header: el cliente de tests no guarda cookies solo.
 */
final class AuthTest extends HttpTestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Los tokens cuelgan del usuario por FK: se van antes que el usuario.
        RememberToken::query()->delete();
        parent::tearDown();
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

    /**
     * Un campo vacío no es un fallo de credenciales: el mensaje va pegado al
     * input que lo causó (per-field, con aria) y NO por el flash genérico.
     * El flash queda para credenciales y throttling, que no son un campo.
     */
    public function test_login_con_datos_invalidos_muestra_el_error_por_campo(): void
    {
        $token = self::token('/login');

        $response = self::app()->handle(
            self::request('POST', '/login', ['email' => '', 'password' => ''] + $token)
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('id="login-email-error"', $html);
        self::assertStringContainsString('id="login-password-error"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('aria-describedby="login-email-error"', $html);
        self::assertStringContainsString('Este campo es obligatorio.', $html);
        // El canal de credenciales no se usa para validación de campos.
        self::assertStringNotContainsString('Credenciales inválidas.', $html);
    }

    public function test_dummy_hash_es_un_bcrypt_valido_de_coste_12(): void
    {
        $info = password_get_info(AuthController::DUMMY_HASH);

        self::assertSame('bcrypt', $info['algoName']);
        self::assertSame(12, $info['options']['cost']);
        self::assertSame(60, strlen(AuthController::DUMMY_HASH));
        // El dummy nunca autentica, pero igual paga el coste de bcrypt.
        self::assertFalse(password_verify('cualquier-clave', AuthController::DUMMY_HASH));
    }

    public function test_login_con_credenciales_malas_no_entra_ni_dice_cual_fallo(): void
    {
        $this->createUser('yo@ejemplo.com', 'secreto123');
        $token = self::token('/login');

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

    /**
     * El login no es una pantalla de la app: dentro del shell ofrecia
     * navegacion a una ruta protegida, que sin sesion redirige de vuelta a este mismo
     * formulario. Era una puerta que no lleva a ningun lado, y ademas mostraba
     * el header, el toggle de tema y el footer de una app a la que todavia no
     * se entro. Vive en layouts/auth.twig, no en layouts/app.twig.
     */
    public function test_el_login_no_se_renderiza_dentro_del_shell(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/login'))->getBody();

        self::assertStringContainsString('action="/login"', $html);
        self::assertStringNotContainsString('id="sidebar"', $html);
        self::assertStringNotContainsString('href="/_test/protected"', $html);
    }

    public function test_login_valido_redirige_y_abre_sesion(): void
    {
        $user = $this->createUser();
        $token = self::token('/login');

        $response = self::app()->handle(
            self::request('POST', '/login', [
                'email'    => 'yo@ejemplo.com',
                'password' => 'secreto123',
            ] + $token)
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
        self::assertSame($user->id, $_SESSION['user_id'] ?? null);
    }

    public function test_login_con_remember_emite_cookie_y_guarda_solo_el_hash(): void
    {
        $this->createUser();
        $token = self::token('/login');

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
            self::request('GET', '/_test/protected', [], ['Cookie' => 'remember=' . $pair])
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
            self::request('GET', '/_test/protected', [], ['Cookie' => 'remember=' . $selector . ':' . str_repeat('0', 64)])
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
            self::request('GET', '/_test/protected', [], ['Cookie' => 'remember=expiretest1234567890abcd:' . $validator])
        );

        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertNull(RememberToken::query()->where('selector', 'expiretest1234567890abcd')->first());
        self::assertStringContainsString('Max-Age=0', $this->rememberRaw($response));
    }

    public function test_ruta_protegida_sin_login_redirige(): void
    {
        $response = self::app()->handle(self::request('GET', '/_test/protected'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function test_ruta_protegida_htmx_devuelve_hx_redirect(): void
    {
        $response = self::app()->handle(self::request('GET', '/_test/protected', [], ['HX-Request' => 'true']));

        // 200 + HX-Redirect (no 303): un redirect rompería el swap de HTMX.
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('HX-Redirect'));
    }

    public function test_logout_cierra_quema_remember_y_redirige(): void
    {
        $user = $this->createUser();
        // El token de logout se scrapea ANTES de entrar: logueado, GET /login
        // redirige a / y ya no hay form.
        $token = self::token('/login');
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

    public function test_login_estando_logueado_redirige_a_la_raiz(): void
    {
        self::loginAs();

        $response = self::app()->handle(self::request('GET', '/login'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
    }

    private function createUser(string $email = 'yo@ejemplo.com', string $password = 'secreto123'): User
    {
        return User::create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    /** Login con remember y devuelve el par crudo de la cookie emitida. */
    private function loginWithRemember(User $user): string
    {
        $token = self::token('/login');

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
}
