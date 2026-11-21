<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\User;
use Framework\Http\Pagination;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Contrato de `Pagination::fromRequest()`: la lectura de `page`/`per_page` que
 * cada controlador de listado repetía a mano.
 *
 * Lo que se prueba no es la matemática (eso vive en
 * tests/Support/PaginationTest.php) sino que la lectura del query string
 * entregue exactamente la misma salida que `paginate()` con los mismos
 * argumentos: sin defaults nuevos ni clamping extra.
 */
final class PaginationTest extends TestCase
{
    protected function tearDown(): void
    {
        User::query()->delete();
    }

    public function test_sin_query_params_usa_la_primera_pagina_y_el_default(): void
    {
        $this->seedUsers(25);

        $p = Pagination::fromRequest(
            $this->query(),
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/notas'),
            '/notas'
        );

        self::assertSame(1, $p['current_page']);
        self::assertSame(Pagination::DEFAULT_PER_PAGE, $p['per_page']);
        self::assertSame(25, $p['total']);
        self::assertSame(1, $p['from']);
        self::assertSame(10, $p['to']);
        self::assertCount(10, $p['items']);
    }

    public function test_toma_page_y_per_page_del_query_string(): void
    {
        $this->seedUsers(25);

        $p = Pagination::fromRequest(
            $this->query(),
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/notas?page=2&per_page=5'),
            '/notas'
        );

        self::assertSame(2, $p['current_page']);
        self::assertSame(5, $p['per_page']);
        self::assertSame(5, $p['last_page']);
        self::assertSame(6, $p['from']);
        self::assertSame(10, $p['to']);
        self::assertCount(5, $p['items']);
    }

    public function test_conserva_la_base_url_tal_cual_la_recibe(): void
    {
        $this->seedUsers(3);

        $p = Pagination::fromRequest(
            $this->query(),
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/app/contacts/data?page=1'),
            '/app/contacts/data'
        );

        self::assertSame('/app/contacts/data', $p['base_url']);
    }

    /**
     * La salida tiene que ser la misma que armar la llamada a mano: es el
     * reemplazo de esas cuatro líneas, no una paginación paralela.
     */
    public function test_la_salida_es_identica_a_paginate_con_los_mismos_argumentos(): void
    {
        $this->seedUsers(25);

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost/notas?page=3&per_page=20');

        $esperado = Pagination::paginate($this->query(), 3, 20, '/notas');
        $obtenido = Pagination::fromRequest($this->query(), $request, '/notas');

        // Las colecciones son instancias distintas con las mismas filas: se
        // comparan por contenido y el resto del array clave por clave.
        $esperado['items'] = $esperado['items']->all();
        $obtenido['items'] = $obtenido['items']->all();

        self::assertEquals($esperado, $obtenido);
    }

    private function query(): Builder
    {
        return User::orderByDesc('id');
    }

    private function seedUsers(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            User::create([
                'email'         => sprintf('pagination%02d@example.test', $i),
                'password_hash' => 'not-a-real-hash',
            ]);
        }
    }
}
