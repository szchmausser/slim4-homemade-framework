<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * La U de CRUD: la edición es inline en la fila.
 *
 * El form de edición vive dentro de `_list.twig`, o sea dentro del panel que
 * el swap reemplaza. Ahí no alcanzan los hidden de `#alta-tarea`, que queda
 * fuera de ese panel, así que el form lleva sus propios tokens CSRF.
 *
 * El PUT llega como `application/x-www-form-urlencoded` (el default de htmx
 * para verbos no-GET) y lo parsea `addBodyParsingMiddleware()`.
 */
final class EditTaskTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        self::loginAs();
    }

    public function test_get_edit_renderiza_el_formulario_de_la_fila(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('GET', '/tareas/' . $task->id . '/edit')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        // El query string de posicion lo fija tests/Http/ListStateContractTest;
        // aca solo importa que el guardado apunte al recurso correcto.
        self::assertStringContainsString('hx-put="/tareas/' . $task->id . '?', $html);
        self::assertStringContainsString('value="Comprar pan"', $html);
        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('Cancelar', $html);
    }

    /**
     * S5: sin htmx la petición es un navegador pidiendo un documento, y el
     * recurso que pidió no existe. Devolver 200 con un cartel es mentirle al
     * protocolo (un buscador indexa la URL, un proxy la cachea, un curl con
     * `--fail` no falla). El aviso vive dentro del panel porque ahí no hay
     * otro canal: en una página de error el mensaje lo pone el propio 404.
     */
    public function test_get_edit_de_un_id_inexistente_sin_htmx_responde_404(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas/999999/edit')
        );

        self::assertSame(404, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('No encontramos esa página.', $html, 'Tiene que salir la página de error, no el panel.');
        self::assertStringNotContainsString('tareas-panel', $html, 'Un fragmento de la app dentro de un 404 es el bug opuesto.');
    }

    /**
     * La otra mitad de S5: un request de htmx sigue recibiendo el panel
     * re-renderizado con el aviso, porque un 404 le metería la página de error
     * dentro de #tareas-panel.
     */
    public function test_get_edit_de_un_id_inexistente_con_htmx_avisa_en_el_panel(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas/999999/edit', [], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('tareas-panel', $html);
        self::assertStringNotContainsString('hx-put=', $html, 'No hay fila que editar.');

        $payload = json_decode($response->getHeaderLine('HX-Trigger-After-Swap'), true);

        self::assertSame(
            'La tarea no existe o ya fue eliminada.',
            $payload['toasts']['items'][0]['message'] ?? null
        );
        self::assertStringNotContainsString(
            'La tarea no existe o ya fue eliminada.',
            $html,
            'Con htmx el aviso va en el header, no en el HTML.'
        );
    }

    public function test_put_con_token_valido_actualiza_la_tarea(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan integral',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Comprar pan integral', $task->fresh()->title);
        self::assertStringContainsString('Comprar pan integral', (string) $response->getBody());
        // El swap vuelve a la lista normal: editing_id queda en null.
        self::assertStringNotContainsString('hx-put=', (string) $response->getBody());
    }

    /**
     * S5: un fallo de validación no es un 2xx. Además, el mensaje ya no va
     * por el flash genérico: vive pegado al campo (`errors.title`), con
     * `aria-invalid` para que el lector lo anuncie. Dos avisos del mismo
     * fallo (banner + campo) es ruido, no redundancia.
     */
    public function test_put_sin_titulo_devuelve_422_con_el_error_del_campo(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => '',
            ])
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title, 'La validación falló: el título no cambia.');

        $html = (string) $response->getBody();

        self::assertStringContainsString('Este campo es obligatorio.', $html, 'El mensaje en castellano tiene que llegar al campo.');
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringNotContainsString('alert-error', $html, 'El error de un campo no se repite como flash.');
        // La fila se queda en modo formulario: si volviera a la lista, el
        // usuario pierde el lugar que estaba editando junto con el error.
        self::assertStringContainsString('hx-put=', $html);
    }

    public function test_put_invalido_conserva_lo_tipeado_en_lugar_del_guardado(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();
        $tipeado = str_repeat('b', 200);

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => $tipeado,
            ])
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title, 'La validación falló: el título no cambia.');

        $html = (string) $response->getBody();

        self::assertStringContainsString(
            'value="' . $tipeado . '"',
            $html,
            'El input se repintó con el valor guardado y perdió lo tipeado.'
        );
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('No puede superar los 120 caracteres.', $html);
    }

    /**
     * S5: el mismo criterio que en el GET de edición. Sin htmx el recurso
     * pedido no existe y el 200 con un cartel le miente al protocolo.
     */
    public function test_put_a_un_id_inexistente_sin_htmx_responde_404(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/999999', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'No debería guardarse',
            ])
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('No encontramos esa página.', (string) $response->getBody());
        self::assertSame(0, Task::count(), 'No se creó ninguna fila nueva.');
    }

    public function test_put_a_un_id_inexistente_con_htmx_avisa_en_el_panel(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/999999', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'No debería guardarse',
            ], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = json_decode($response->getHeaderLine('HX-Trigger-After-Swap'), true);

        self::assertSame(
            'La tarea no existe o ya fue eliminada.',
            $payload['toasts']['items'][0]['message'] ?? null
        );
        self::assertSame(0, Task::count(), 'No se creó ninguna fila nueva.');
    }

    public function test_put_sin_token_devuelve_400_para_htmx(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, ['title' => 'No debería guardarse'], ['HX-Request' => 'true'])
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title);
    }
}
