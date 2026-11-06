<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * Qué recibe htmx y qué recibe el navegador en la misma ruta.
 *
 * Con `HX-Request` la respuesta tiene que ser el panel y nada más: el botón
 * "Cancelar" apunta a `/tareas` con `hx-target="#tareas-panel"` y
 * `hx-swap="outerHTML"`, así que si el servidor devuelve `index.twig` (que
 * extiende el layout) htmx recibe un documento completo, se queda con el
 * `<body>` y lo inserta dentro del panel: quedan dos `<h1>`, dos
 * `id="alta-tarea"` y el padding del layout dos veces.
 *
 * Sin `HX-Request` es un navegador pidiendo una página (form con JS apagado,
 * URL directa, curl): ahí el fragmento es el bug opuesto —markup sin `<head>`
 * ni hoja de estilos— y la respuesta tiene que ser el documento entero (S9).
 */
final class PartialRenderTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        self::loginAs();
    }

    public function test_get_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('id="alta-tarea"', $html);
    }

    public function test_get_con_hx_request_devuelve_solo_el_panel(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas', [], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un <html> completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
    }

    /**
     * S9: una mutación invocada SIN htmx (form normal con JS apagado, URL
     * directa, un curl) no puede devolver `tasks/_panel.twig`. Ese fragmento
     * viaja sin `<head>`, sin hoja de estilos y sin shell: el navegador lo
     * pinta como texto plano y la acción, aunque haya funcionado, parece
     * rota. El contrato es el mismo que ya cumple `index()`: la región para
     * htmx, la página entera para todo lo demás.
     */
    public function test_post_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, Task::count(), 'La tarea no se creó: la respuesta no es la que se está probando.');

        $this->assertDocumentoCompleto((string) $response->getBody());
    }

    /**
     * El par del test de arriba: la rama de htmx no cambió. Sin esto, un
     * `respond()` que devolviera siempre la página entera rompería cada swap
     * (el `<body>` entero dentro del panel) y la suite de toasts no lo vería:
     * ella mira los avisos, no la forma del documento.
     */
    public function test_post_con_hx_request_devuelve_solo_el_panel(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un <html> completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
    }

    public function test_delete_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('DELETE', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id), 'La tarea no se borró: la respuesta no es la que se está probando.');

        $this->assertDocumentoCompleto((string) $response->getBody());
    }

    /**
     * Lo que hace que el navegador pueda pintar la respuesta por su cuenta.
     * El link de la hoja de estilos es el assert que de verdad separa una
     * página de un fragmento: `<!DOCTYPE html>` podría venir de un `_panel`
     * mal armado, pero sin `<link rel="stylesheet">` la página sigue sin
     * estilos y el bug de S9 continúa.
     */
    private function assertDocumentoCompleto(string $html): void
    {
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'Sin doctype el navegador queda en modo quirks.');
        self::assertStringContainsString('<html', $html, 'La mutación devolvió un fragmento, no una página.');
        self::assertStringContainsString('rel="stylesheet"', $html, 'Sin la hoja de estilos la página se ve sin CSS.');
        self::assertStringContainsString('/assets/daisyui-5.7.46.css', $html);
    }
}
