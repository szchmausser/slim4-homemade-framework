<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Support\AppFactory;
use Tests\Tasks\Http\TasksTestCase;

/**
 * El demo bajo APP_BASE_PATH: listado, paginación y HX-Push-Url no pueden
 * perder el prefijo. Se instala junto con el scaffold demo.
 */
final class BasePathTasksTest extends TasksTestCase
{
    private const PREFIJO = '/app';

    protected function setUp(): void
    {
        self::prefijo(self::PREFIJO);
        AppFactory::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        self::prefijo('');
        AppFactory::reset();
    }

    public function test_el_listado_responde_bajo_el_prefijo_y_ninguna_url_queda_en_la_raiz(): void
    {
        self::loginAs();

        $response = self::app()->handle(self::request('GET', '/app/tareas'));

        self::assertSame(200, $response->getStatusCode(), 'el prefijo no llegó al router');
        $html = (string) $response->getBody();

        self::assertStringContainsString('href="/app/tareas"', $html);
        self::assertStringContainsString('href="/app/assets/daisyui-5.7.46.css"', $html);

        self::assertDoesNotMatchRegularExpression(
            '#(?:href|src|action|hx-get|hx-post|hx-put|hx-delete)="/(?!app/)#',
            $html,
            'quedó una URL raíz-absoluta que no lleva el prefijo'
        );
    }

    public function test_la_paginacion_y_la_url_empujada_llevan_el_prefijo(): void
    {
        self::loginAs();

        // 11 filas: con 10 por página existe una página 2, y sin eso la
        // paginación no emite ningún <a href> (son botones apagados).
        for ($i = 1; $i <= 11; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }

        $html = (string) self::app()->handle(self::request('GET', '/app/tareas'))->getBody();

        self::assertMatchesRegularExpression(
            '#<a[^>]*href="/app/tareas\?page=2&(?:amp;)?per_page=10"#',
            $html,
            'la paginación tiene que llevar el prefijo'
        );

        $task = Task::orderByDesc('id')->first();
        self::assertNotNull($task, 'el fixture tiene que haber creado tareas');

        $token = self::token('/app/tareas');

        $response = self::app()->handle(
            self::request('POST', sprintf('/app/tareas/%d/toggle?page=1&per_page=10', $task->id))
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            '/app/tareas?page=1&per_page=10',
            $response->getHeaderLine('HX-Push-Url')
        );
    }

    private static function prefijo(string $valor): void
    {
        $_ENV['APP_BASE_PATH'] = $valor;
        putenv('APP_BASE_PATH=' . $valor);
    }
}
