<?php

declare(strict_types=1);

namespace Tests\Contacts\Http;

use App\Contacts\Models\Contact;
use Psr\Http\Message\ResponseInterface as Response;

final class ContactsTest extends ContactsTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
    }

    /**
     * S10: la página ya NO viaja con las filas. El HTML trae el shell de la
     * tabla y la URL del endpoint; el listado se pide por AJAX. Antes, cada
     * render metía todos los contactos en un `data-rows` que crecía con la
     * tabla, así que este assert de “el nombre sembrado aparece en el HTML”
     * se movió al endpoint: el HTML es justamente lo que ya no lo lleva.
     */
    public function test_index_no_embebe_las_filas_y_apunta_al_endpoint(): void
    {
        Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com', 'phone' => '123']);

        $response = self::app()->handle(self::request('GET', '/contactos'));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('data-tabulator-table', $html);
        self::assertStringContainsString('data-url="/contactos/datos"', $html);
        self::assertStringNotContainsString('data-rows', $html, 'Las filas no viajan en el HTML.');
        self::assertStringNotContainsString('Ana Torres', $html, 'Las filas no viajan en el HTML.');

        // El contacto no desapareció: cambió de canal. La misma fila que antes
        // estaba embebida llega ahora por el endpoint que la tabla consulta.
        $rows = self::rows(self::json(self::app()->handle(self::request('GET', self::dataUrl()))));
        self::assertSame('Ana Torres', $rows[0]['name']);
        self::assertStringContainsString('1 contactos', $html, 'El badge cuenta en la base, no en un array.');
        self::assertStringContainsString('/assets/vendor/tabulator/6.7.1/tabulator.min.js', $html);
        self::assertStringContainsString('contact-create-modal', $html);
        self::assertStringContainsString('contact-delete-modal', $html);
        self::assertStringContainsString('Agregar contacto', $html);
    }

    /**
     * S10: el endpoint vive dentro del grupo `/contactos`, así que hereda
     * RequireAuthMiddleware. Sin sesión no hay datos, ni siquiera para un GET
     * que parece sólo lectura.
     */
    public function test_el_endpoint_de_datos_requiere_sesion(): void
    {
        Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com']);
        unset($_SESSION['user_id']);

        $response = self::app()->handle(self::request('GET', self::dataUrl()));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertSame('', (string) $response->getBody());
    }

    /**
     * S10: la respuesta tiene la forma que espera el modo remoto de Tabulator
     * —`{last_page, data}`— y cada fila conserva la forma de siempre más el
     * `number` que antes calculaba el cliente. La numeración la resuelve el
     * servidor: es la posición en la página, no el id.
     */
    public function test_datos_devuelve_last_page_y_data_con_la_forma_de_siempre(): void
    {
        self::seedContacts(25);

        $response = self::app()->handle(self::request('GET', self::dataUrl()));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = self::json($response);
        self::assertSame(3, $payload['last_page']);

        $rows = self::rows($payload);
        self::assertCount(10, $rows);
        // Sin sorter, el default sigue siendo id descendente: lo último
        // cargado primero, con `#` numerando desde 1 en la página.
        self::assertSame(self::namesDescending(25, 10), array_column($rows, 'name'));
        self::assertSame(range(1, 10), array_column($rows, 'number'));

        // Los ids no arrancan en 1 —la secuencia sobrevive al borrado entre
        // tests—, así que las URLs se comparan contra el contacto más nuevo.
        $newest = Contact::query()->orderByDesc('id')->firstOrFail();
        self::assertSame('/contactos/' . $newest->id . '/edit', $rows[0]['edit_url']);
        self::assertSame('/contactos/' . $newest->id . '/delete', $rows[0]['delete_url']);

        $keys = array_keys($rows[0]);
        sort($keys);
        self::assertSame(['delete_url', 'edit_url', 'email', 'id', 'name', 'number', 'phone'], $keys);

        $secondPage = self::rows(self::json(self::app()->handle(self::request('GET', self::dataUrl(['page' => 2])))));
        self::assertSame(range(11, 20), array_column($secondPage, 'number'), 'La numeración continúa entre páginas.');
    }

    /**
     * S10: `per_page` es el mismo vocabulario del resto de la app y lo clampea
     * Pagination::clampPerPage: un tamaño que no está en ALLOWED_PER_PAGE cae
     * al default en vez de convertirse en un LIMIT arbitrario.
     */
    public function test_datos_respeta_per_page_y_clampea_el_tamano_desconocido(): void
    {
        self::seedContacts(12);

        $five = self::json(self::app()->handle(self::request('GET', self::dataUrl(['per_page' => 5]))));
        self::assertCount(5, self::rows($five));
        self::assertSame(3, $five['last_page']);

        $unknown = self::json(self::app()->handle(self::request('GET', self::dataUrl(['per_page' => 7]))));
        self::assertCount(10, self::rows($unknown), 'Un tamaño fuera de la lista cae al default.');
        self::assertSame(2, $unknown['last_page']);
    }

    /**
     * S10: pedir una página que no existe devuelve la última, no un array
     * vacío. El clamp es de Pagination::paginate, no de este controlador.
     */
    public function test_datos_clampea_una_pagina_fuera_de_rango(): void
    {
        self::seedContacts(12);

        $payload = self::json(self::app()->handle(self::request('GET', self::dataUrl(['page' => 99]))));

        self::assertSame(2, $payload['last_page']);

        $rows = self::rows($payload);
        self::assertCount(2, $rows);
        // La última página del orden default (id descendente) son los dos más
        // viejos, numerados 11 y 12: el clamp devuelve datos, no un vacío.
        self::assertSame(['Contacto 02', 'Contacto 01'], array_column($rows, 'name'));
        self::assertSame([11, 12], array_column($rows, 'number'));
    }

    /**
     * S10: el orden llega como `sort[i][field]`/`sort[i][dir]` y se resuelve
     * contra una whitelist. Con un sorter activo, `#` es la posición en ESE
     * orden: la primera fila es la 1 aunque su id sea otro.
     */
    public function test_datos_ordena_por_una_columna_de_la_whitelist(): void
    {
        $carla = Contact::create(['name' => 'Carla', 'email' => 'carla@ejemplo.com']);
        $ana = Contact::create(['name' => 'Ana', 'email' => 'ana@ejemplo.com']);
        $bruno = Contact::create(['name' => 'Bruno', 'email' => 'bruno@ejemplo.com']);

        $asc = self::rows(self::json(self::app()->handle(
            self::request('GET', self::dataUrl(sorters: [['field' => 'name', 'dir' => 'asc']]))
        )));

        self::assertSame(['Ana', 'Bruno', 'Carla'], array_column($asc, 'name'));
        self::assertSame([$ana->id, $bruno->id, $carla->id], array_column($asc, 'id'));
        self::assertSame([1, 2, 3], array_column($asc, 'number'));

        $desc = self::rows(self::json(self::app()->handle(
            self::request('GET', self::dataUrl(sorters: [['field' => 'name', 'dir' => 'desc']]))
        )));

        self::assertSame(['Carla', 'Bruno', 'Ana'], array_column($desc, 'name'));
    }

    /**
     * S10: se aplican TODOS los sorters de la whitelist, en orden de llegada.
     * Quedarse con el primero rompería el orden compuesto que el usuario armó
     * con shift+click en los encabezados.
     */
    public function test_datos_aplica_todos_los_sorters_en_orden_de_llegada(): void
    {
        Contact::create(['name' => 'Zoe', 'email' => 'zoe@ejemplo.com', 'phone' => '555-0200']);
        Contact::create(['name' => 'Ana', 'email' => 'ana@ejemplo.com', 'phone' => '555-0200']);
        Contact::create(['name' => 'Beto', 'email' => 'beto@ejemplo.com', 'phone' => '555-0100']);

        $rows = self::rows(self::json(self::app()->handle(self::request('GET', self::dataUrl(sorters: [
            ['field' => 'phone', 'dir' => 'asc'],
            ['field' => 'name', 'dir' => 'asc'],
        ])))));

        self::assertSame(['Beto', 'Ana', 'Zoe'], array_column($rows, 'name'));
    }

    /**
     * S10: un campo fuera de la whitelist se descarta en silencio. El caso
     * `password_hash` es el que importa: no existe en `contacts`, así que si
     * el nombre llegara a la query SQLite tiraría “no such column” y esto
     * sería un 500. Un 200 con el orden default prueba que nunca llegó.
     */
    public function test_datos_ignora_un_campo_fuera_de_la_whitelist(): void
    {
        $first = Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com']);
        $second = Contact::create(['name' => 'Bruno Díaz', 'email' => 'bruno@ejemplo.com']);
        $third = Contact::create(['name' => 'Carla Méndez', 'email' => 'carla@ejemplo.com']);

        $response = self::app()->handle(self::request(
            'GET',
            self::dataUrl(sorters: [['field' => 'password_hash', 'dir' => 'asc']])
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $rows = self::rows(self::json($response));
        self::assertSame(
            [$third->id, $second->id, $first->id],
            array_column($rows, 'id'),
            'Sin sorter válido, default id descendente.'
        );
    }

    /**
     * S10: página y orden se combinan en el servidor. Con el sorter activo la
     * página 2 es la segunda porción alfabética, y la numeración arranca en 6.
     */
    public function test_datos_pagina_y_ordena_juntos(): void
    {
        self::seedContacts(12);

        $rows = self::rows(self::json(self::app()->handle(self::request('GET', self::dataUrl(
            ['page' => 2, 'per_page' => 5],
            [['field' => 'name', 'dir' => 'asc']]
        )))));

        self::assertSame(
            ['Contacto 06', 'Contacto 07', 'Contacto 08', 'Contacto 09', 'Contacto 10'],
            array_column($rows, 'name')
        );
        self::assertSame([6, 7, 8, 9, 10], array_column($rows, 'number'));
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

    /**
     * Query string como la manda Tabulator 6.7.1: su generador de URLs
     * serializa objetos anidados con corchetes y encodeURIComponent, así que
     * `sort` viaja como `sort%5B0%5D%5Bfield%5D=…`. Los nombres de `page` y
     * `per_page` son los del resto de la app.
     *
     * @param array<string, int|string> $params
     * @param list<array{field: string, dir: string}> $sorters
     */
    private static function dataUrl(array $params = [], array $sorters = []): string
    {
        foreach ($sorters as $index => $sorter) {
            $params['sort[' . $index . '][field]'] = $sorter['field'];
            $params['sort[' . $index . '][dir]'] = $sorter['dir'];
        }

        return '/contactos/datos' . ($params === [] ? '' : '?' . http_build_query($params));
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(Response $response): array
    {
        $payload = json_decode((string) $response->getBody(), true);

        self::assertIsArray($payload, 'La respuesta del endpoint no es JSON.');

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return list<array<string, mixed>>
     */
    private static function rows(array $payload): array
    {
        $rows = $payload['data'] ?? [];
        self::assertIsArray($rows, 'La respuesta no trae `data`.');

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    /**
     * Contactos con nombre, email y teléfono únicos para que el orden sea
     * determinista sin depender de un desempate por id.
     */
    private static function seedContacts(int $count): void
    {
        foreach (range(1, $count) as $index) {
            Contact::create([
                'name' => sprintf('Contacto %02d', $index),
                'email' => sprintf('contacto%02d@ejemplo.com', $index),
                'phone' => sprintf('+54 11 5555-%04d', 1000 + $index),
            ]);
        }
    }

    /**
     * Nombres que siembra seedContacts(), del más nuevo al más viejo: es el
     * orden default del endpoint (id descendente) sin fijar ids absolutos, que
     * la secuencia de SQLite conserva aunque el test borre sus filas.
     *
     * @return list<string>
     */
    private static function namesDescending(int $from, int $count): array
    {
        $names = [];

        foreach (range($from, $from - $count + 1) as $index) {
            $names[] = sprintf('Contacto %02d', $index);
        }

        return $names;
    }
}
