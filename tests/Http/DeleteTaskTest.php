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
 * La D del CRUD, que es la única que depende de dónde viajan los tokens.
 *
 * htmx 2.0.11 trae `methodsThatUseUrlParams: ['get', 'delete']`, así que los
 * params que aporta `hx-include` en un DELETE van al query string. `Guard`
 * solo mira `getParsedBody()` y, si está vacío, los headers: el query string
 * nunca. Por eso el botón de eliminar manda los tokens con `hx-headers` y no
 * con `hx-include`.
 */
final class DeleteTaskTest extends TestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
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

    public function test_delete_con_los_tokens_en_headers_borra_la_tarea(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('DELETE', '/tareas/' . $task->id)
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id), 'La tarea siguió existiendo.');
        self::assertStringNotContainsString('Comprar pan', (string) $response->getBody());
    }

    public function test_delete_con_los_tokens_en_query_string_no_pasa_el_guard(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('DELETE', '/tareas/' . $task->id . '?' . http_build_query($token))
                ->withHeader('HX-Request', 'true')
        );

        // No es el comportamiento deseado: es la medición que explica por qué
        // el botón usa hx-headers. Guard no lee el query string, así que este
        // 400 es el bug del usuario reproducido tal cual.
        self::assertSame(400, $response->getStatusCode());
        self::assertNotNull(Task::find($task->id), 'Guard rechazó: no se borra nada.');
    }

    public function test_eliminar_pide_confirmacion_con_modal_y_sin_confirm_nativo(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $html = (string) self::app()->handle($this->request('GET', '/tareas'))->getBody();

        self::assertStringNotContainsString('hx-confirm', $html, 'El confirm() nativo tiene que estar muerto.');
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('¿Borrar esta tarea?', $html);
        self::assertStringContainsString('hx-delete="/tareas/' . $task->id . '"', $html);
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig. El name real del
     * input es `csrf_name` / `csrf_value`: `csrf_name_key` es variable de Twig
     * y no aparece en el HTML renderizado.
     */
    private function token(): array
    {
        $html = (string) self::app()->handle($this->request('GET', '/tareas'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    private static function request(string $method, string $path): ServerRequestInterface
    {
        // URI absoluta, igual que en el resto de la suite: con path-only el
        // host queda vacío y Slim se porta distinto en los casos borde.
        return (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);
    }
}
