<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * La edición inline: el panel vuelve con una fila en modo formulario.
 *
 * Capa tests. El form de edición vive dentro de `_list.twig`, o sea dentro de la
 * región que el swap reemplaza, así que lleva sus propios tokens CSRF: los hidden del
 * alta quedan fuera y no llegan. El PUT viaja como `application/x-www-form-urlencoded`
 * y lo parsea `addBodyParsingMiddleware()`.
 *
 * Para tu entidad, copia el archivo y cambia la ruta y los campos.
 */
final class EditTaskTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // `/tasks` exige login (RequireAuth): estos casos entran logueados.
        self::loginAs();
    }

    public function test_get_edit_renderiza_el_formulario_de_la_fila(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('GET', '/tasks/' . $task->id . '/edit')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('aria-label="Miga de pan"', $html);
        self::assertStringContainsString('aria-current="page">Tareas', $html);

        // Sólo el recurso destino: la posición del query string tiene su caso en
        // `ListStateContractTest`.
        self::assertStringContainsString('hx-put="/tasks/' . $task->id . '?', $html);
        self::assertStringContainsString('value="Comprar pan"', $html);
        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('Cancelar', $html);
    }

    /**
     * Sin htmx la petición es un navegador pidiendo un documento, y el recurso no
     * existe. Devolver 200 con un cartel le miente al protocolo: un buscador indexa la
     * URL, un proxy la cachea y un `curl --fail` no falla.
     */
    public function test_get_edit_de_un_id_inexistente_sin_htmx_responde_404(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tasks/999999/edit')
        );

        self::assertSame(404, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('No encontramos esa página.', $html, 'Tiene que salir la página de error, no el panel.');
        self::assertStringNotContainsString('tareas-panel', $html, 'Un fragmento de la app dentro de un 404 es el bug opuesto.');
    }

    /**
     * La otra mitad: un request de htmx recibe el panel re-renderizado con el aviso,
     * porque un 404 le metería la página de error dentro del panel.
     */
    public function test_get_edit_de_un_id_inexistente_con_htmx_avisa_en_el_panel(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tasks/999999/edit', [], ['HX-Request' => 'true'])
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
            self::request('PUT', '/tasks/' . $task->id, [
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
     * Un fallo de validación no es un 2xx, y el mensaje no va por el flash genérico:
     * vive pegado al campo (`errors.title`), con `aria-invalid` para el lector. Dos
     * avisos del mismo fallo serían ruido.
     */
    public function test_put_sin_titulo_devuelve_422_con_el_error_del_campo(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('PUT', '/tasks/' . $task->id, [
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
            self::request('PUT', '/tasks/' . $task->id, [
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
     * Mismo criterio que el GET de edición: sin htmx el recurso pedido no existe, así
     * que un 200 con un cartel le mentiría al protocolo.
     */
    public function test_put_a_un_id_inexistente_sin_htmx_responde_404(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('PUT', '/tasks/999999', [
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
            self::request('PUT', '/tasks/999999', [
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
            self::request('PUT', '/tasks/' . $task->id, ['title' => 'No debería guardarse'], ['HX-Request' => 'true'])
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title);
    }
}
