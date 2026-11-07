<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Pagination;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La matemática de la paginación, sin HTTP: clamp, ventana y slices.
 * Si esto está verde, lo que se ve raro en pantalla es maquetación, no datos.
 */
final class PaginationTest extends TestCase
{
    protected function tearDown(): void
    {
        User::query()->delete();
    }

    public function test_clamp_acepta_solo_tamanos_permitidos(): void
    {
        self::assertSame(5, Pagination::clampPerPage(5));
        self::assertSame(10, Pagination::clampPerPage(10));
        self::assertSame(25, Pagination::clampPerPage(25));
        self::assertSame(50, Pagination::clampPerPage(50));
        self::assertSame(100, Pagination::clampPerPage(100));
        self::assertSame(10, Pagination::clampPerPage(15));
        self::assertSame(10, Pagination::clampPerPage(7));
        self::assertSame(10, Pagination::clampPerPage(0));
        self::assertSame(10, Pagination::clampPerPage(-5));
        self::assertSame(10, Pagination::clampPerPage('abc'));
        self::assertSame(10, Pagination::clampPerPage(null));
    }

    /** @return array<string, array{0: int, 1: int, 2: array}> */
    public static function windows(): array
    {
        return [
            'una sola'             => [1, 1, [1]],
            'pocas'                => [1, 5, [1, 2, 3, 4, 5]],
            'inicio'               => [1, 10, [1, 2, 3, 4, 5]],
            'segunda'              => [2, 10, [1, 2, 3, 4, 5]],
            'medio'                => [5, 10, [3, 4, 5, 6, 7]],
            'sexta'                => [6, 10, [4, 5, 6, 7, 8]],
            'final'                => [9, 10, [6, 7, 8, 9, 10]],
            'última exacta'        => [10, 10, [6, 7, 8, 9, 10]],
            'seis paginas, medio'  => [3, 6, [1, 2, 3, 4, 5]],
            'cuatro de seis'       => [4, 6, [2, 3, 4, 5, 6]],
        ];
    }

    #[DataProvider('windows')]
    public function test_ventana(int $current, int $last, array $expected): void
    {
        self::assertSame($expected, Pagination::window($current, $last));
    }

    /**
     * La ventana es un bloque contiguo de hasta cinco páginas, centrado en la
     * actual y recortado en los extremos. No hay ellipsis: los saltos largos
     * quedan en Primera/Anterior/Siguiente/Última.
     */
    public function test_la_ventana_es_contigua_y_no_pasa_de_cinco_entradas(): void
    {
        foreach (range(1, 12) as $last) {
            foreach (range(1, $last) as $current) {
                $pages = Pagination::window($current, $last);
                $caso = sprintf('ventana de %d en %d', $current, $last);

                self::assertLessThanOrEqual(Pagination::WINDOW_SIZE, count($pages), $caso);
                self::assertSame(range($pages[0], $pages[array_key_last($pages)]), $pages, $caso . ': bloque contiguo');
                self::assertContains($current, $pages, $caso . ': incluye la actual');
                self::assertGreaterThanOrEqual(1, $pages[0], $caso . ': no sale por izquierda');
                self::assertLessThanOrEqual($last, $pages[array_key_last($pages)], $caso . ': no sale por derecha');
            }
        }
    }

    public function test_pagina_intermedia_trae_su_slice_y_contadores(): void
    {
        $this->seedUsers(25);

        $p = Pagination::paginate($this->query(), 2, 10, '/tareas');

        self::assertSame(25, $p['total']);
        self::assertSame(10, $p['per_page']);
        self::assertSame(2, $p['current_page']);
        self::assertSame(3, $p['last_page']);
        self::assertSame(11, $p['from']);
        self::assertSame(20, $p['to']);
        self::assertCount(10, $p['items']);
        self::assertSame('/tareas', $p['base_url']);
    }

    public function test_pagina_fuera_de_rango_se_clampea_a_la_ultima(): void
    {
        $this->seedUsers(25);

        $p = Pagination::paginate($this->query(), 99, 10, '/tareas');

        self::assertSame(3, $p['current_page']);
        self::assertCount(5, $p['items']);
        self::assertSame(21, $p['from']);
        self::assertSame(25, $p['to']);
    }

    public function test_pagina_cero_o_negativa_es_la_primera(): void
    {
        $this->seedUsers(5);

        self::assertSame(1, Pagination::paginate($this->query(), 0, 10, '/t')['current_page']);
        self::assertSame(1, Pagination::paginate($this->query(), -3, 10, '/t')['current_page']);

        // 5 tiene que ser un tamano valido, no un valor que el clamp devuelva al
        // default: si el template ofrece un tamano que el server rechaza, el
        // usuario elige 5 y la lista le muestra 10 sin explicar nada.
        self::assertSame(5, Pagination::paginate($this->query(), 1, 5, '/t')['per_page']);

        // La lista de tamanos viaja con la paginacion para que el template no
        // tenga su propia copia: dos copias se desincronizan en cuanto alguien
        // agrega o quita un tamano en un solo lado.
        self::assertSame(Pagination::ALLOWED_PER_PAGE, Pagination::paginate($this->query(), 1, 10, '/t')['allowed_per_page']);
    }

    public function test_sin_filas_no_rompe_los_contadores(): void
    {
        $p = Pagination::paginate($this->query(), 1, 10, '/tareas');

        self::assertSame(0, $p['total']);
        self::assertSame(1, $p['last_page']);
        self::assertSame(0, $p['from']);
        self::assertSame(0, $p['to']);
        self::assertCount(0, $p['items']);
    }

    public function test_per_page_invalido_cae_al_default(): void
    {
        $this->seedUsers(25);

        $p = Pagination::paginate($this->query(), 1, 999, '/tareas');

        self::assertSame(10, $p['per_page']);
        self::assertSame(3, $p['last_page']);
    }

    private function query(): Builder
    {
        return User::orderByDesc('id');
    }

    private function seedUsers(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            User::create([
                'email'         => sprintf('user%02d@example.test', $i),
                'password_hash' => 'not-a-real-hash',
            ]);
        }
    }
}
