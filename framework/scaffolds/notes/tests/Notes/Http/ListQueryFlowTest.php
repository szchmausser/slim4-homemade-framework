<?php

declare(strict_types=1);

namespace Tests\Notes\Http;

use App\Models\Note;
use Tests\Support\HttpTestCase;

/**
 * Búsqueda y orden del listado de Notas, de punta a punta.
 *
 * Capa tests. La búsqueda recorre `title` y `body` (el término puede estar en
 * cualquiera de los dos), el orden sólo acepta `title` y `created_at`, y la
 * paginación conserva `q`. El default es `created_at desc` con desempate por id:
 * en los tests todas las filas comparten el segundo de creación, así que el
 * orden visible es el de creación inverso.
 *
 * Para tu entidad, copia el archivo y cambia la ruta y las columnas.
 */
final class ListQueryFlowTest extends HttpTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
        Note::query()->delete();

        Note::create(['title' => 'Plan semanal']);
        Note::create(['title' => 'Comprar pan', 'body' => 'Incluye el plan de comidas']);
        Note::create(['title' => 'Zeta final']);
        Note::create(['title' => 'Nota suelta', 'body' => 'Sin plan todavía']);
        Note::create(['title' => 'Alfa']);
    }

    protected function tearDown(): void
    {
        Note::query()->delete();
        parent::tearDown();
    }

    public function test_la_busqueda_recorre_titulo_y_cuerpo(): void
    {
        $html = self::page('/notes?q=plan');

        self::assertStringContainsString('Plan semanal', $html, 'coincide por título');
        self::assertStringContainsString('Comprar pan', $html, 'coincide por cuerpo');
        self::assertStringContainsString('Nota suelta', $html, 'coincide por cuerpo');
        self::assertStringNotContainsString('Zeta final', $html);
        self::assertStringContainsString('Mostrando 1 al 3 de 3 registros', $html);
    }

    public function test_el_orden_cambia_con_sort_y_dir(): void
    {
        $ascendente = self::page('/notes?sort=title&dir=asc');
        self::assertLessThan(
            self::posicion($ascendente, 'Zeta final'),
            self::posicion($ascendente, 'Alfa'),
            'dir=asc ordena por título de la A a la Z'
        );

        $descendente = self::page('/notes?sort=title&dir=desc');
        self::assertLessThan(
            self::posicion($descendente, 'Alfa'),
            self::posicion($descendente, 'Zeta final'),
            'dir=desc invierte el orden'
        );
    }

    public function test_sort_no_whitelisteado_no_cambia_el_orden(): void
    {
        $porDefecto = self::page('/notes');
        $conId = self::page('/notes?sort=id&dir=asc');

        self::assertLessThan(
            self::posicion($porDefecto, 'Plan semanal'),
            self::posicion($porDefecto, 'Alfa'),
            'el default muestra primero la última nota creada'
        );
        self::assertSame(
            self::posicion($porDefecto, 'Alfa') < self::posicion($porDefecto, 'Plan semanal'),
            self::posicion($conId, 'Alfa') < self::posicion($conId, 'Plan semanal'),
            'sort=id no es del whitelist: el orden tiene que seguir siendo el default'
        );
    }

    public function test_la_paginacion_conserva_la_busqueda(): void
    {
        foreach (range(1, 12) as $i) {
            Note::create(['title' => sprintf('Plan %02d', $i)]);
        }

        $html = self::page('/notes?q=plan');

        self::assertStringContainsString('Mostrando 1 al 10 de 15 registros', $html);
        self::assertStringContainsString(
            'href="/notes?q=plan&page=2&per_page=10"',
            $html,
            'el enlace de página conserva q y usa & porque la base ya trae query string'
        );
    }

    public function test_el_selector_de_tamano_conserva_el_estado(): void
    {
        $html = self::page('/notes?q=plan');

        self::assertStringContainsString('<form method="get" action="/notes"', $html);
        self::assertStringContainsString('<input type="hidden" name="q" value="plan">', $html);
        self::assertStringContainsString('<input type="hidden" name="sort" value="created_at">', $html);
        self::assertStringContainsString('<input type="hidden" name="dir" value="desc">', $html);
        self::assertStringContainsString('hx-include="closest form"', $html);
    }

    /**
     * El lápiz de la fila edita la nota sin perder el estado de la lista (el
     * título ya lo conservaba). Sin estado, la URL tiene que ser la histórica.
     */
    public function test_el_lapiz_de_edicion_conserva_el_estado_y_sin_estado_no_lo_agrega(): void
    {
        $note = Note::query()->where('title', 'Plan semanal')->firstOrFail();

        $conEstado = self::page('/notes?q=plan&sort=title&dir=asc');

        self::assertSame(
            '/notes/' . $note->id . '/edit?q=plan&sort=title&dir=asc',
            self::editButtonHref($conEstado, 'Plan semanal'),
            'el lápiz tiene que arrastrar q/sort/dir como el link del título'
        );

        $sinEstado = self::page('/notes');

        self::assertSame(
            '/notes/' . $note->id . '/edit',
            self::editButtonHref($sinEstado, 'Plan semanal'),
            'sin estado la URL del lápiz es la histórica, sin query string'
        );
    }

    public function test_los_encabezados_declaran_aria_sort(): void
    {
        $default = self::page('/notes');

        self::assertStringContainsString('aria-sort="descending"', $default, 'el default ordena por created_at desc');
        self::assertStringContainsString('aria-sort="none"', $default);

        $ascendente = self::page('/notes?sort=title&dir=asc');

        self::assertMatchesRegularExpression(
            '#role="columnheader" aria-sort="ascending"[^>]*>\s*<a[^>]*href="/notes\?sort=title&dir=desc"#',
            $ascendente,
            'la columna activa anuncia la dirección y su enlace la invierte'
        );
    }

    private static function page(string $path): string
    {
        return (string) self::app()->handle(self::request('GET', $path))->getBody();
    }

    /**
     * `href` del botón-lápiz de una fila, decodificado: el componente autoescapa
     * el `&` del query string a `&amp;`.
     */
    private static function editButtonHref(string $html, string $title): string
    {
        $aria = strpos($html, 'aria-label="Editar ' . $title . '"');
        self::assertNotFalse($aria, 'No se encontró el lápiz de edición de «' . $title . '».');

        $start = strrpos(substr($html, 0, (int) $aria), '<a ');
        self::assertNotFalse($start, 'El lápiz no se renderizó como <a>.');

        $end = strpos($html, '>', (int) $start);
        self::assertNotFalse($end, 'No se pudo cerrar la etiqueta del lápiz.');

        $anchor = substr($html, (int) $start, (int) $end - (int) $start);
        self::assertSame(1, preg_match('/href="([^"]*)"/', $anchor, $match), 'El lápiz no tiene href.');

        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function posicion(string $html, string $texto): int
    {
        $posicion = strpos($html, $texto);

        self::assertNotFalse($posicion, 'No se encontró «' . $texto . '» en el listado.');

        return (int) $posicion;
    }
}
