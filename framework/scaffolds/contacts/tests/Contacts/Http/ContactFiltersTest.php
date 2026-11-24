<?php

declare(strict_types=1);

namespace Tests\Contacts\Http;

use App\Models\Contact;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Filtros de cabecera de la grilla: el canal remoto de Tabulator hacia
 * `GET /contacts/data`.
 *
 * La tabla viaja con `filterMode: 'remote'`, así que la librería manda los
 * filtros de cabecera como `filters[i][field]`, `filters[i][type]` y
 * `filters[i][value]` (el nombre del parámetro lo fija `dataSendParams` en
 * `contacts-tabulator.js`). Del otro lado, el controlador sólo acepta un campo
 * de `FILTERABLE` y un tipo de `FILTER_TYPES`: los mismos límites que `SORTABLE`
 * tiene para el orden.
 *
 * Lo que se fija acá es el comportamiento del endpoint, no la tabla: filtro
 * aplicado, total y páginas del conjunto FILTRADO, valor literal (los comodines
 * de `LIKE` se escapan) y los dos rechazos silenciosos (campo y tipo).
 */
final class ContactFiltersTest extends ContactsTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
    }

    /**
     * El filtro se aplica antes de paginar: `last_row` es el total filtrado y
     * `last_page` sale de ese total, no del listado completo. La numeración
     * (`#`) también corre sobre el conjunto filtrado.
     */
    public function test_el_filtro_se_aplica_antes_de_paginar_y_last_row_es_el_total_filtrado(): void
    {
        self::seedContacts(12);

        $first = self::json(self::app()->handle(self::request(
            'GET',
            self::dataUrl(['per_page' => 5], [['name', 'like', 'Contacto 0']])
        )));

        // "Contacto 01" .. "Contacto 09": nueve de los doce.
        self::assertSame(9, $first['last_row'], 'last_row tiene que ser el total filtrado.');
        self::assertSame(2, $first['last_page'], 'Las páginas salen del total filtrado.');
        self::assertSame(5, count(self::rows($first)));
        self::assertSame([1, 2, 3, 4, 5], array_column(self::rows($first), 'number'), '`#` numera el conjunto filtrado.');

        $second = self::json(self::app()->handle(self::request(
            'GET',
            self::dataUrl(['page' => 2, 'per_page' => 5], [['name', 'like', 'Contacto 0']])
        )));

        // Orden default id descendente, sin la página completa: nueve filas dan
        // 5 + 4 y la última página no trae relleno del listado completo.
        self::assertSame(
            ['Contacto 04', 'Contacto 03', 'Contacto 02', 'Contacto 01'],
            array_column(self::rows($second), 'name')
        );
        self::assertSame([6, 7, 8, 9], array_column(self::rows($second), 'number'));
        self::assertSame(9, $second['last_row']);
    }

    /**
     * Los tres campos de la whitelist filtran su propia columna: no es una
     * búsqueda global, así que un valor que sólo está en el email no trae filas
     * cuando el filtro apunta al nombre.
     */
    public function test_los_tres_campos_permitidos_filtran_su_columna(): void
    {
        Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com', 'phone' => '555-0100']);
        Contact::create(['name' => 'Bruno Díaz', 'email' => 'bruno@otro.com', 'phone' => '555-0200']);

        self::assertSame(['Ana Torres'], self::names(self::filter(['name', 'like', 'ana'])));
        self::assertSame(['Ana Torres'], self::names(self::filter(['email', 'like', '@ejemplo.com'])));
        self::assertSame(['Bruno Díaz'], self::names(self::filter(['phone', 'like', '0200'])));
        self::assertSame([], self::names(self::filter(['name', 'like', '555'])), 'El filtro no busca en todas las columnas.');
    }

    /** `=` es el otro tipo admitido: compara el valor entero, no lo busca adentro. */
    public function test_el_tipo_igual_compara_el_valor_entero(): void
    {
        Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com']);
        Contact::create(['name' => 'Ana Torres Gómez', 'email' => 'ana.gomez@ejemplo.com']);

        self::assertSame(['Ana Torres'], self::names(self::filter(['name', '=', 'Ana Torres'])));
        self::assertSame([], self::names(self::filter(['name', '=', 'Ana'])), '`=` no es parcial.');
        self::assertSame(
            ['Ana Torres Gómez', 'Ana Torres'],
            self::names(self::filter(['name', 'like', 'Ana'])),
            '`like` sí es parcial y matchea las dos.'
        );
    }

    /**
     * Los comodines de `LIKE` se buscan como literales: el valor se escapa y el
     * SQL declara `ESCAPE`, así que un `%` tipeado en el filtro no matchea todo
     * lo que empieza igual.
     */
    public function test_los_comodines_del_like_se_tratan_como_literales(): void
    {
        Contact::create(['name' => 'Ana 100% real', 'email' => 'cien@ejemplo.com']);
        Contact::create(['name' => 'Ana 100 veces', 'email' => 'veces@ejemplo.com']);
        Contact::create(['name' => 'Ana_b', 'email' => 'guion-bajo@ejemplo.com']);
        Contact::create(['name' => 'Anaxb', 'email' => 'sin-guion-bajo@ejemplo.com']);

        self::assertSame(
            ['Ana 100% real'],
            self::names(self::filter(['name', 'like', '100%'])),
            'Un `%` en el filtro busca ese carácter, no «cualquier cosa».'
        );
        self::assertSame(
            ['Ana_b'],
            self::names(self::filter(['name', 'like', 'a_b'])),
            'Un `_` en el filtro busca ese carácter, no cualquier carácter.'
        );
    }

    /**
     * Un `field` fuera de la whitelist se descarta en silencio. `password_hash`
     * es el caso que importa: no existe en `contacts`, así que si llegara a la
     * query SQLite devolvería un 500; un 200 con el listado completo prueba que
     * no llegó.
     */
    public function test_un_campo_fuera_de_la_whitelist_no_filtra_nada(): void
    {
        self::seedContacts(3);

        $response = self::app()->handle(self::request(
            'GET',
            self::dataUrl([], [['password_hash', 'like', 'x']])
        ));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = self::json($response);
        self::assertSame(3, $payload['last_row']);
        self::assertCount(3, self::rows($payload));
    }

    /** Un `type` que no es `like` ni `=` se ignora entero: no se aplica «a medias». */
    public function test_un_tipo_de_filtro_fuera_de_la_whitelist_se_ignora(): void
    {
        self::seedContacts(3);

        foreach (['function', '>=', ''] as $type) {
            $payload = self::json(self::app()->handle(self::request(
                'GET',
                self::dataUrl([], [['name', $type, 'Contacto 01']])
            )));

            self::assertSame(3, $payload['last_row'], "El tipo «{$type}» no puede filtrar.");
            self::assertCount(3, self::rows($payload));
        }
    }

    /**
     * Sin `filters` en el query string la respuesta es la de siempre, byte a
     * byte: mismo orden de claves, mismos valores y `last_row` sobre el listado
     * completo. Es el contrato que la tabla ya consumía y que este cambio no
     * toca.
     */
    public function test_sin_filtros_la_respuesta_conserva_el_contrato_actual(): void
    {
        $ana = Contact::create(['name' => 'Ana Torres', 'email' => 'ana@ejemplo.com', 'phone' => '555-0100']);
        $bruno = Contact::create(['name' => 'Bruno Díaz', 'email' => 'bruno@ejemplo.com']);

        $response = self::app()->handle(self::request('GET', self::dataUrl()));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        // Orden default id descendente, `#` desde 1 y las dos URLs de las
        // acciones resueltas con el base path.
        $esperado = json_encode([
            'last_page' => 1,
            'last_row'  => 2,
            'data'      => [
                [
                    'number'     => 1,
                    'id'         => $bruno->id,
                    'name'       => 'Bruno Díaz',
                    'email'      => 'bruno@ejemplo.com',
                    'phone'      => '',
                    'edit_url'   => '/contacts/' . $bruno->id . '/edit',
                    'delete_url' => '/contacts/' . $bruno->id . '/delete',
                ],
                [
                    'number'     => 2,
                    'id'         => $ana->id,
                    'name'       => 'Ana Torres',
                    'email'      => 'ana@ejemplo.com',
                    'phone'      => '555-0100',
                    'edit_url'   => '/contacts/' . $ana->id . '/edit',
                    'delete_url' => '/contacts/' . $ana->id . '/delete',
                ],
            ],
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        self::assertSame($esperado, (string) $response->getBody());
    }

    /**
     * Payload de un filtro único contra el endpoint.
     *
     * @param array{0: string, 1: string, 2: string} $filter
     * @return array<string, mixed>
     */
    private static function filter(array $filter): array
    {
        return self::json(self::app()->handle(self::request('GET', self::dataUrl([], [$filter]))));
    }

    /**
     * Query string como la arma la tabla.
     *
     * Los nombres son los que fija `dataSendParams` en
     * `contacts-tabulator.js` —`page`, `per_page`, `sort` y `filters`—, y los
     * filtros viajan anidados como `filters[i][field]`: la librería serializa el
     * array `[{field, type, value}]` con corchetes, igual que el `sort` de los
     * encabezados.
     *
     * @param array<string, int|string>                            $params
     * @param list<array{0: string, 1: string, 2: string}>         $filters
     */
    private static function dataUrl(array $params = [], array $filters = []): string
    {
        foreach ($filters as $index => [$field, $type, $value]) {
            $params['filters[' . $index . '][field]'] = $field;
            $params['filters[' . $index . '][type]'] = $type;
            $params['filters[' . $index . '][value]'] = $value;
        }

        return '/contacts/data' . ($params === [] ? '' : '?' . http_build_query($params));
    }

    /**
     * Decodifica el cuerpo de la respuesta y falla el test si no es JSON.
     *
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
     * Las filas de `data`, con el test fallando si la clave no vino.
     *
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
     * Nombres de las filas, en el orden en que llegaron.
     *
     * @param array<string, mixed> $payload
     * @return list<string>
     */
    private static function names(array $payload): array
    {
        return array_column(self::rows($payload), 'name');
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
}
