<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * El toggle era la ÚNICA acción HTMX sin cobertura de tests, y ahí se coló
 * el bug: `now()` global no existe (solo `Illuminate\Support\now()`), el
 * controller reventaba con 500 y HTMX, al recibir un status de error, no
 * swapea: sin toast, sin cambio, falla muda. Acá se cubren las dos ramas
 * del toggle y el id inexistente.
 */
final class ToggleTaskTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        //
        // Acá había un GET /login previo: en un proceso fresco session_start()
        // pisaba $_SESSION y el login manual se perdía. tests/bootstrap.php ya
        // abre la sesión centralmente, así que ese request quedó muerto y se
        // fue con la duplicación.
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
        // El estado ya no se escribe al lado del título: viaja en el tooltip del
        // icono (aria-label + nodo con role visual). El assert sigue siendo el
        // mismo dato, ahora en su nuevo lugar.
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

        // Con HX-Request el aviso va en el header (canal único: si viajara
        // también en el HTML, se vería dos veces). El layout lo consume con
        // @toasts.window y el payload es un objeto, así que el array de
        // mensajes está en .items.
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
     * S5: la otra mitad del test de arriba. Este no es un request de htmx: es
     * un navegador pidiendo el estado de un recurso que no existe, así que
     * corresponde el 404 y no un 200 con un toast que nadie escucha.
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
