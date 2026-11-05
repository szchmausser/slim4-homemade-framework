<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\AppFactory;

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
final class ToastFlowTest extends TestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        $_SESSION['user_id'] = User::create([
            'email'         => 'yo@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;
    }

    protected function tearDown(): void
    {
        // El schema vive en un SQLite compartido: si no limpiás, la suite
        // depende del orden de ejecución.
        unset($_SESSION['user_id']);
        User::query()->delete();
        Task::query()->delete();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_crear_con_htmx_manda_el_aviso_en_el_header_y_no_en_el_html(): void
    {
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ], htmx: true)
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
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas', [
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
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => '',
            ], htmx: true)
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
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('POST', '/tareas/' . $task->id . '/toggle', [], htmx: true, headers: [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ])
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
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('DELETE', '/tareas/' . $task->id, [], htmx: true, headers: [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertNull(Task::find($task->id));

        $items = $this->toastItems($response);
        self::assertSame('Tarea eliminada.', $items[0]['message'] ?? null);
        self::assertSame('success', $items[0]['type'] ?? null);
    }

    public function test_eliminar_un_id_inexistente_avisa_error_en_vez_de_mentir(): void
    {
        $token = $this->token();

        $response = self::app()->handle(
            $this->request('DELETE', '/tareas/999999', [], htmx: true, headers: [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
            ])
        );

        self::assertSame(200, $response->getStatusCode());

        $items = $this->toastItems($response);
        self::assertSame('La tarea no existe o ya fue eliminada.', $items[0]['message'] ?? null);
        self::assertSame('error', $items[0]['type'] ?? null);
    }

    public function test_una_navegacion_htmx_sin_accion_no_emite_toasts(): void
    {
        $response = self::app()->handle($this->request('GET', '/tareas', [], htmx: true));

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

    /**
     * Extrae los hidden de partials/_csrf.twig. Los tokens se piden con un GET
     * sin HX-Request: ese render deja además banners inline vacíos, así que
     * scrapear acá no depende del canal de avisos.
     *
     * @return array{csrf_name: string, csrf_value: string}
     */
    private function token(): array
    {
        $html = (string) self::app()->handle($this->request('GET', '/tareas'))->getBody();

        preg_match('/name="csrf_name"\s+value="([^"]+)"/', $html, $n);
        preg_match('/name="csrf_value"\s+value="([^"]+)"/', $html, $v);

        self::assertArrayHasKey(1, $n, 'no encontré el hidden csrf_name');
        self::assertArrayHasKey(1, $v, 'no encontré el hidden csrf_value');

        return ['csrf_name' => $n[1], 'csrf_value' => $v[1]];
    }

    /**
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function request(
        string $method,
        string $path,
        array $body = [],
        bool $htmx = false,
        array $headers = []
    ): ServerRequestInterface {
        // URI absoluta, igual que en el resto de la suite: con path-only el
        // host queda vacío y Slim se porta distinto en los casos borde.
        $request = (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);

        if ($body !== []) {
            // Body crudo con su Content-Type: así pasa por el Body Parsing
            // Middleware igual que en producción. withParsedBody() saltearía
            // justo la capa que rompen los cambios de orden del pipeline.
            $request = $request
                ->withBody((new StreamFactory())->createStream(http_build_query($body)))
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        // Solo se setea cuando corresponde: un header presente con valor vacío
        // cuenta como "no HTMX" en el resto de la app, pero hasHeader() no
        // distingue, así que acá no se agrega nunca vacío.
        return $htmx ? $request->withHeader('HX-Request', 'true') : $request;
    }
}
