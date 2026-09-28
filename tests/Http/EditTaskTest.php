<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

/**
 * La U de CRUD: la edición es inline en la fila.
 *
 * El form de edición vive dentro de `_list.twig`, o sea dentro del panel que
 * el swap reemplaza. Ahí no alcanzan los hidden de `#alta-tarea`, que queda
 * fuera de ese panel, así que el form lleva sus propios tokens CSRF.
 *
 * El PUT llega como `application/x-www-form-urlencoded` (el default de htmx
 * para verbos no-GET) y lo parsea `addBodyParsingMiddleware()`.
 */
final class EditTaskTest extends TestCase
{
    protected function tearDown(): void
    {
        // El schema vive en memoria y se crea una sola vez: si no limpiás,
        // la suite depende de la orden de ejecución.
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_get_edit_renderiza_el_formulario_de_la_fila(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('GET', '/tareas/' . $task->id . '/edit')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('hx-put="/tareas/' . $task->id . '"', $html);
        self::assertStringContainsString('value="Comprar pan"', $html);
        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('Cancelar', $html);
    }

    public function test_get_edit_de_un_id_inexistente_avisa_en_lugar_de_ignorar(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas/999999/edit')
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('tareas-panel', $html);
        self::assertStringContainsString('La tarea no existe o ya fue eliminada.', $html);
        self::assertStringNotContainsString('hx-put=', $html, 'No hay fila que editar.');
    }

    public function test_put_con_token_valido_actualiza_la_tarea(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan integral',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Comprar pan integral', $task->fresh()->title);
        self::assertStringContainsString('Comprar pan integral', (string) $response->getBody());
        // El swap vuelve a la lista normal: editing_id queda en null.
        self::assertStringNotContainsString('hx-put=', (string) $response->getBody());
    }

    public function test_put_sin_titulo_no_actualiza_y_muestra_el_flash(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = $this->token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => '',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title, 'La validación falló: el título no cambia.');
        self::assertStringContainsString('bg-red-100', (string) $response->getBody(), 'El flash de error no se renderizó.');
        // La fila se queda en modo formulario: si volviera a la lista, el
        // usuario pierde el lugar que estaba editando junto con el error.
        self::assertStringContainsString('hx-put=', (string) $response->getBody());
    }

    public function test_put_a_un_id_inexistente_avisa_en_lugar_de_ignorar(): void
    {
        $token = $this->token();

        $response = self::app()->handle(
            self::request('PUT', '/tareas/999999', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'No debería guardarse',
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'La tarea no existe o ya fue eliminada.',
            (string) $response->getBody()
        );
        self::assertSame(0, Task::count(), 'No se creó ninguna fila nueva.');
    }

    public function test_put_sin_token_devuelve_400_para_htmx(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);

        $response = self::app()->handle(
            self::request('PUT', '/tareas/' . $task->id, ['title' => 'No debería guardarse'], htmx: true)
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Comprar pan', $task->fresh()->title);
    }

    /**
     * Extrae los dos hidden que emite partials/_csrf.twig. El name real del
     * input es `csrf_name` / `csrf_value`: `csrf_name_key` es variable de Twig
     * y no aparece en el HTML renderizado.
     */
    private function token(): array
    {
        $html = (string) self::app()->handle(self::request('GET', '/tareas'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    private static function request(string $method, string $path, array $body = [], bool $htmx = false): ServerRequestInterface
    {
        // URI absoluta y body crudo con su Content-Type, para que pase por el
        // Body Parsing Middleware igual que en producción.
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
