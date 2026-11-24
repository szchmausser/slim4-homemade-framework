<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\ListQuery;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * `ListQuery` es el primitivo que convierte `?q=&sort=&dir=&<filtro>` en una
 * consulta segura sobre un whitelist declarado: el nombre de columna NUNCA
 * sale del request.
 *
 * La tabla de abajo es un fixture de ESTE test, no el esquema de la app: existe
 * para ejercitar `apply()` contra un SQLite real sin depender de una entidad
 * del dominio (ni de un demo instalado). El esquema de la app sigue siendo
 * propiedad de las migraciones.
 */
final class ListQueryTest extends TestCase
{
    private Connection $connection;

    private Model $model;

    protected function setUp(): void
    {
        // Conexión propia y en memoria: no toca la Capsule global ni la base
        // de la suite, así que el orden de ejecución no puede contaminarla.
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $this->connection = $capsule->getConnection();
        $this->connection->getPdo()->exec(
            'CREATE TABLE list_query_items (
                id INTEGER PRIMARY KEY,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                status TEXT NOT NULL,
                score INTEGER NOT NULL
            )'
        );

        $this->model = new class extends Model {
            protected $table = 'list_query_items';
            public $timestamps = false;
            protected $guarded = [];
        };

        $this->seed();
    }

    // --- parsing -------------------------------------------------------------

    public function test_sin_query_string_no_hay_estado(): void
    {
        $query = $this->listQuery('/tasks');

        self::assertNull($query->search());
        self::assertSame('score', $query->sort(), 'sin sort, la columna efectiva es la default');
        self::assertSame('desc', $query->direction());
        self::assertSame([], $query->filters());
        self::assertFalse($query->active(), 'los defaults no son estado de la URL');
        self::assertSame('/tasks', $query->url('/tasks'));
    }

    public function test_la_busqueda_se_recorta_y_se_limita(): void
    {
        self::assertSame('plan', $this->listQuery('/tasks?q=%20%20plan%20%20')->search());
        self::assertNull($this->listQuery('/tasks?q=')->search());
        self::assertNull($this->listQuery('/tasks?q=%20%20')->search());
        self::assertNull($this->listQuery('/tasks?q[]=plan')->search(), 'un array no es un término');

        $largo = $this->listQuery('/tasks?q=' . urlencode(str_repeat('á', 150)))->search();
        self::assertSame(ListQuery::MAX_SEARCH_LENGTH, mb_strlen((string) $largo));
        self::assertSame(str_repeat('á', ListQuery::MAX_SEARCH_LENGTH), $largo);
    }

    public function test_el_orden_solo_acepta_columnas_del_whitelist(): void
    {
        self::assertSame('title', $this->listQuery('/tasks?sort=title')->sort());
        self::assertSame('score', $this->listQuery('/tasks?sort=id')->sort(), 'sort=id no es una columna sortable');
        self::assertSame('score', $this->listQuery('/tasks?sort[]=title')->sort());

        $sinDefault = $this->options();
        unset($sinDefault['default']);
        $query = $this->listQuery('/tasks?sort=id', $sinDefault);
        self::assertNull($query->sort(), 'sin default ni sort válido no hay orden');
        self::assertSame('asc', $query->direction());
    }

    public function test_la_direccion_se_normaliza_a_asc_o_desc(): void
    {
        self::assertSame('desc', $this->listQuery('/tasks?dir=DESC')->direction());
        self::assertSame('desc', $this->listQuery('/tasks?dir=%20desc%20')->direction());
        self::assertSame('asc', $this->listQuery('/tasks?dir=asc')->direction());
        self::assertSame('desc', $this->listQuery('/tasks?dir=%3Bdrop')->direction(), 'basura cae al default, no al SQL');
        self::assertSame('desc', $this->listQuery('/tasks?dir[]=asc')->direction());
    }

    public function test_los_filtros_solo_aceptan_valores_del_whitelist(): void
    {
        self::assertSame(
            ['status' => 'pendiente'],
            $this->listQuery('/tasks?status=pendiente')->filters()
        );

        self::assertSame([], $this->listQuery('/tasks?status=borrado')->filters(), 'fuera del whitelist se ignora');
        self::assertSame([], $this->listQuery('/tasks?otro=pendiente')->filters(), 'una columna no declarada no entra');
        self::assertSame([], $this->listQuery('/tasks?status[]=pendiente')->filters());
    }

    // --- url() ---------------------------------------------------------------

    public function test_url_serializa_solo_el_estado_explicito(): void
    {
        self::assertSame('/tasks', $this->listQuery('/tasks?sort=id')->url('/tasks'), 'lo rechazado no vuelve a la URL');

        self::assertSame(
            '/tasks?q=plan',
            $this->listQuery('/tasks?q=plan')->url('/tasks')
        );

        self::assertSame(
            '/tasks?q=plan&sort=title&dir=desc&status=pendiente',
            $this->listQuery('/tasks?q=plan&sort=title&dir=DESC&status=pendiente&otro=x')->url('/tasks'),
            'el orden de los parámetros es estable: q, sort, dir y filtros'
        );

        self::assertSame(
            '/tasks?q=100%25',
            $this->listQuery('/tasks?q=100%25')->url('/tasks'),
            'el término viaja crudo y URL-encodeado'
        );
    }

    public function test_url_conserva_una_query_string_previa(): void
    {
        self::assertSame(
            '/tasks?page=2&q=plan',
            $this->listQuery('/tasks?q=plan')->url('/tasks?page=2')
        );
    }

    public function test_active_solo_cuando_hay_estado_en_la_url(): void
    {
        self::assertFalse($this->listQuery('/tasks')->active());
        self::assertFalse($this->listQuery('/tasks?sort=id&dir=nope&status=borrado')->active(), 'todo rechazado no es estado');
        self::assertTrue($this->listQuery('/tasks?q=plan')->active());
        self::assertTrue($this->listQuery('/tasks?sort=title')->active());
        self::assertTrue($this->listQuery('/tasks?dir=desc')->active());
        self::assertTrue($this->listQuery('/tasks?status=pendiente')->active());
    }

    // --- apply() contra SQLite ----------------------------------------------

    public function test_apply_sin_busqueda_no_agrega_clausula(): void
    {
        $ids = $this->listQuery('/tasks')->apply($this->builder())->get()->pluck('id')->all();

        self::assertSame([4, 1, 3, 2], $ids, 'sin q quedan todas las filas, ordenadas por el default');
    }

    public function test_apply_busca_en_cualquiera_de_las_columnas_con_or(): void
    {
        $ids = $this->listQuery('/tasks?q=plan')->apply($this->builder())->get()->pluck('id')->all();

        // 2 lo tiene en title y 3 en body: el OR cubre las dos columnas.
        self::assertSame([3, 2], $ids);
    }

    public function test_apply_escapa_los_comodines_de_like(): void
    {
        $cien = $this->listQuery('/tasks?q=100%25')->apply($this->builder())->get()->pluck('id')->all();
        self::assertSame([1], $cien, '100% busca el literal, no «todo»');

        $guion = $this->listQuery('/tasks?q=a_b')->apply($this->builder())->get()->pluck('id')->all();
        self::assertSame([2], $guion, 'a_b no puede matchear aXb');

        $sinEscapar = $this->listQuery('/tasks?q=aXb')->apply($this->builder())->get()->pluck('id')->all();
        self::assertSame([4], $sinEscapar, 'el literal que sí coincide sigue encontrándose');
    }

    public function test_apply_filtra_solo_valores_aceptados(): void
    {
        self::assertSame(
            [1, 3],
            $this->listQuery('/tasks?status=pendiente')->apply($this->builder())->get()->pluck('id')->all()
        );

        self::assertSame(
            [4, 1, 3, 2],
            $this->listQuery('/tasks?status=borrado')->apply($this->builder())->get()->pluck('id')->all(),
            'un filtro fuera del whitelist se ignora, no rompe la lista'
        );
    }

    public function test_apply_ordena_con_la_columna_y_direccion_efectivas(): void
    {
        self::assertSame(
            [1, 4, 2, 3],
            $this->listQuery('/tasks?sort=title&dir=asc')->apply($this->builder())->get()->pluck('id')->all()
        );

        self::assertSame(
            [3, 2, 4, 1],
            $this->listQuery('/tasks?sort=title&dir=desc')->apply($this->builder())->get()->pluck('id')->all()
        );
    }

    public function test_sort_no_whitelisteado_no_cambia_el_orden(): void
    {
        $porDefault = $this->listQuery('/tasks')->apply($this->builder())->get()->pluck('id')->all();
        $conSortId = $this->listQuery('/tasks?sort=id')->apply($this->builder())->get()->pluck('id')->all();

        self::assertSame([4, 1, 3, 2], $porDefault);
        self::assertSame($porDefault, $conSortId, 'sort=id no puede convertirse en un ORDER BY id');
    }

    public function test_dir_invalida_cae_a_la_direccion_default(): void
    {
        $ids = $this->listQuery('/tasks?dir=%3Bdrop')->apply($this->builder())->get()->pluck('id')->all();

        self::assertSame([4, 1, 3, 2], $ids, 'dir=;drop no llega al ORDER BY: queda el default desc');
    }

    public function test_apply_compone_busqueda_filtro_y_orden(): void
    {
        $ids = $this->listQuery('/tasks?q=plan&status=pendiente&sort=title&dir=asc')
            ->apply($this->builder())
            ->get()
            ->pluck('id')
            ->all();

        self::assertSame([3], $ids, 'sólo la fila que cumple búsqueda Y filtro');
    }

    public function test_apply_devuelve_el_mismo_builder_para_encadenar(): void
    {
        $builder = $this->builder();

        self::assertSame($builder, $this->listQuery('/tasks')->apply($builder));
    }

    // --- helpers -------------------------------------------------------------

    /** @return array<string, mixed> */
    private function options(): array
    {
        return [
            'search'  => ['title', 'body'],
            'sort'    => ['title', 'score'],
            'default' => ['score', 'desc'],
            'filters' => ['status' => ['pendiente', 'completado']],
        ];
    }

    /** @param array<string, mixed> $options */
    private function listQuery(string $uri, array $options = []): ListQuery
    {
        return ListQuery::fromRequest($this->request($uri), $options === [] ? $this->options() : $options);
    }

    private function request(string $uri): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost' . $uri);
    }

    private function builder(): Builder
    {
        return (new Builder($this->connection->query()))->setModel($this->model);
    }

    private function seed(): void
    {
        $this->connection->table('list_query_items')->insert([
            ['id' => 1, 'title' => 'Informe 100% real', 'body' => 'cierre', 'status' => 'pendiente', 'score' => 30],
            ['id' => 2, 'title' => 'Plan a_b interno', 'body' => 'detalle', 'status' => 'completado', 'score' => 10],
            ['id' => 3, 'title' => 'Zeta', 'body' => 'Plan de cierre', 'status' => 'pendiente', 'score' => 20],
            ['id' => 4, 'title' => 'Otro aXb', 'body' => 'nada', 'status' => 'archivado', 'score' => 40],
        ]);
    }
}
