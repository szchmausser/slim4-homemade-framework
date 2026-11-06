<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * La D del CRUD, que es la única que depende de dónde viajan los tokens.
 *
 * htmx 2.0.11 trae `methodsThatUseUrlParams: ['get', 'delete']`, así que los
 * params que aporta `hx-include` en un DELETE van al query string. `Guard`
 * solo mira `getParsedBody()` y, si está vacío, los headers: el query string
 * nunca. Por eso el botón de eliminar manda los tokens con `hx-headers` y no
 * con `hx-include`.
 */
final class DeleteTaskTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        self::loginAs();
    }

    public function test_delete_con_los_tokens_en_headers_borra_la_tarea(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('DELETE', '/tareas/' . $task->id)
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
        $token = self::token();

        $response = self::app()->handle(
            self::request('DELETE', '/tareas/' . $task->id . '?' . http_build_query($token))
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

        $html = (string) self::app()->handle(self::request('GET', '/tareas'))->getBody();

        self::assertStringNotContainsString('hx-confirm', $html, 'El confirm() nativo tiene que estar muerto.');
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('¿Borrar esta tarea?', $html);
        // El query string de posicion lo fija tests/Http/ListStateContractTest;
        // aca solo importa que el borrado apunte al recurso correcto.
        self::assertStringContainsString('hx-delete="/tareas/' . $task->id . '?', $html);
    }
}
