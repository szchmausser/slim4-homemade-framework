<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\User;
use App\Support\Throttle;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

/**
 * Throttling del login: 5 fallos / 15 min por email + IP. El mensaje y el
 * status tienen que ser indistinguibles de unas credenciales invalidas
 * normales: si el bloqueo se delata, sirve para enumerar cuentas.
 *
 * Corre contra las migraciones reales (login_attempts viene de la 006) y
 * limpia la tabla en setUp/tearDown: la suite comparte proceso y base.
 */
final class LoginThrottleTest extends TestCase
{
    private const EMAIL = 'yo@ejemplo.com';

    private const PASSWORD = 'secreto123';

    private const IP = '203.0.113.7';

    protected function setUp(): void
    {
        $_SESSION = [];
        Capsule::table('login_attempts')->delete();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        Capsule::table('login_attempts')->delete();
        User::query()->delete();
    }

    public function test_cinco_fallos_bloquean_el_sexto_aun_con_la_clave_correcta(): void
    {
        $this->createUser();
        $token = $this->csrf();

        for ($i = 0; $i < Throttle::MAX_ATTEMPTS; $i++) {
            $response = $this->post('clave-errada', $token);

            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('Credenciales inválidas.', (string) $response->getBody());
        }

        self::assertSame(Throttle::MAX_ATTEMPTS, $this->attempts());

        // Clave correcta, pero la ventana ya esta llena: no entra.
        $response = $this->post(self::PASSWORD, $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);

        // Mismo mensaje que un fallo normal: no revela bloqueo ni existencia.
        $html = (string) $response->getBody();
        self::assertStringContainsString('Credenciales inválidas.', $html);
        self::assertStringNotContainsStringIgnoringCase('bloquead', $html);

        // El intento bloqueado no se registra: el contador no crece solo.
        self::assertSame(Throttle::MAX_ATTEMPTS, $this->attempts());
    }

    public function test_el_login_exitoso_limpia_el_contador(): void
    {
        $user = $this->createUser();
        $token = $this->csrf();

        for ($i = 0; $i < 4; $i++) {
            $this->post('clave-errada', $token);
        }
        self::assertSame(4, $this->attempts());

        $response = $this->post(self::PASSWORD, $token);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame($user->id, $_SESSION['user_id'] ?? null);
        self::assertSame(0, $this->attempts(), 'El login exitoso deberia limpiar los fallos.');

        // Y el contador limpio se nota en el comportamiento: 4 fallos nuevos
        // siguen por debajo del limite y la clave correcta vuelve a entrar.
        unset($_SESSION['user_id']);
        for ($i = 0; $i < 4; $i++) {
            $this->post('clave-errada', $token);
        }

        $response = $this->post(self::PASSWORD, $token);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame($user->id, $_SESSION['user_id'] ?? null);
    }

    public function test_la_ventana_expira_a_los_quince_minutos(): void
    {
        $user = $this->createUser();
        $token = $this->csrf();

        for ($i = 0; $i < Throttle::MAX_ATTEMPTS; $i++) {
            $this->post('clave-errada', $token);
        }
        self::assertTrue(Throttle::tooMany(self::EMAIL, self::IP));

        // Envejecer los fallos mas alla de la ventana (como haria el reloj).
        Capsule::table('login_attempts')
            ->where('email', self::EMAIL)
            ->where('ip', self::IP)
            ->update(['attempted_at' => time() - Throttle::WINDOW - 1]);

        self::assertFalse(Throttle::tooMany(self::EMAIL, self::IP));

        $response = $this->post(self::PASSWORD, $token);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame($user->id, $_SESSION['user_id'] ?? null);
    }

    public function test_el_bloqueo_es_por_email_y_por_ip(): void
    {
        $this->createUser();
        $otro = $this->createUser('otro@ejemplo.com', 'otra-clave');
        $token = $this->csrf();

        for ($i = 0; $i < Throttle::MAX_ATTEMPTS; $i++) {
            $this->post('clave-errada', $token);
        }

        // Mismo email desde otra IP: no hereda el bloqueo.
        $response = $this->post(self::PASSWORD, $token, self::EMAIL, '198.51.100.9');
        self::assertSame(303, $response->getStatusCode());

        // Otro email desde la IP bloqueada: tampoco hereda el bloqueo.
        $response = $this->post('otra-clave', $token, 'otro@ejemplo.com', self::IP);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame($otro->id, $_SESSION['user_id'] ?? null);
    }

    private function createUser(string $email = self::EMAIL, string $password = self::PASSWORD): User
    {
        return User::create([
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    private function attempts(): int
    {
        return Capsule::table('login_attempts')
            ->where('email', self::EMAIL)
            ->where('ip', self::IP)
            ->count();
    }

    private function post(
        string $password,
        array $token,
        string $email = self::EMAIL,
        string $ip = self::IP
    ): ResponseInterface {
        return self::app()->handle(
            self::request('POST', '/login', [
                'email'    => $email,
                'password' => $password,
            ] + $token, $ip)
        );
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    /** Token CSRF fresco de la pagina de login. */
    private function csrf(): array
    {
        $html = (string) self::app()->handle(self::request('GET', '/login'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name en /login');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value en /login');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    private static function request(
        string $method,
        string $path,
        array $body = [],
        string $ip = self::IP
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path, ['REMOTE_ADDR' => $ip]);

        if ($body !== []) {
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        return $request;
    }
}
