<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Url;

/**
 * Contrato del selector de idioma del footer: `POST /locale` con CSRF, la
 * elección guardada en la sesión y el redirect de vuelta sin agujeros.
 *
 * Es una mutación, así que pasa por el Guard como cualquier POST: sin token
 * válido no cambia nada. El `redirect_to` es el punto delicado —un campo que el
 * servidor devuelve como `Location`— y por eso se prueban las formas que
 * abrirían un redirect abierto (`//host`, URL absoluta, barra invertida).
 */
final class LocaleSelectorTest extends HttpTestCase
{
    protected function tearDown(): void
    {
        // El failure handler del Guard deja un aviso para el próximo render;
        // no puede aparecer en el test siguiente.
        unset($_SESSION['locale'], $_SESSION['flash']);

        parent::tearDown();
    }

    public function test_el_footer_ofrece_el_selector_con_el_token(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        self::assertStringContainsString('action="' . Url::path('/locale') . '"', $html);
        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="lang"', $html);
        self::assertStringContainsString('name="redirect_to" value="/"', $html);
        self::assertStringContainsString('<option value="es" selected>Español</option>', $html);
        self::assertStringContainsString('<option value="en">English</option>', $html);
        self::assertStringContainsString('name="csrf_name"', $html);
        self::assertStringContainsString('name="csrf_value"', $html);
    }

    public function test_cada_idioma_se_nombra_en_su_propio_idioma(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/?lang=en'))->getBody();

        self::assertStringContainsString('<option value="en" selected>English</option>', $html);
        self::assertStringContainsString('<option value="es">Español</option>', $html);
    }

    public function test_un_post_valido_cambia_el_idioma_y_vuelve_a_la_pagina(): void
    {
        $response = self::app()->handle(self::request('POST', '/locale', [
            'lang'        => 'en',
            'redirect_to' => '/login',
        ] + self::token()));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(Url::path('/login'), $response->getHeaderLine('Location'));
        self::assertSame('en', $_SESSION['locale'] ?? null);

        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        self::assertStringContainsString('<html lang="en"', $html);
        self::assertStringContainsString('environment', $html);
    }

    public function test_un_csrf_invalido_no_cambia_nada(): void
    {
        $token = self::token();

        $response = self::app()->handle(self::request('POST', '/locale', [
            'lang'        => 'en',
            'redirect_to' => '/',
            'csrf_name'   => $token['csrf_name'],
            'csrf_value'  => 'token-invalido',
        ]));

        // El failure handler del Guard manda al login; lo que importa es que no
        // se haya aplicado la elección.
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(Url::path('/login'), $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('locale', $_SESSION);

        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        self::assertStringContainsString('<html lang="es"', $html);
    }

    public function test_un_locale_inexistente_se_ignora(): void
    {
        $response = self::app()->handle(self::request('POST', '/locale', [
            'lang'        => 'xx',
            'redirect_to' => '/login',
        ] + self::token()));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(Url::path('/login'), $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('locale', $_SESSION);

        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        self::assertStringContainsString('<html lang="es"', $html);
    }

    public function test_el_redirect_no_acepta_urls_absolutas_ni_protocol_relative(): void
    {
        foreach (['//evil.example/x', 'https://evil.example/x', '/\\evil.example', 'evil.example', 'http:/evil.example'] as $hostil) {
            $response = self::app()->handle(self::request('POST', '/locale', [
                'lang'        => 'en',
                'redirect_to' => $hostil,
            ] + self::token()));

            self::assertSame(303, $response->getStatusCode());
            self::assertSame(
                Url::path('/'),
                $response->getHeaderLine('Location'),
                "El redirect aceptó «{$hostil}» como destino."
            );
            self::assertStringStartsNotWith('//', $response->getHeaderLine('Location'));
        }
    }

    public function test_el_redirect_conserva_una_ruta_local_valida(): void
    {
        $response = self::app()->handle(self::request('POST', '/locale', [
            'lang'        => 'es',
            'redirect_to' => '/login?next=%2F',
        ] + self::token()));

        self::assertSame(Url::path('/login?next=%2F'), $response->getHeaderLine('Location'));
    }
}
