<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Task;
use App\Support\Pagination;
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
        Task::query()->delete();
    }

    public function test_clamp_acepta_solo_tamanos_permitidos(): void
    {
        self::assertSame(10, Pagination::clampPerPage(10));
        self::assertSame(15, Pagination::clampPerPage(15));
        self::assertSame(25, Pagination::clampPerPage(25));
        self::assertSame(50, Pagination::clampPerPage(50));
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
            'una sola'            => [1, 1, [1]],
            'pocas sin ellipsis'  => [1, 5, [1, 2, 3, 4, 5]],
            'inicio con ellipsis' => [1, 10, [1, 2, 3, null, 10]],
            'medio con dos'       => [5, 10, [1, null, 3, 4, 5, 6, 7, null, 10]],
            'final con una'       => [9, 10, [1, null, 7, 8, 9, 10]],
            'última exacta'       => [10, 10, [1, null, 8, 9, 10]],
        ];
    }

    #[DataProvider('windows')]
    public function test_ventana(int $current, int $last, array $expected): void
    {
        self::assertSame($expected, Pagination::window($current, $last));
    }

    public function test_pagina_intermedia_trae_su_slice_y_contadores(): void
    {
        $this->seedTasks(25);

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
        $this->seedTasks(25);

        $p = Pagination::paginate($this->query(), 99, 10, '/tareas');

        self::assertSame(3, $p['current_page']);
        self::assertCount(5, $p['items']);
        self::assertSame(21, $p['from']);
        self::assertSame(25, $p['to']);
    }

    public function test_pagina_cero_o_negativa_es_la_primera(): void
    {
        $this->seedTasks(5);

        self::assertSame(1, Pagination::paginate($this->query(), 0, 10, '/t')['current_page']);
        self::assertSame(1, Pagination::paginate($this->query(), -3, 10, '/t')['current_page']);
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
        $this->seedTasks(25);

        $p = Pagination::paginate($this->query(), 1, 999, '/tareas');

        self::assertSame(10, $p['per_page']);
        self::assertSame(3, $p['last_page']);
    }

    private function query(): Builder
    {
        return Task::orderByDesc('id');
    }

    private function seedTasks(int $n): void
    {
        for ($i = 1; $i <= $n; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
    }
}
