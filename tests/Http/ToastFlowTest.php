<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\HttpTestCase;

/**
 * Avisos después de una acción CRUD: qué canal usa cada tipo de request.
 *
 * El canal lo elige el request, y solo uno:
 * - con `HX-Request` el mensaje viaja en `HX-Trigger-After-Swap` y el HTML
 *   sale SIN banner (si viajara por los dos lados se vería dos veces);
 * - sin `HX-Request` queda inline en `#tareas-panel`, que es el fallback para
 *   formularios y navegaciones normales (ahí no hay evento que escuchar).
 *
 * El segundo test de esta clase es el par del de CSRF: aquel cubre la
 * RECEPCIÓN del evento `csrf` y este la del evento `toasts`. Si alguien
 * reemplaza el stack del layout por un alert suelto, esto falla antes de que
 * el usuario guarde una tarea y no vea nada.
 */
final class ToastFlowTest extends HttpTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        self::loginAs();
    }

    public function test_crear_con_htmx_manda_el_aviso_en_el_header_y_no_en_el_html(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, Task::count());

        $items = $this->toastItems($response);
        self::assertSame('Tarea creada.', $items[0]['message'] ?? null);
        self::assertSame('success', $items[0]['type'] ?? null);

        $html = (string) $response->getBody();
        self::assertStringNotContainsString('Tarea creada.', $html, 'Con htmx el aviso no puede ir también en el HTML.');
        self::assertStringNotContainsString('alert-success', $html);
    }

    public function test_crear_sin_htmx_deja_el_aviso_inline_y_sin_header(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();
        self::assertStringContainsString('alert-success', $html, 'Sin htmx el aviso tiene que quedar inline.');
        self::assertStringContainsString('Tarea creada.', $html);
        self::assertSame(
            '',
            $response->getHeaderLine('HX-Trigger-After-Swap'),
            'Un request sin HX-Request no dispara eventos de htmx.'
        );
    }

    public function test_la_validacion_fallida_llega_como_toast_de_error(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => '',
            ], ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, Task::count(), 'No se crea nada.');

        $items = $this->toastItems($response);
        self::assertSame('error', $items[0]['type'] ?? null);
        self::assertNotSame('', (string) ($items[0]['message'] ?? ''), 'El toast de error llegó vacío.');
        self::assertStringNotContainsString('alert-error', (string) $response->getBody());
    }

    public function test_completar_manda_el_toast_con_el_estado_nuevo(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas/' . $task->id . '/toggle', [], $token + ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('completado', Task::find($task->id)->status);

        $items = $this->toastItems($response);
        self::assertSame('Tarea completada.', $items[0]['message'] ?? null);
        self::assertSame('success', $items[0]['type'] ?? null);
    }

    public function test_eliminar_manda_toast_de_exito(): void
    {
        $task = Task::create(['title' => 'Comprar pan']);
        $token = self::token();

        $response = self::app()->handle(
            self::request('DELETE', '/tareas/' . $task->id, [], $token + ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id));

        $items = $this->toastItems($response);
        self::assertSame('Tarea eliminada.', $items[0]['message'] ?? null);
        self::assertSame('success', $items[0]['type'] ?? null);
    }

    public function test_eliminar_un_id_inexistente_avisa_error_en_vez_de_mentir(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('DELETE', '/tareas/999999', [], $token + ['HX-Request' => 'true'])
        );

        self::assertSame(200, $response->getStatusCode());

        $items = $this->toastItems($response);
        self::assertSame('La tarea no existe o ya fue eliminada.', $items[0]['message'] ?? null);
        self::assertSame('error', $items[0]['type'] ?? null);
    }

    public function test_una_navegacion_htmx_sin_accion_no_emite_toasts(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas', [], ['HX-Request' => 'true']));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('HX-Trigger-After-Swap'), 'Sin flash no hay evento.');
    }

    /**
     * Contrato del layout: el stack y los DOS listeners. El de `csrf` se
     * conserva porque el 400 de slim/csrf no hace swap y no puede usar el
     * canal After-Swap; el de `toasts` lee `.items` (payload objeto).
     */
    public function test_el_layout_recibe_los_dos_eventos_en_el_stack_de_toasts(): void
    {
        $layout = file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/layouts/app.twig'
        );

        self::assertNotFalse($layout, 'No se pudo leer resources/views/layouts/app.twig');
        self::assertStringContainsString('class="toast-host"', $layout, 'Falta el host de avisos.');
        self::assertStringContainsString('toast-progress', $layout, 'La barra de vida es la que retira el toast (animationend).');
        self::assertStringContainsString(
            '@animationend="remove(t.id)"',
            $layout,
            'Sin animationend el toast queda pegado para siempre.'
        );
        self::assertStringContainsString('@toasts.window=', $layout, 'Nadie escucha el evento toasts.');
        self::assertStringContainsString('$event.detail.items', $layout, 'El evento toasts entrega los items en .items.');
        self::assertStringContainsString('@csrf.window=', $layout, 'El listener de CSRF no puede desaparecer.');
        self::assertStringContainsString('$event.detail.value', $layout, 'El evento csrf entrega el texto en .value.');
        self::assertStringNotContainsString(
            'setTimeout(() => this.remove',
            $layout,
            'La vida del toast la lleva la animación CSS, no un timer de JS: con el timer, la barra y el retiro se desincronizan.'
        );
        self::assertStringNotContainsString(
            'jsdelivr',
            $layout,
            'Sin CDN: los assets son locales con SRI.'
        );
    }

    /**
     * @return list<array{message: string, type: string}>
     */
    private function toastItems(ResponseInterface $response): array
    {
        $raw = $response->getHeaderLine('HX-Trigger-After-Swap');

        self::assertNotSame('', $raw, 'La acción htmx no emitió HX-Trigger-After-Swap.');

        $payload = json_decode($raw, true);
        self::assertIsArray($payload, 'HX-Trigger-After-Swap tiene que ser JSON.');

        $items = $payload['toasts']['items'] ?? null;
        self::assertIsArray($items, 'El payload tiene que ser un objeto con toasts.items.');
        self::assertNotSame([], $items, 'El evento toasts llegó sin items.');

        return $items;
    }
}
