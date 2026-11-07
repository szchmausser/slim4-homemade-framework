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
}
