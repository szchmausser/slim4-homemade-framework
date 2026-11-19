<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * El toggle y sus dos ramas: al completar guarda `completed_at`, al volver a
 * `pendiente` la limpia.
 *
 * Capa tests. También cubre el id inexistente en sus dos formas: panel con aviso para
 * htmx y 404 para un navegador. Para tu entidad, copia el archivo y cambia la ruta.
 */
final class ToggleTaskTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // `/tareas` exige login (RequireAuth): estos casos entran logueados.
        self::loginAs();
    }

    public function test_toggle_de_pendiente_a_completado_guarda_fecha_y_lo_pinta(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas/' . $task->id . '/toggle')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());

        $updated = Task::find($task->id);
        self::assertSame('completado', $updated->status, 'El status no cambió.');
        self::assertNotNull($updated->completed_at, 'Falta completed_at al completar.');

        $html = (string) $response->getBody();
        // El estado no se escribe al lado del título: viaja en el tooltip del icono,
        // así que el assert mira el `aria-label` y el nodo visual.
        self::assertStringContainsString('aria-label="Completada el ', $html, 'El icono no describe el estado nuevo.');
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
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas/' . $task->id . '/toggle')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());

        $updated = Task::find($task->id);
        self::assertSame('pendiente', $updated->status, 'El status no volvió a pendiente.');
        self::assertNull($updated->completed_at, 'completed_at no se limpió.');

        self::assertStringContainsString('Pendiente', (string) $response->getBody());
        self::assertStringNotContainsString('aria-label="Completada el', (string) $response->getBody(), 'Quedó el estado viejo en el icono.');
    }

    public function test_toggle_con_id_inexistente_avisa_en_el_panel_sin_500(): void
    {
        Task::create(['title' => 'Única']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas/999999/toggle')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());

        // Con `HX-Request` el aviso va en el header: si viajara también en el HTML se
        // vería dos veces. El payload es un objeto y los mensajes están en `.items`.
        $payload = json_decode($response->getHeaderLine('HX-Trigger-After-Swap'), true);

        self::assertSame(
            'La tarea no existe o ya fue eliminada.',
            $payload['toasts']['items'][0]['message'] ?? null
        );
        self::assertSame('error', $payload['toasts']['items'][0]['type'] ?? null);
        self::assertStringNotContainsString(
            'La tarea no existe o ya fue eliminada.',
            (string) $response->getBody(),
            'El aviso no puede ir en el header Y en el HTML.'
        );
    }

    /**
     * La otra mitad: este no es un request de htmx, es un navegador pidiendo el estado
     * de un recurso que no existe, así que corresponde el 404 y no un 200 con un aviso
     * que nadie escucha.
     */
    public function test_toggle_sin_htmx_de_un_id_inexistente_responde_404(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas/999999/toggle', $token)
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('No encontramos esa página.', (string) $response->getBody());
    }
}
