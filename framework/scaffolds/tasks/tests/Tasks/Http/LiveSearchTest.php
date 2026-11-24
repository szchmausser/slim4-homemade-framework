<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Models\Task;

/**
 * Búsqueda en vivo del listado de Tareas (S8).
 *
 * Capa tests. El formulario GET sigue funcionando sin JS: el submit normal no
 * se toca. Encima lleva los atributos de htmx: `hx-get` a la misma ruta,
 * `hx-trigger` con el debounce nativo (`input changed delay:400ms`), `change`
 * para el filtro de estado y `search` para la × del `type="search"`,
 * `hx-target` al panel, `hx-swap="outerHTML"` y `hx-push-url` para que la URL
 * siga siendo el estado y se pueda compartir.
 *
 * `hx-preserve` + `id` en el propio formulario es lo que evita que el swap se
 * lleve el input mientras se escribe: la respuesta trae un formulario nuevo,
 * pero htmx conserva el que ya está en el DOM (con el foco y el caret).
 *
 * Para tu entidad, copia el archivo y cambia la ruta, el id del panel y el id
 * del formulario.
 */
final class LiveSearchTest extends TasksTestCase
{
    protected function setUp(): void
    {
        self::loginAs();

        Task::create(['title' => 'Plan de lanzamiento']);
        Task::create(['title' => 'Comprar pan']);
        Task::create(['title' => 'Informe final', 'status' => 'completado']);
    }

    /** Lo que htmx recibe al tipear: el panel con las filas filtradas, no la página. */
    public function test_la_busqueda_en_vivo_devuelve_solo_el_panel_filtrado(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tasks?q=plan', [], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un documento completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
        self::assertStringContainsString('Plan de lanzamiento', $html);
        self::assertStringNotContainsString('Comprar pan', $html, 'la respuesta tiene que traer sólo las filas que matchean');
        self::assertStringContainsString(
            'aria-label="Limpiar búsqueda y filtros"',
            $html,
            'el swap trae también el control de limpiar, porque vive dentro del panel'
        );
    }

    /** El formulario declara el debounce, el destino y cómo conservar el foco. */
    public function test_el_formulario_declara_la_busqueda_en_vivo_y_conserva_el_foco(): void
    {
        $html = self::page('/tasks?q=plan&status=pendiente');
        $form = self::searchForm($html, 'tasks-search-form');

        self::assertSame(
            1,
            substr_count($html, 'id="tasks-search-form"'),
            'El id del formulario tiene que ser único en la página: hx-preserve se resuelve por id.'
        );
        self::assertStringContainsString('method="get"', $form, 'Sin JS el formulario tiene que seguir siendo un GET normal.');
        self::assertStringContainsString('action="/tasks"', $form);
        self::assertStringContainsString('hx-get="/tasks"', $form);
        self::assertStringContainsString(
            'hx-trigger="input changed delay:400ms, change, search"',
            $form,
            'El debounce es el delay del trigger y la × del type=search dispara `search`.'
        );
        self::assertStringContainsString('hx-target="#tareas-panel"', $form);
        self::assertStringContainsString('hx-swap="outerHTML"', $form);
        self::assertStringContainsString('hx-push-url="true"', $form);
        self::assertStringContainsString('hx-preserve="true"', $form, 'Sin hx-preserve el swap se lleva el input y el caret.');
        self::assertStringContainsString('type="search"', $form, 'El campo de búsqueda tiene que ser type=search para tener la × nativa.');
        self::assertStringContainsString('name="q"', $form);
    }

    /** Limpiar es un enlace a la ruta base (navegación completa) y sólo vive con estado. */
    public function test_el_enlace_de_limpiar_aparece_solo_con_estado_en_la_url(): void
    {
        $conEstado = self::page('/tasks?q=plan&status=pendiente');

        self::assertMatchesRegularExpression(
            '#<a[^>]*href="/tasks"[^>]*class="btn btn-ghost"[^>]*aria-label="Limpiar búsqueda y filtros"\s*>\s*<span>Limpiar</span>#',
            $conEstado,
            'El control de limpiar es un enlace normal a la ruta base, con texto y aria-label del catálogo.'
        );

        $sinEstado = self::page('/tasks');

        self::assertStringNotContainsString(
            'Limpiar búsqueda y filtros',
            $sinEstado,
            'Sin búsqueda, filtro ni orden explícitos no hay nada que limpiar.'
        );
        self::assertStringNotContainsString('<span>Limpiar</span>', $sinEstado);
    }

    private static function page(string $path): string
    {
        return (string) self::app()->handle(self::request('GET', $path))->getBody();
    }

    /**
     * HTML del formulario de búsqueda, del `<form>` a su `</form>`.
     *
     * Se extrae para que las aserciones de atributos no puedan matchear otro
     * elemento de la página (el `<select>` del paginador también lleva `hx-get`).
     */
    private static function searchForm(string $html, string $id): string
    {
        $patron = '#<form[^>]*id="' . preg_quote($id, '#') . '".*?</form>#s';

        self::assertSame(1, preg_match($patron, $html, $form), 'No se encontró el formulario `' . $id . '`.');

        return $form[0];
    }
}
