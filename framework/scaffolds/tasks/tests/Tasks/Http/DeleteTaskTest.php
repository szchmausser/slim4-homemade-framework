<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * El borrado, que es la acción del CRUD que depende de dónde viajan los tokens.
 *
 * Capa tests. htmx manda los params de un DELETE en el query string, y el `Guard`
 * sólo mira el cuerpo y los headers, nunca el query string; por eso el botón manda
 * los tokens con `hx-headers`. Los casos de acá miden las dos formas.
 *
 * Para tu entidad, copia el archivo y cambia el recurso.
 */
final class DeleteTaskTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // `/tareas` exige login (RequireAuth): estos casos entran logueados.
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

        // Es la medición que explica por qué el botón usa `hx-headers`: el guard
        // rechaza porque no lee el query string. El 400 es el fallo reproducido.
        self::assertSame(400, $response->getStatusCode());
        self::assertNotNull(Task::find($task->id), 'Guard rechazó: no se borra nada.');
    }

    /**
     * Sin htmx el request es un navegador pidiendo un recurso que no existe, así que
     * corresponde el 404. La rama de htmx (200 con aviso en el panel) tiene su caso en
     * `ToastFlowTest`: un 404 dentro del panel metería la página de error en la lista.
     */
    public function test_borrar_un_id_inexistente_sin_htmx_responde_404(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('DELETE', '/tareas/999999', $token)
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('No encontramos esa página.', (string) $response->getBody());
    }

    public function test_eliminar_pide_confirmacion_con_modal_y_sin_confirm_nativo(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $html = (string) self::app()->handle(self::request('GET', '/tareas'))->getBody();

        self::assertStringNotContainsString('hx-confirm', $html, 'El confirm() nativo tiene que estar muerto.');
        self::assertStringContainsString('<dialog', $html);
        self::assertStringContainsString('¿Borrar esta tarea?', $html);
        // Sólo importa que el borrado apunte al recurso correcto: la posición del
        // query string tiene su propio caso en `ListStateContractTest`.
        self::assertStringContainsString('hx-delete="/tareas/' . $task->id . '?', $html);
    }
}
