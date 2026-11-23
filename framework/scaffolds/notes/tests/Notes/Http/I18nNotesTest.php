<?php

declare(strict_types=1);

namespace Tests\Notes\Http;

use App\Models\Note;
use Tests\Support\HttpTestCase;

/**
 * El demo Notes sale del catálogo sin cambiar de idioma.
 *
 * Este demo está en inglés por diseño (L16 de `odd/tasks/demo-conventions.md`:
 * «columnas title/body, datos de ejemplo, etiquetas, estados vacíos, diálogos,
 * flashes y aria-labels en inglés»), así que sus dos catálogos llevan el mismo
 * texto: lo que se externalizó es de dónde sale la cadena, no el idioma del
 * demo. Cambiar eso es una decisión de producto —traducir `notes.*` en
 * `lang/es.php`— y no un efecto colateral de esta unidad.
 *
 * El test fija las dos cosas: que el texto es el mismo en los dos locales y que
 * ya no vive en la plantilla ni en el controlador (si vuelve un literal, la
 * clave desaparece del catálogo y `I18nDemosTest` lo frena; si el render deja
 * de pasar por `t()`, este archivo no puede distinguirlo, y por eso la
 * referencia de es/en es el contrato de S7).
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

    /** S7: la pantalla dice lo mismo que decía antes del cambio. */
    public function test_la_pantalla_conserva_el_texto_de_head(): void
    {
        $note = Note::create(['title' => 'Groceries', 'body' => 'Milk']);

        $html = self::texto((string) self::app()->handle(self::request('GET', '/notes'))->getBody());

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

        // El modal de edición vive en la página completa: el swap de htmx
        // devuelve `_panel.twig`, que no lo incluye.
        $edit = self::texto((string) self::app()->handle(
            self::request('GET', '/notes/' . $note->id . '/edit')
        )->getBody());

        self::assertStringContainsString('Edit note', $edit);
        self::assertStringContainsString('<span>Save changes</span>', $edit);
        self::assertStringContainsString('<span>Cancel</span>', $edit);
        self::assertStringContainsString('aria-label="Edit Groceries"', $edit);
    }

    /**
     * El demo no cambia de idioma: es inglés en los dos locales.
     *
     * La aserción del sidebar sí distingue: `nav.notes` es el nombre del módulo
     * y también dice «Notes» en los dos catálogos.
     */
    public function test_el_demo_sale_en_ingles_en_los_dos_locales(): void
    {
        foreach (['', '?lang=en'] as $query) {
            $html = self::texto((string) self::app()->handle(self::request('GET', '/notes' . $query))->getBody());

            $idioma = $query === '' ? 'es' : 'en';

            self::assertStringContainsString('<title>Notes</title>', $html, "La pantalla de notes cambió de idioma en {$idioma}.");
            self::assertStringContainsString('Add note', $html);
            self::assertStringContainsString('No notes yet.', $html);
            self::assertStringContainsString('>Notes<', $html, 'El sidebar no muestra la etiqueta del módulo.');
            self::assertStringNotContainsString('Notas', $html);
            self::assertStringNotContainsString('notes.title', $html, 'La clave salió cruda: no se está traduciendo.');
        }
    }

    /** El aviso del alta sale del catálogo, con el mismo texto en los dos locales. */
    public function test_el_aviso_del_alta_sale_del_catalogo(): void
    {
        foreach (['', '?lang=en'] as $query) {
            $token = self::token('/notes' . $query);

            self::app()->handle(self::request('POST', '/notes' . $query, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Groceries',
                'body'       => '',
            ]));

            $html = self::texto((string) self::app()->handle(self::request('GET', '/notes' . $query))->getBody());

            $idioma = $query === '' ? 'es' : 'en';

            self::assertStringContainsString('Note created.', $html, "El aviso no llegó al host de toasts en {$idioma}.");

            Note::query()->delete();
        }
    }
}
