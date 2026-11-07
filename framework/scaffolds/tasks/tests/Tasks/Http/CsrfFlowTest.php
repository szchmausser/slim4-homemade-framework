<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use Tests\Tasks\Http\TasksTestCase;

final class CsrfFlowTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth): estos tests entran logueados.
        // La capa de auth en sí se testea en AuthTest, no acá.
        self::loginAs();
    }

    public function test_get_renderiza_el_formulario_con_token(): void
    {
        $response = self::app()->handle(
            self::request('GET', '/tareas')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="csrf_name"', (string) $response->getBody());
    }

    public function test_post_con_token_valido_crea_la_tarea(): void
    {
        $app = self::app();

        // 1. El GET abre la sesión y deja el token en ella.
        $getResponse = $app->handle(self::request('GET', '/tareas'));
        $token = $this->scrapeToken((string) $getResponse->getBody());

        // 2. El POST reutiliza ese token.
        $postResponse = $app->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => $token['csrf_name'],
                'csrf_value' => $token['csrf_value'],
                'title'      => 'Comprar pan',
            ])
        );

        self::assertSame(200, $postResponse->getStatusCode());
        self::assertStringContainsString('Comprar pan', (string) $postResponse->getBody());
    }

    public function test_post_con_token_invalido_redirige_al_login(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', [
                'csrf_name'  => 'basura',
                'csrf_value' => 'basura',
                'title'      => 'No deberia guardarse',
            ])
        );

        // Sin el failureHandler del capítulo 6 esto devolvería 400
        // "Failed CSRF check!" en text/plain. El 303 comprueba que nuestro
        // handler está conectado en la posición correcta del constructor.
        self::assertSame(303, $response->getStatusCode());
        self::assertStringNotContainsString('No deberia guardarse', (string) $response->getBody());
    }

    public function test_post_htmx_con_token_invalido_devuelve_400_y_hx_trigger(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', ['title' => 'x'], ['HX-Request' => 'true'])
        );

        self::assertSame(400, $response->getStatusCode());
        // El valor es {"csrf":"..."} — asserteamos contra el valor, no contra
        // el nombre del header (getHeader() devuelve los valores).
        $hxTrigger = $response->getHeaderLine('HX-Trigger');
        self::assertStringContainsString('csrf', $hxTrigger);

        // El header tiene que ser JSON con la clave `csrf`: es lo que lee
        // `@csrf.window` del layout. Un texto plano no tiene claves y el
        // listener se queda con undefined.
        $payload = json_decode($hxTrigger, true);
        self::assertIsArray($payload, 'HX-Trigger tiene que ser JSON.');
        self::assertArrayHasKey('csrf', $payload);
    }

    public function test_el_hx_trigger_del_csrf_tiene_alguien_que_lo_escuche(): void
    {
        // Existe por un bug real que ningún otro test veía: el failureHandler
        // emite HX-Trigger:{csrf:...} y no había NINGÚN listener en las
        // plantillas. El test de arriba assertea que el header salga — y sale,
        // perfecto. Pero llegaba al vacío: HTMX recibe el 400, no hace swap
        // (no es 2xx), no dispara nada visible y la tarea desaparece sin
        // error. Test verde, producción rota.
        //
        // Asserteamos el lado de la RECEPCIÓN, que es el que se rompe. Si
        // tocás el shell y te llevás el @csrf.window, este test falla antes
        // que alguien pierda una tarea en silencio.
        self::assertStringContainsString(
            '@csrf.',
            $this->shellSource(),
            'El failureHandler emite HX-Trigger:{csrf} pero nada lo escucha: el fallo CSRF queda silencioso.'
        );
    }

    public function test_el_listener_de_csrf_lee_el_valor_del_evento(): void
    {
        // Regresión del bug 12: con `String($event.detail)` el toast mostraba
        // "[object Object]". El texto vive en `.value`, no en el objeto
        // entero, y buscar solo `@csrf.` pasaba con las dos variantes.
        $layout = $this->shellSource();

        self::assertStringContainsString(
            '$event.detail.value',
            $layout,
            'El listener de CSRF tiene que leer $event.detail.value: con $event.detail el aviso dice [object Object].'
        );
        self::assertStringContainsString('@csrf.window=', $layout);
    }
}
