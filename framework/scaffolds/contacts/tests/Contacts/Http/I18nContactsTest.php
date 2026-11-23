<?php

declare(strict_types=1);

namespace Tests\Contacts\Http;

use App\Models\Contact;

/**
 * El demo Contactos en los dos idiomas, y el canal por el que el JS recibe sus
 * textos.
 *
 * Lo segundo es la parte nueva de esta unidad: el archivo
 * `public/assets/js/contacts-tabulator.js` es un asset estático, así que no
 * puede llamar a `t()`. El servidor le manda las cadenas del locale activo en
 * un bloque JSON al lado de la tabla (`#contacts-table-i18n`), y el JS sólo las
 * consume. Si el bloque no viaja, o viaja en el idioma equivocado, la tabla
 * queda con textos del otro idioma aunque el resto de la pantalla cambie.
 *
 * Para tu entidad: si tu JS arma texto, copiá la vía —`data-*` o JSON— en vez
 * de incrustar cadenas: un asset no sabe en qué idioma lo pidieron.
 */
final class I18nContactsTest extends ContactsTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
    }

    /** Texto del render con las entidades HTML decodificadas. */
    private static function texto(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Textos que la tabla recibe del servidor.
     *
     * @return array<string, mixed>
     */
    private static function tableStrings(string $html): array
    {
        if (preg_match('/id="contacts-table-i18n" data-strings="([^"]*)"/', $html, $match) !== 1) {
            self::fail('La página no emite el bloque de textos de la tabla (#contacts-table-i18n).');
        }

        // El HTML ya decodificó las entidades del atributo: el JSON llega tal
        // cual lo va a ver `JSON.parse()` en el navegador.
        return json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** S7: la pantalla en español dice lo mismo que decía antes del cambio. */
    public function test_la_pantalla_conserva_el_espanol_de_head(): void
    {
        $html = self::texto((string) self::app()->handle(self::request('GET', '/contacts'))->getBody());

        self::assertStringContainsString('<title>Contactos</title>', $html);
        self::assertStringContainsString('CRUD de contactos con listado en Tabulator: numeración, columnas y acciones sobre SQLite.', $html);
        self::assertStringContainsString('<span>Agregar contacto</span>', $html);
        self::assertStringContainsString('Listado de contactos', $html);
        self::assertStringContainsString('Activa JavaScript para ver la tabla interactiva.', $html);
        self::assertStringContainsString('Nuevo contacto', $html);
        self::assertStringContainsString('Completa los datos para agregarlo al listado.', $html);
        self::assertStringContainsString('<span>Crear contacto</span>', $html);
        self::assertStringContainsString('¿Eliminar a', $html);
        self::assertStringContainsString('Eliminar contacto', $html);
        self::assertMatchesRegularExpression('/for="name">\s*Nombre/', $html);
        self::assertMatchesRegularExpression('/for="phone">\s*Teléfono/', $html);
    }

    /** El formulario suelto de htmx también sale del catálogo. */
    public function test_el_formulario_suelto_conserva_el_espanol_de_head(): void
    {
        $contact = Contact::create(['name' => 'Ana', 'email' => 'ana@ejemplo.com', 'phone' => '']);

        $html = self::texto((string) self::app()->handle(
            self::request('GET', '/contacts/' . $contact->id . '/edit', [], ['HX-Request' => 'true'])
        )->getBody());

        self::assertMatchesRegularExpression('/for="name">\s*Nombre/', $html);
        self::assertMatchesRegularExpression('/for="email">\s*Email/', $html);
        self::assertStringContainsString('<span>Guardar cambios</span>', $html);
        self::assertStringContainsString('<span>Cancelar</span>', $html);
    }

    /** Los textos de la tabla viajan con el idioma de la petición. */
    public function test_la_tabla_recibe_sus_textos_en_el_idioma_activo(): void
    {
        $es = self::tableStrings((string) self::app()->handle(self::request('GET', '/contacts'))->getBody());

        self::assertSame('Nombre', $es['columns']['name'] ?? null);
        self::assertSame('Acciones', $es['columns']['actions'] ?? null);
        self::assertSame('Editar', $es['actions']['edit'] ?? null);
        self::assertSame('Eliminar', $es['actions']['delete'] ?? null);
        self::assertSame('No hay contactos todavía.', $es['empty'] ?? null);
        self::assertSame('Cargando...', $es['loading'] ?? null);
        self::assertSame('Mostrar u ocultar los datos de la fila', $es['collapse'] ?? null);
        self::assertSame('Filas', $es['pagination']['page_size'] ?? null);
        self::assertSame('Primera', $es['pagination']['first'] ?? null);
        self::assertSame('Mostrando', $es['pagination']['counter']['showing'] ?? null);
        self::assertSame('filas', $es['pagination']['counter']['rows'] ?? null);

        $en = self::tableStrings((string) self::app()->handle(self::request('GET', '/contacts?lang=en'))->getBody());

        self::assertSame('Name', $en['columns']['name'] ?? null);
        self::assertSame('Actions', $en['columns']['actions'] ?? null);
        self::assertSame('Edit', $en['actions']['edit'] ?? null);
        self::assertSame('Delete', $en['actions']['delete'] ?? null);
        self::assertSame('No contacts yet.', $en['empty'] ?? null);
        self::assertSame('Loading...', $en['loading'] ?? null);
        self::assertSame('Rows', $en['pagination']['page_size'] ?? null);
        self::assertSame('First', $en['pagination']['first'] ?? null);
        self::assertSame('rows', $en['pagination']['counter']['rows'] ?? null);
    }

    /**
     * Con `?lang=en` la pantalla, el sidebar y los avisos salen en inglés.
     *
     * Una aserción visible por superficie: encabezado, botón de alta, modal de
     * borrado, formulario, sidebar y el flash del alta.
     */
    public function test_con_lang_en_la_pantalla_y_los_avisos_salen_en_ingles(): void
    {
        $response = self::app()->handle(self::request('GET', '/contacts?lang=en'));
        $html = self::texto((string) $response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html lang="en"', $html);
        self::assertStringContainsString('<title>Contacts</title>', $html);
        self::assertStringContainsString('Contact CRUD with a Tabulator listing', $html);
        self::assertStringContainsString('<span>Add contact</span>', $html);
        self::assertStringContainsString('Contact list', $html);
        self::assertStringContainsString('Enable JavaScript to see the interactive table.', $html);
        self::assertStringContainsString('New contact', $html);
        self::assertStringContainsString('Delete contact', $html);
        self::assertStringContainsString('Delete', $html);
        self::assertStringContainsString('>Contacts<', $html, 'El sidebar no muestra la etiqueta traducida.');
        self::assertStringNotContainsString('Agregar contacto', $html);
        self::assertStringNotContainsString('>Contactos<', $html);

        $token = self::token('/contacts?lang=en');

        self::app()->handle(self::request('POST', '/contacts?lang=en', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name'       => 'Ana Torres',
            'email'      => 'ana@ejemplo.com',
            'phone'      => '',
        ]));

        $flash = self::texto((string) self::app()->handle(self::request('GET', '/contacts?lang=en'))->getBody());

        self::assertStringContainsString('Contact created.', $flash);
        self::assertStringNotContainsString('Contacto creado.', $flash);
    }

    /**
     * El SRI del JS del demo coincide con el archivo que se sirve.
     *
     * El demo carga sus assets con `integrity=`: si el hash no coincide con el
     * archivo instalado, el navegador se niega a ejecutarlo y la tabla no
     * aparece. Tocar el JS sin recalcular el hash es el error fácil de cometer.
     */
    public function test_el_sri_del_js_coincide_con_el_archivo_instalado(): void
    {
        $ruta = dirname(__DIR__, 3) . '/public/assets/js/contacts-tabulator.js';

        $js = file_get_contents($ruta);
        self::assertNotFalse($js, 'No se pudo leer ' . $ruta);

        $esperado = 'sha384-' . base64_encode(hash('sha384', $js, true));

        $html = (string) self::app()->handle(self::request('GET', '/contacts'))->getBody();

        self::assertStringContainsString($esperado, $html, 'El integrity del JS no coincide con el archivo: el navegador no lo ejecuta.');
    }
}
