<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Models\Task;

/**
 * Búsqueda, filtro y orden del listado de Tareas, de punta a punta.
 *
 * Capa tests. Cada caso pide la URL que el usuario escribiría y mira el HTML:
 * el estado vive en la query string (S2), el controlador sólo acepta lo que
 * declara su whitelist (S3) y `Pagination` conserva ese estado en sus enlaces
 * (S4). Las filas se siembran con ids crecientes; el orden por defecto
 * (`created_at desc` + desempate por id) las muestra de la más nueva a la más
 * vieja, aunque todas compartan el segundo de creación.
 *
 * Para tu entidad, copia el archivo y cambia la ruta, las columnas y los textos.
 */
final class ListQueryFlowTest extends TasksTestCase
{
    protected function setUp(): void
    {
        self::loginAs();

        // El orden importa: el id crece con cada fila y desempata created_at.
        $tareas = [
            'Plan de lanzamiento' => 'pendiente',
            'Informe 100% real'   => 'completado',
            'Comprar pan'         => 'pendiente',
            'Revisar plan anual'  => 'completado',
            'Notas sueltas'       => 'pendiente',
            'Zeta final'          => 'completado',
            'Ciento 100X'         => 'pendiente',
        ];

        foreach ($tareas as $title => $status) {
            Task::create(['title' => $title, 'status' => $status]);
        }
    }

    // --- búsqueda ------------------------------------------------------------

    public function test_la_busqueda_filtra_por_titulo(): void
    {
        $html = self::page('/tasks?q=plan');

        self::assertStringContainsString('Plan de lanzamiento', $html);
        self::assertStringContainsString('Revisar plan anual', $html);
        self::assertStringNotContainsString('Comprar pan', $html, 'la búsqueda no puede dejar pasar otras filas');
        self::assertStringContainsString('Mostrando 1 al 2 de 2 tareas', $html);
    }

    /**
     * S3: `%` y `_` son comodines de `LIKE`. Escapados, `100%` busca el literal
     * y no «cualquier cosa que empiece con 100».
     */
    public function test_la_busqueda_escapa_los_comodines_de_like(): void
    {
        $html = self::page('/tasks?q=100%25');

        self::assertStringContainsString('Informe 100% real', $html);
        self::assertStringNotContainsString('Ciento 100X', $html, '100% no puede comportarse como un comodín');
        self::assertStringContainsString('Mostrando 1 al 1 de 1 tareas', $html);
    }

    // --- orden ---------------------------------------------------------------

    public function test_el_orden_cambia_con_sort_y_dir(): void
    {
        $ascendente = self::page('/tasks?sort=title&dir=asc');
        self::assertLessThan(
            self::posicion($ascendente, 'Zeta final'),
            self::posicion($ascendente, 'Comprar pan'),
            'dir=asc tiene que ordenar por título de la A a la Z'
        );

        $descendente = self::page('/tasks?sort=title&dir=desc');
        self::assertLessThan(
            self::posicion($descendente, 'Comprar pan'),
            self::posicion($descendente, 'Zeta final'),
            'dir=desc tiene que invertir el orden'
        );
    }

    public function test_sort_no_whitelisteado_no_cambia_el_orden(): void
    {
        $porDefecto = self::page('/tasks');
        $conId = self::page('/tasks?sort=id&dir=asc');

        self::assertLessThan(
            self::posicion($porDefecto, 'Plan de lanzamiento'),
            self::posicion($porDefecto, 'Ciento 100X'),
            'el default muestra primero la última tarea creada'
        );
        self::assertSame(
            self::posicion($porDefecto, 'Ciento 100X') < self::posicion($porDefecto, 'Plan de lanzamiento'),
            self::posicion($conId, 'Ciento 100X') < self::posicion($conId, 'Plan de lanzamiento'),
            'sort=id no es del whitelist: el orden tiene que seguir siendo el default'
        );
    }

    // --- filtro --------------------------------------------------------------

    public function test_el_filtro_solo_acepta_los_valores_declarados(): void
    {
        $pendientes = self::page('/tasks?status=pendiente');

        self::assertStringContainsString('Plan de lanzamiento', $pendientes);
        self::assertStringContainsString('Ciento 100X', $pendientes);
        self::assertStringNotContainsString('Informe 100% real', $pendientes, 'esa tarea está completada');
        self::assertStringContainsString('Mostrando 1 al 4 de 4 tareas', $pendientes);

        // Un valor fuera del whitelist se ignora en silencio; la lista sigue
        // completa y el status es 200.
        $basura = self::page('/tasks?status=borrado&otro=1');

        self::assertStringContainsString('Mostrando 1 al 7 de 7 tareas', $basura);
        self::assertStringContainsString('Informe 100% real', $basura);
    }

    // --- composición con paginación y acciones -------------------------------

    public function test_la_paginacion_conserva_la_busqueda(): void
    {
        foreach (range(1, 6) as $i) {
            Task::create(['title' => sprintf('Plan extra %02d', $i)]);
        }

        $html = self::page('/tasks?q=plan&per_page=5');

        self::assertStringContainsString('Mostrando 1 al 5 de 8 tareas', $html);
        self::assertStringContainsString(
            'href="/tasks?q=plan&page=2&per_page=5"',
            $html,
            'el enlace de página tiene que arrastrar q y usar & porque la base ya trae query string'
        );
    }

    public function test_las_acciones_de_fila_conservan_el_estado_de_la_lista(): void
    {
        $html = self::page('/tasks?q=plan&page=1&per_page=5');

        // Los atributos de `components/ui/button.twig` se autoescapan: el `&`
        // puede salir como `&amp;`, que es la misma URL para el navegador.
        $estado = 'page=1&(?:amp;)?per_page=5&(?:amp;)?q=plan';

        self::assertMatchesRegularExpression(
            '#hx-post="/tasks/\d+/toggle\?' . $estado . '"#',
            $html,
            'el toggle de una fila filtrada tiene que volver a la lista filtrada'
        );
        self::assertMatchesRegularExpression('#hx-get="/tasks/\d+/edit\?' . $estado . '"#', $html);
        self::assertMatchesRegularExpression('#hx-delete="/tasks/\d+\?' . $estado . '"#', $html);
    }

    /**
     * T2.3: el selector de tamaño es un GET y un GET reemplaza la query del
     * `action`. Sin hidden, elegir «25 por página» desde una lista filtrada
     * devolvía la lista sin búsqueda; con hidden, el estado viaja intacto.
     */
    public function test_el_selector_de_tamano_conserva_el_estado(): void
    {
        $html = self::page('/tasks?q=plan&status=pendiente');

        self::assertStringContainsString('<form method="get" action="/tasks"', $html);
        self::assertStringContainsString('<input type="hidden" name="q" value="plan">', $html);
        self::assertStringContainsString('<input type="hidden" name="status" value="pendiente">', $html);
        self::assertStringContainsString('<input type="hidden" name="sort" value="created_at">', $html);
        self::assertStringContainsString('<input type="hidden" name="dir" value="desc">', $html);
        self::assertStringContainsString('hx-include="closest form"', $html, 'htmx tiene que mandar también los hidden');
    }

    // --- encabezados ordenables ---------------------------------------------

    public function test_los_encabezados_declaran_aria_sort_y_togglean_la_direccion(): void
    {
        $default = self::page('/tasks');

        self::assertStringContainsString('aria-sort="descending"', $default, 'el default ordena por created_at desc');
        self::assertStringContainsString('aria-sort="none"', $default);

        $ascendente = self::page('/tasks?sort=title&dir=asc');

        self::assertMatchesRegularExpression(
            '#role="columnheader" aria-sort="ascending"[^>]*>\s*<a[^>]*href="/tasks\?sort=title&dir=desc"#',
            $ascendente,
            'la columna activa anuncia la dirección y su enlace la invierte'
        );
    }

    private static function page(string $path): string
    {
        return (string) self::app()->handle(self::request('GET', $path))->getBody();
    }

    private static function posicion(string $html, string $texto): int
    {
        $posicion = strpos($html, $texto);

        self::assertNotFalse($posicion, 'No se encontró «' . $texto . '» en el listado.');

        return (int) $posicion;
    }
}
