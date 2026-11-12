<?php

declare(strict_types=1);

namespace Tests\Notas\Http;

use App\Notas\Models\Nota;
use Tests\Support\HttpTestCase;

final class NotaTest extends HttpTestCase
{
    protected function setUp(): void
    {
        self::loginAs();
        Nota::query()->delete();
    }

    protected function tearDown(): void
    {
        Nota::query()->delete();
        parent::tearDown();
    }

    private function crear(array $datos = []): Nota
    {
        return Nota::create(['titulo' => 'De prueba'] + $datos);
    }

    public function test_crea_una_nota_y_la_muestra_en_la_lista(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => 'Primera nota',
            'cuerpo'     => 'Contenido',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/notas', $response->getHeaderLine('Location'));
        self::assertSame(1, Nota::query()->count());

        $lista = self::app()->handle(self::request('GET', '/notas'));
        self::assertStringContainsString('Primera nota', (string) $lista->getBody());
    }

    public function test_el_alta_invalida_reabre_el_modal_con_lo_tipeado(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => '',
            'cuerpo'     => 'Texto que quiero conservar',
        ]));
        $html = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, Nota::query()->count());
        self::assertStringContainsString('Este campo es obligatorio.', $html);
        self::assertStringContainsString('Texto que quiero conservar', $html);
        self::assertMatchesRegularExpression('/nota-create-modal[^>]*open/', $html);
    }

    public function test_edita_una_nota(): void
    {
        $nota = $this->crear(['titulo' => 'Antes']);
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas/' . $nota->id, [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => 'Después',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('Después', (string) Nota::find($nota->id)?->titulo);
    }

    public function test_el_rechazo_de_la_edicion_reabre_su_modal_y_no_el_del_alta(): void
    {
        $nota = $this->crear(['titulo' => 'Actual']);
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas/' . $nota->id, [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => '',
        ]));
        $html = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertMatchesRegularExpression('/nota-edit-modal[^>]*open/', $html);
        self::assertDoesNotMatchRegularExpression('/nota-create-modal[^>]*open/', $html);
    }

    public function test_borra_una_nota_con_confirmacion(): void
    {
        $nota = $this->crear();
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas/' . $nota->id . '/delete', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertNull(Nota::find($nota->id));
    }

    public function test_sin_token_csrf_redirige_al_login_y_no_crea_nada(): void
    {
        $response = self::app()->handle(self::request('POST', '/notas', [
            'titulo' => 'Sin token',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertSame(0, Nota::query()->count());
    }

    public function test_pagina_por_region_y_expone_la_url_de_la_pagina_renderizada(): void
    {
        foreach (range(1, 12) as $i) {
            $this->crear(['titulo' => 'Nota ' . $i]);
        }

        $response = self::app()->handle(self::request(
            'GET',
            '/notas?page=2&per_page=5',
            [],
            ['HX-Request' => 'true']
        ));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/notas?page=2&per_page=5', $response->getHeaderLine('HX-Push-Url'));
        // Región, no página: sin shell dentro de la respuesta.
        self::assertStringNotContainsString('<aside', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertSame(5, substr_count($html, 'rounded-box border border-base-300'));
    }

    public function test_los_formularios_deshabilitan_el_boton_para_no_duplicar(): void
    {
        $this->crear();
        $html = (string) self::app()->handle(self::request('GET', '/notas'))->getBody();

        self::assertStringContainsString(
            "this.querySelector('[type=submit]').disabled = true",
            $html,
            'Sin esta guarda, un doble clic en un POST plano crea dos filas.'
        );
    }
}
