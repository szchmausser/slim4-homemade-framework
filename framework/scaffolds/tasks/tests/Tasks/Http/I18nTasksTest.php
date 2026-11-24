<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Models\Task;
use Psr\Http\Message\ResponseInterface;

/**
 * El demo Tareas en los dos idiomas.
 *
 * Dos contratos distintos y complementarios:
 * - S7: el español de la pantalla es el mismo que antes de externalizar las
 *   cadenas, carácter por carácter.
 * - Locale por request: con `?lang=en` la pantalla, el sidebar y los avisos
 *   salen en inglés, sin tocar `APP_LOCALE` ni reiniciar nada.
 *
 * Los textos se piden por HTTP y no por `twig()->render()` a propósito: el
 * locale lo fija `LocaleMiddleware` en cada petición, así que el resultado no
 * depende del idioma que dejó el test anterior.
 *
 * Para tu entidad: si tus textos salen del catálogo, este archivo es el molde;
 * cambia las rutas, las claves y los dos juegos de textos.
 */
final class I18nTasksTest extends TasksTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
    }

    /** Texto del render con las entidades HTML decodificadas. */
    private static function texto(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Los avisos de una respuesta de htmx viajan en `HX-Trigger-After-Swap`
     * (`Controller::withToasts()`), no en `HX-Trigger`.
     *
     * @return list<array{message: string, type: string}>
     */
    private function toasts(ResponseInterface $response): array
    {
        $header = $response->getHeaderLine('HX-Trigger-After-Swap');

        self::assertNotSame('', $header, 'La mutación no emitió HX-Trigger.');

        $payload = json_decode($header, true, 512, JSON_THROW_ON_ERROR);
        $items = $payload['toasts']['items'] ?? null;

        self::assertIsArray($items, 'El header no trae toasts.items.');

        return $items;
    }

    /** S7: la pantalla en español dice lo mismo que decía antes del cambio. */
    public function test_la_pantalla_conserva_el_espanol_de_head(): void
    {
        Task::create(['title' => 'Comprar pan']);

        $html = self::texto((string) self::app()->handle(self::request('GET', '/tasks'))->getBody());

        self::assertStringContainsString('<title>Tareas</title>', $html);
        self::assertStringContainsString('Mis tareas', $html);
        self::assertStringContainsString('CRUD de tareas con HTMX, edición inline y paginación sobre SQLite.', $html);
        self::assertStringContainsString('placeholder="Nueva tarea"', $html);
        self::assertStringContainsString('<span>Agregar</span>', $html);
        self::assertStringContainsString('aria-label="Pendiente"', $html);
        self::assertStringContainsString('aria-label="Completar"', $html);
        self::assertStringContainsString('aria-label="Editar"', $html);
        self::assertStringContainsString('aria-label="Eliminar"', $html);
        self::assertStringContainsString('¿Borrar esta tarea?', $html);
        self::assertStringContainsString('<span>Borrar</span>', $html);
        self::assertStringContainsString('Mostrando 1 al 1 de 1 tareas', $html);

        // Listado: búsqueda, filtro y encabezados ordenables.
        self::assertStringContainsString('<span>Buscar</span>', $html);
        self::assertStringContainsString('placeholder="Buscar por título"', $html);
        self::assertStringContainsString('<span class="text-sm font-semibold">Estado</span>', $html);
        self::assertStringContainsString('Todos los estados', $html);
        self::assertStringContainsString('aria-label="Ordenar por"', $html);
        self::assertStringContainsString('>Título</a>', $html);

        // La fila en edición trae el label del campo y los dos botones: el swap
        // de `/tasks/{id}/edit` es una respuesta de htmx, así que se pide así.
        $task = Task::query()->firstOrFail();
        $editing = self::texto((string) self::app()->handle(
            self::request('GET', '/tasks/' . $task->id . '/edit', [], ['HX-Request' => 'true'])
        )->getBody());

        self::assertStringContainsString('for="task-title-' . $task->id . '">', $editing);
        self::assertStringContainsString('Título de la tarea', $editing);
        self::assertStringContainsString('<span>Guardar</span>', $editing);
        self::assertStringContainsString('<span>Cancelar</span>', $editing);
    }

    /** El estado completado arma la frase con su fecha, sin concatenar en la vista. */
    public function test_el_estado_completado_conserva_su_fecha(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $task->update(['status' => 'completado', 'completed_at' => '2026-02-02 03:04:05']);

        $html = self::texto((string) self::app()->handle(self::request('GET', '/tasks'))->getBody());

        self::assertStringContainsString('Completada el 02/02/2026 3:04 AM', $html);
    }

    /**
     * Con `?lang=en` la pantalla, el sidebar y los avisos salen en inglés.
     *
     * Una aserción visible por superficie: encabezado, estado vacío, campo,
     * sidebar, ayuda del estado y los dos flashes que emite el controlador.
     */
    public function test_con_lang_en_la_pantalla_y_los_avisos_salen_en_ingles(): void
    {
        $response = self::app()->handle(self::request('GET', '/tasks?lang=en'));
        $html = self::texto((string) $response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<html lang="en"', $html);
        self::assertStringContainsString('<title>Tasks</title>', $html);
        self::assertStringContainsString('My tasks', $html);
        self::assertStringContainsString('No tasks yet.', $html);
        self::assertStringContainsString('placeholder="New task"', $html);
        self::assertStringContainsString('<span>Add</span>', $html);
        self::assertStringContainsString('>Tasks<', $html, 'El sidebar no muestra la etiqueta traducida.');
        self::assertStringContainsString('>Home</a>', $html, 'La miga raíz no sale del catálogo.');

        // El listado también cambia de idioma: botón, filtro y encabezados.
        self::assertStringContainsString('<span>Search</span>', $html);
        self::assertStringContainsString('placeholder="Search by title"', $html);
        self::assertStringContainsString('All statuses', $html);
        self::assertStringContainsString('aria-label="Sort by"', $html);
        self::assertStringContainsString('aria-current="page">Tasks', $html);
        self::assertStringNotContainsString('>Inicio</a>', $html);
        self::assertStringNotContainsString('Mis tareas', $html);
        self::assertStringNotContainsString('>Tareas<', $html);

        $token = self::token('/tasks?lang=en');

        $created = self::app()->handle(self::request('POST', '/tasks?lang=en', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => 'Buy bread',
        ], ['HX-Request' => 'true']));

        self::assertSame('Task created.', $this->toasts($created)[0]['message'] ?? null);

        $task = Task::query()->firstOrFail();

        $toggled = self::app()->handle(self::request(
            'POST',
            '/tasks/' . $task->id . '/toggle?lang=en',
            [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ],
            ['HX-Request' => 'true']
        ));

        self::assertSame('Task completed.', $this->toasts($toggled)[0]['message'] ?? null);

        // El swap devuelve el panel: el estado nuevo también se lee en inglés.
        $panel = self::texto((string) $toggled->getBody());

        self::assertStringContainsString('Completed on', $panel);
        self::assertStringContainsString('aria-label="Mark as not done"', $panel);
    }
}
