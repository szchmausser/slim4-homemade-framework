<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * El router, y sobre todo la ruta raiz.
 *
 * El segundo test existe por un bug concreto: `errors/error.twig` cierra con
 * `<a href="/">Volver al inicio</a>`, y no había ninguna ruta en `/`. El botón
 * de la página de error era, él mismo, un link muerto. Ningún test lo detectó
 * porque todos asserteaban el status de `/no-existe` y nadie seguía el link.
 */
final class RoutingTest extends TestCase
{
    public function test_la_raiz_responde_y_no_un_404(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/')
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_la_raiz_presenta_el_stack_y_enlaza_a_tareas(): void
    {
        $response = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/')
        );

        $response->getBody()->rewind();
        $html = $response->getBody()->getContents();

        self::assertStringContainsString('Slim 4', $html, 'La raiz deberia mostrar el stack.');
        self::assertStringContainsString('href="/tareas"', $html, 'La raiz deberia enlazar al listado.');
    }

    public function test_el_boton_de_la_pagina_de_error_no_es_un_link_muerto(): void
    {
        // Si este test falla, volvio el bug: la pagina de error enlaza a una
        // ruta que no existe y el usuario queda atrapado en un 404.
        $app = AppFactory::make();

        $error = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/no-existe')
        );
        $error->getBody()->rewind();
        $html = $error->getBody()->getContents();

        self::assertStringContainsString('href="/"', $html, 'La pagina de error deberia ofrecer volver al inicio.');

        $target = AppFactory::make()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'http://localhost/')
        );

        // assertSame(200), no assertNotSame(404): un 500 también pasa
        // "distinto de 404" y dejaba el link muerto cubierto por un test verde.
        self::assertSame(200, $target->getStatusCode(), 'El destino del link de error tiene que responder.');
    }
}
