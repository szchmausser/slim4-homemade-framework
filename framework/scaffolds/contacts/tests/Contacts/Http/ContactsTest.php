<?php

declare(strict_types=1);

namespace Tests\Contacts\Http;

use App\Contacts\Models\Contact;

final class ContactsTest extends ContactsTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
    }

    public function test_index_renderiza_tabulator_y_los_contactos(): void
    {
        Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com', 'phone' => '123']);

        $response = self::app()->handle(self::request('GET', '/contactos'));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-tabulator-table', $html);
        self::assertStringContainsString('/assets/vendor/tabulator/6.7.1/tabulator.min.js', $html);
        self::assertStringContainsString('contact-create-modal', $html);
        self::assertStringContainsString('contact-delete-modal', $html);
        self::assertStringContainsString('Agregar contacto', $html);
        self::assertStringContainsString('Ana Torres', $html);
    }

    public function test_los_avisos_de_contactos_llegan_al_host_de_toasts(): void
    {
        $token = self::token('/contactos');

        self::app()->handle(self::request('POST', '/contactos', [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name' => 'Toast Uno',
            'email' => 'toast@ejemplo.com',
            'phone' => '',
        ]));

        $html = (string) self::app()->handle(self::request('GET', '/contactos'))->getBody();
        self::assertStringContainsString('toast-host', $html);
        self::assertStringContainsString('x-init="addAll(', $html);
        self::assertStringContainsString('Contacto creado.', $html);

        $contact = Contact::query()->where('email', 'toast@ejemplo.com')->firstOrFail();
        $token = self::token('/contactos');

        self::app()->handle(self::request('POST', '/contactos/' . $contact->id . '/delete', [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
        ]));

        $html = (string) self::app()->handle(self::request('GET', '/contactos'))->getBody();
        self::assertStringContainsString('Contacto eliminado.', $html);
    }

    public function test_store_crea_un_contacto(): void
    {
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request('POST', '/contactos', [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name' => 'Bruno Díaz',
            'email' => 'bruno@ejemplo.com',
            'phone' => '555-0102',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/contactos', $response->getHeaderLine('Location'));
        self::assertSame(1, Contact::query()->count());
    }

    public function test_update_modifica_un_contacto(): void
    {
        $contact = Contact::create([
            'name' => 'Carla Méndez',
            'email' => 'carla@ejemplo.com',
            'phone' => '555-0103',
        ]);
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request('POST', '/contactos/' . $contact->id, [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name' => 'Carla Actualizada',
            'email' => 'carla.nueva@ejemplo.com',
            'phone' => '',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('Carla Actualizada', $contact->fresh()?->name);
    }

    public function test_destroy_elimina_un_contacto(): void
    {
        $contact = Contact::create([
            'name' => 'Temporal',
            'email' => 'temporal@ejemplo.com',
        ]);
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request(
            'POST',
            '/contactos/' . $contact->id . '/delete',
            [
                'csrf_name' => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ]
        ));

        self::assertSame(303, $response->getStatusCode());
        self::assertNull(Contact::query()->find($contact->id));
    }

    /**
     * S7: un alta inválida NO redirige. El redirect descartaba lo tipeado y
     * dejaba el modal cerrado, así que el usuario tenía que volver a abrirlo y
     * a escribir todo (y el mensaje vivía en un toast que no decía qué campo
     * corregir). El formulario vuelve a renderizarse con los valores, los
     * mensajes por campo y el modal abierto, que es donde estaba escribiendo.
     */
    public function test_store_invalido_rerenderiza_el_formulario_con_lo_tipeado_y_el_error(): void
    {
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request('POST', '/contactos', [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name' => 'Sin correo',
            'email' => 'no-es-un-email',
            'phone' => '555-0102',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, Contact::query()->count(), 'No se crea nada.');

        $html = (string) $response->getBody();

        // El modal tiene que viajar abierto: el <dialog> sale con `open` (la
        // regla de daisyUI es `.modal[open]`), sin depender de JS.
        self::assertMatchesRegularExpression(
            '/<dialog\b[^>]*id="contact-create-modal"[^>]*\bopen\b/',
            $html,
            'El modal de alta tiene que venir abierto para no perder lo tipeado.'
        );
        self::assertStringContainsString('value="Sin correo"', $html, 'El nombre tipeado se perdió.');
        self::assertStringContainsString('value="555-0102"', $html, 'El teléfono tipeado se perdió.');
        self::assertStringContainsString('Este campo debe ser un email válido.', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringNotContainsString('alert-error', $html, 'El error de un campo no se repite como flash.');
    }

    /**
     * S7: la edición tenía el mismo defecto por una vía peor: 303 a
     * /contactos descartaba la pantalla de edición entera, así que el usuario
     * perdía el formulario, lo tipeado y hasta el contacto que estaba
     * editando. Ahora la página de edición vuelve con los valores y el error.
     */
    public function test_update_invalido_rerenderiza_el_formulario_en_vez_de_redirigir(): void
    {
        $contact = Contact::create([
            'name' => 'Carla Méndez',
            'email' => 'carla@ejemplo.com',
            'phone' => '555-0103',
        ]);
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request('POST', '/contactos/' . $contact->id, [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name' => '',
            'email' => 'carla.nueva@ejemplo.com',
            'phone' => '555-0199',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'), 'Un fallo de validación no puede redirigir.');
        self::assertSame('Carla Méndez', $contact->fresh()?->name, 'La validación falló: el contacto no cambia.');
        self::assertSame('carla@ejemplo.com', $contact->fresh()?->email);

        $html = (string) $response->getBody();

        self::assertStringContainsString('action="/contactos/' . $contact->id . '"', $html, 'Tiene que ser la pantalla de edición.');
        self::assertStringContainsString('Este campo es obligatorio.', $html);
        self::assertStringContainsString('value="carla.nueva@ejemplo.com"', $html, 'Se repintó el email guardado en vez de lo tipeado.');
        self::assertStringContainsString('value="555-0199"', $html, 'El teléfono tipeado se perdió.');
        self::assertStringContainsString('aria-invalid="true"', $html);
    }

    /**
     * S5: la edición de un contacto inexistente es un 404, no un redirect al
     * listado. El redirect escondía el error: el usuario veía el listado como
     * si el contacto se hubiera abierto, y el aviso que lo explicaba duraba
     * cinco segundos.
     */
    public function test_edit_de_un_contacto_inexistente_responde_404(): void
    {
        $response = self::app()->handle(self::request('GET', '/contactos/999999/edit'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('No encontramos esa página.', (string) $response->getBody());
    }

    public function test_update_de_un_contacto_inexistente_responde_404(): void
    {
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request('POST', '/contactos/999999', [
            'csrf_name' => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'name' => 'Fantasma',
            'email' => 'fantasma@ejemplo.com',
        ]));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(0, Contact::query()->count(), 'No se crea nada.');
    }

    /**
     * El borrado de un contacto inexistente SOBREVIVE a S5 como PRG: una
     * acción con redirect no es una vista de recurso, y el listado (que sí
     * existe) es el destino correcto. Lo que no puede pasar es que el aviso se
     * pierda sin que nadie lo vea.
     */
    public function test_borrar_un_contacto_inexistente_redirige_con_aviso(): void
    {
        $token = self::token('/contactos');

        $response = self::app()->handle(self::request(
            'POST',
            '/contactos/999999/delete',
            [
                'csrf_name' => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ]
        ));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/contactos', $response->getHeaderLine('Location'));

        $html = (string) self::app()->handle(self::request('GET', '/contactos'))->getBody();

        self::assertStringContainsString('El contacto no existe.', $html, 'El aviso del borrado perdido no llegó al listado.');
    }
}
