<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

final class ErrorHandlingTest extends TestCase
{
    public function test_ruta_inexistente_devuelve_404_y_no_500(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function test_las_paginas_de_error_tambien_llevan_security_headers(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
    }

    /**
     * Un 404 no es un defecto: no hay stack trace que mirar. Si Whoops lo
     * renderiza igual, cada URL mal tipeada cuesta ~154 KB de HTML de debugger
     * (medido) en vez de los ~507 bytes de errors/error.twig. En 32 bits ese
     * pico de memoria se paga caro (cap. 17), y de paso dejás de mirar la
     * página de error real porque todo 404 se ve igual de apocalyptic.
     *
     * Este test corre con APP_DEBUG=true (lo que hay en .env), o sea que
     * Whoops está construido: verifica que la rama correcta sea la elegida.
     */
    public function test_un_404_no_gasta_la_pagina_de_whoops(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );

        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();

        self::assertStringNotContainsString('Whoops', $html);
        self::assertStringContainsString('Not found.', $html);
        self::assertLessThan(4096, strlen($html), 'El 404 deberia pesar bytes, no cientos de KB.');
    }
}
