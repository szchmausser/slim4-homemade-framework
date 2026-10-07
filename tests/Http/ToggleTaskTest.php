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
 * El toggle era la ÚNICA acción HTMX sin cobertura de tests, y ahí se coló
 * el bug: `now()` global no existe (solo `Illuminate\Support\now()`), el
 * controller reventaba con 500 y HTMX, al recibir un status de error, no
 * swapea: sin toast, sin cambio, falla muda. Acá se cubren las dos ramas
 * del toggle y el id inexistente.
 */
final class ToggleTaskTest extends TestCase
{
    protected function setUp(): void
    {
        // Arranque de sesión ANTES de setear user_id: en el primer request de
        // un proceso fresco, session_start() pisa $_SESSION y el login manual
        // se pierde (mismo trampa que DeleteTaskTest aislado). Un GET público
        // arranca la sesión; recién ahí se inyecta el usuario.
        self::app()->handle($this->request('GET', '/login'));
        $_SESSION['user_id'] = User::create([
            'email'         => 'toggle@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        User::query()->delete();
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_toggle_de_pendiente_a_completado_guarda_fecha_y_lo_pinta(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas/' . $task->id . '/toggle')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());

        $updated = Task::find($task->id);
        self::assertSame('completado', $updated->status, 'El status no cambió.');
        self::assertNotNull($updated->completed_at, 'Falta completed_at al completar.');

        $html = (string) $response->getBody();
        self::assertStringContainsString('Completada el', $html, 'El panel no pinta el estado nuevo.');
        self::assertStringContainsString('Desmarcar', $html, 'El botón no cambió de etiqueta.');
    }

    public function test_toggle_de_completado_a_pendiente_limpia_fecha(): void
    {
        $task = Task::create([
            'title'        => 'Ya hecha',
            'status'       => 'completado',
            'completed_at' => '2026-01-01 10:00:00',
        ]);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas/' . $task->id . '/toggle')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());

        $updated = Task::find($task->id);
        self::assertSame('pendiente', $updated->status, 'El status no volvió a pendiente.');
        self::assertNull($updated->completed_at, 'completed_at no se limpió.');

        self::assertStringContainsString('Pendiente', (string) $response->getBody());
    }

    public function test_toggle_con_id_inexistente_avisa_en_el_panel_sin_500(): void
    {
        Task::create(['title' => 'Única']);
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas/999999/toggle')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'La tarea no existe o ya fue eliminada.',
            (string) $response->getBody()
        );
    }

    /**
     * Tokens frescos scrapeados de GET /tareas (mismo patrón que DeleteTaskTest).
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
        return (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);
    }
}
