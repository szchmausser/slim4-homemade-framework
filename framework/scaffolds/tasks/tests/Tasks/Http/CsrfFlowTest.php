<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use Tests\Tasks\Http\TasksTestCase;

/**
 * CSRF de punta a punta en una ruta del demo: el token viaja en el formulario, el
 * POST sin token no crea nada y el 400 de htmx llega con un evento que alguien
 * escucha.
 *
 * Capa tests. La autenticación en sí no se prueba acá: `/tareas` exige login y estos
 * casos entran logueados.
 *
 * Para tu entidad, copia el archivo y cambia la ruta.
 */
final class CsrfFlowTest extends TasksTestCase
{
    protected function setUp(): void
    {
        // `/tareas` exige login (RequireAuth): estos casos entran logueados.
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

        // Sin el failureHandler del guard esto devolvería 400 "Failed CSRF
        // check!" en texto plano; el 303 comprueba que está conectado.
        self::assertSame(303, $response->getStatusCode());
        self::assertStringNotContainsString('No deberia guardarse', (string) $response->getBody());
    }

    public function test_post_htmx_con_token_invalido_devuelve_400_y_hx_trigger(): void
    {
        $response = self::app()->handle(
            self::request('POST', '/tareas', ['title' => 'x'], ['HX-Request' => 'true'])
        );

        self::assertSame(400, $response->getStatusCode());
        // El header es JSON con la clave `csrf`: es lo que lee `@csrf.window` del
        // layout. Un texto plano no tiene claves y el listener queda en undefined.
        $hxTrigger = $response->getHeaderLine('HX-Trigger');
        self::assertStringContainsString('csrf', $hxTrigger);

        $payload = json_decode($hxTrigger, true);
        self::assertIsArray($payload, 'HX-Trigger tiene que ser JSON.');
        self::assertArrayHasKey('csrf', $payload);
    }

    public function test_el_hx_trigger_del_csrf_tiene_alguien_que_lo_escuche(): void
    {
        // Que el header salga no alcanza: htmx no swapea un 400, así que sin un
        // listener el fallo queda mudo y la tarea desaparece sin error. Este caso
        // mira la RECEPCIÓN, que es el lado que se rompe al tocar el shell.
        self::assertStringContainsString(
            '@csrf.',
            $this->shellSource(),
            'El failureHandler emite HX-Trigger:{csrf} pero nada lo escucha: el fallo CSRF queda silencioso.'
        );
    }

    public function test_el_listener_de_csrf_lee_el_valor_del_evento(): void
    {
        // El texto vive en `.value`, no en el objeto entero: con `$event.detail`
        // el aviso mostraría `[object Object]`, y buscar sólo `@csrf.` pasaría
        // con las dos variantes.
        $layout = $this->shellSource();

        self::assertStringContainsString(
            '$event.detail.value',
            $layout,
            'El listener de CSRF tiene que leer $event.detail.value: con $event.detail el aviso dice [object Object].'
        );
        self::assertStringContainsString('@csrf.window=', $layout);
    }
}
