<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\HttpTestCase;

final class ErrorHandlingTest extends HttpTestCase
{
    public function test_ruta_inexistente_devuelve_404_y_no_500(): void
    {
        $response = self::app()->handle(self::request('GET', '/no-existe'));

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_las_paginas_de_error_tambien_llevan_security_headers(): void
    {
        $response = self::app()->handle(self::request('GET', '/no-existe'));

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    /**
     * Un 404 no es un defecto: no hay stack trace que mirar. Si Whoops lo
     * renderiza igual, cada URL mal tipeada cuesta ~154 KB de HTML de debugger
     * (medido) en vez del ~1 KB de errors/error.twig. En 32 bits ese
     * pico de memoria se paga caro (cap. 17), y de paso dejas de mirar la
     * página de error real porque todo 404 se ve igual de apocalyptic.
     *
     * También fija el contrato de S7: el mensaje sale en español (no el
     * "Not found." en inglés que manda el vendor) y la página carga el bundle
     * de daisyUI; sin ese <link> todas sus clases son inertes y el código de
     * estado vuelve a verse como texto plano.
     *
     * Este test corre con APP_DEBUG=true (lo que hay en .env), o sea que
     * Whoops está construido: verifica que la rama correcta sea la elegida.
     */
    public function test_un_404_no_gasta_la_pagina_de_whoops(): void
    {
        $response = self::app()->handle(self::request('GET', '/no-existe'));

        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();

        self::assertStringNotContainsString('Whoops', $html);
        self::assertStringContainsString('No encontramos esa página.', $html);
        self::assertStringContainsString('/assets/daisyui-5.7.46.css', $html);
        self::assertLessThan(4096, strlen($html), 'El 404 deberia pesar bytes, no cientos de KB.');
    }
}
