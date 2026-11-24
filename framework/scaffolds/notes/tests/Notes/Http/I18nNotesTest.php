<?php

declare(strict_types=1);

namespace Tests\Notes\Http;

use App\Models\Note;
use Tests\Support\HttpTestCase;

/**
 * El demo Notes sale del catálogo y cambia de idioma.
 *
 * El demo nació en inglés completo (L16 de `odd/tasks/demo-conventions.md`) y
 * sus dos catálogos decían lo mismo, así que la pantalla era idéntica en `es` y
 * `en`. L10 de `odd/tasks/i18n.md` revirtió esa decisión: era el único demo que
 * no cambiaba de idioma y eso impedía que el tutorial —cuya entidad es `notes`—
 * reusara sus claves. Hoy `notes.*` se traduce como `tasks.*` y `contacts.*`, y
 * también su etiqueta de menú (`nav.notes`): en español el sidebar dice
 * «Inicio · Tareas · Contactos · Notas».
 *
 * El caso en español corre sin `?lang=`: el default del proyecto es `es`, así
 * que es lo que ve quien abre el demo sin elegir idioma. El caso en inglés pide
 * `?lang=en` explícito, que además queda en la sesión (lo limpia el tearDown de
 * `HttpTestCase`, así que no contamina al test siguiente).
 */
final class I18nNotesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
        Note::query()->delete();
    }

    protected function tearDown(): void
    {
        Note::query()->delete();
        parent::tearDown();
    }

    /** Texto del render con las entidades HTML decodificadas. */
    private static function texto(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** El catálogo español: cada superficie de la pantalla sale en castellano. */
    public function test_la_pantalla_sale_en_espanol_por_defecto(): void
    {
        $note = Note::create(['title' => 'Groceries', 'body' => 'Milk']);

        $html = self::texto((string) self::app()->handle(self::request('GET', '/notes'))->getBody());

        self::assertStringContainsString('<title>Notas</title>', $html);
        self::assertStringContainsString('Tus notas, con alta y acciones por fila.', $html);
        self::assertStringContainsString('<span>Agregar nota</span>', $html);
        self::assertStringContainsString('Todas las notas', $html);
        self::assertStringContainsString('Nueva nota', $html);
        self::assertStringContainsString('Ponle un título; el cuerpo es opcional.', $html);
        self::assertStringContainsString('<span>Crear nota</span>', $html);
        self::assertStringContainsString('Borrar nota', $html);
        self::assertStringContainsString('¿Borrar «Groceries»? No se puede deshacer.', $html);
        self::assertStringContainsString('aria-label="Editar Groceries"', $html);
        self::assertStringContainsString('aria-label="Borrar Groceries"', $html);
        self::assertMatchesRegularExpression('/for="title">\s*Título/', $html, 'La etiqueta del campo no salió del catálogo.');
        self::assertStringContainsString('Cuerpo (opcional)', $html);

        // Listado: búsqueda y encabezados ordenables.
        self::assertStringContainsString('<span>Buscar</span>', $html);
        self::assertStringContainsString('placeholder="Buscar en título y cuerpo"', $html);
        self::assertStringContainsString('aria-label="Ordenar por"', $html);

        // Sidebar y miga: el nombre del módulo también sale del catálogo.
        self::assertStringContainsString('>Notas<', $html, 'El sidebar en español no traduce el nombre del módulo.');
        self::assertStringContainsString('aria-current="page">Notas', $html, 'La miga en español no traduce el nombre del módulo.');
        self::assertStringNotContainsString('>Notes<', $html, 'El sidebar en español sigue mostrando el nombre en inglés.');

        // El modal de edición vive en la página completa: el swap de htmx
        // devuelve `_panel.twig`, que no lo incluye.
        $edit = self::texto((string) self::app()->handle(
            self::request('GET', '/notes/' . $note->id . '/edit')
        )->getBody());

        self::assertStringContainsString('Editar nota', $edit);
        self::assertStringContainsString('<span>Guardar cambios</span>', $edit);
        self::assertStringContainsString('<span>Cancelar</span>', $edit);
        self::assertStringNotContainsString('Add note', $edit);
    }

    /**
     * El mismo demo en inglés: la pantalla cambia con el locale.
     *
     * Las dos puntas del menú también: en español el sidebar dice «Notas» y en
     * inglés «Notes», igual que `nav.tasks` dice «Tareas»/«Tasks».
     */
    public function test_el_demo_sale_en_ingles_con_lang_en(): void
    {
        $note = Note::create(['title' => 'Groceries', 'body' => 'Milk']);

        $html = self::texto((string) self::app()->handle(self::request('GET', '/notes?lang=en'))->getBody());

        self::assertStringContainsString('<title>Notes</title>', $html);
        self::assertStringContainsString('Your notes, with creation and row actions.', $html);
        self::assertStringContainsString('<span>Add note</span>', $html);
        self::assertStringContainsString('All notes', $html);
        self::assertStringContainsString('New note', $html);
        self::assertStringContainsString('Give it a title; the body is optional.', $html);
        self::assertStringContainsString('<span>Create note</span>', $html);
        self::assertStringContainsString('Delete note', $html);
        self::assertStringContainsString('Delete "Groceries"? This cannot be undone.', $html);
        self::assertStringContainsString('aria-label="Edit Groceries"', $html);
        self::assertStringContainsString('aria-label="Delete Groceries"', $html);
        self::assertMatchesRegularExpression('/for="title">\s*Title/', $html, 'La etiqueta del campo no salió del catálogo.');
        self::assertStringContainsString('Body (optional)', $html);

        // El listado también cambia de idioma: botón y encabezados.
        self::assertStringContainsString('<span>Search</span>', $html);
        self::assertStringContainsString('placeholder="Search title and body"', $html);
        self::assertStringContainsString('aria-label="Sort by"', $html);

        self::assertStringContainsString('>Notes<', $html, 'El sidebar en inglés no muestra el nombre del módulo.');
        self::assertStringContainsString('aria-current="page">Notes', $html, 'La miga en inglés no muestra el nombre del módulo.');

        self::assertStringNotContainsString('Agregar nota', $html, 'La pantalla sigue en español con `?lang=en`.');
        self::assertStringNotContainsString('Todas las notas', $html, 'La pantalla sigue en español con `?lang=en`.');
        self::assertStringNotContainsString('notes.subtitle', $html, 'La clave salió cruda: no se está traduciendo.');

        $edit = self::texto((string) self::app()->handle(
            self::request('GET', '/notes/' . $note->id . '/edit?lang=en')
        )->getBody());

        self::assertStringContainsString('Edit note', $edit);
        self::assertStringContainsString('<span>Save changes</span>', $edit);
        self::assertStringContainsString('<span>Cancel</span>', $edit);
    }

    /** El aviso del alta sale del catálogo en el idioma de la petición. */
    public function test_el_aviso_del_alta_sale_del_catalogo(): void
    {
        foreach (['' => 'Nota creada.', '?lang=en' => 'Note created.'] as $query => $aviso) {
            $token = self::token('/notes' . $query);

            self::app()->handle(self::request('POST', '/notes' . $query, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Groceries',
                'body'       => '',
            ]));

            $html = self::texto((string) self::app()->handle(self::request('GET', '/notes' . $query))->getBody());

            $idioma = $query === '' ? 'es' : 'en';

            self::assertStringContainsString($aviso, $html, "El aviso no llegó al host de toasts en {$idioma}.");

            Note::query()->delete();
        }
    }
}
