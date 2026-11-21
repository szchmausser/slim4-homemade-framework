<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * Qué recibe htmx y qué recibe el navegador en la misma ruta.
 *
 * Capa tests. Con `HX-Request` la respuesta es el panel y nada más: si llegara
 * `index.twig` (que extiende el layout), el swap anidaría el documento entero. Sin
 * `HX-Request` el fragmento es el problema opuesto: markup sin `<head>` ni estilos.
 *
 * Para tu entidad, copia el archivo y cambia la ruta.
 */
final class PartialRenderTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // `/tasks` exige login (RequireAuth): estos casos entran logueados.
        self::loginAs();
    }

    public function test_get_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tasks')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('id="alta-tarea"', $html);
    }

    public function test_get_con_hx_request_devuelve_solo_el_panel(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tasks', [], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un <html> completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
    }

    /**
     * Una mutación sin htmx (un form con JS apagado, una URL directa, un curl) no puede
     * devolver `_panel.twig`: ese fragmento viaja sin `<head>`, sin hoja de estilos y sin
     * shell, así que la acción funciona pero parece rota.
     */
    public function test_post_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tasks', [
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
     * El par del caso anterior: si `respond()` devolviera siempre la página entera,
     * cada swap anidaría el `<body>`. La suite de avisos no lo vería, porque mira los
     * avisos y no la forma del documento.
     */
    public function test_post_con_hx_request_devuelve_solo_el_panel(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tasks', [
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
            self::request('DELETE', '/tasks/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id), 'La tarea no se borró: la respuesta no es la que se está probando.');

        $this->assertDocumentoCompleto((string) $response->getBody());
    }

    /**
     * Lo que hace que el navegador pueda pintar la respuesta: el `<link>` de la hoja de
     * estilos separa una página de un fragmento, porque el doctype también podría venir
     * de un panel mal armado.
     */
    private function assertDocumentoCompleto(string $html): void
    {
        self::assertStringContainsString('<!DOCTYPE html>', $html, 'Sin doctype el navegador queda en modo quirks.');
        self::assertStringContainsString('<html', $html, 'La mutación devolvió un fragmento, no una página.');
        self::assertStringContainsString('rel="stylesheet"', $html, 'Sin la hoja de estilos la página se ve sin CSS.');
        self::assertStringContainsString('/assets/daisyui-5.7.46.css', $html);
    }
}
