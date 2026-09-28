<?php

declare(strict_types=1);

namespace Tests\Http;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * Qué recibe HTMX cuando pide `/tareas` con `HX-Request`.
 *
 * El botón "Cancelar" apunta a `/tareas` con `hx-target="#tareas-panel"` y
 * `hx-swap="outerHTML"`. Si el servidor devuelve `index.twig` (que extiende
 * el layout), HTMX recibe un documento completo, se queda con el `<body>` y lo
 * inserta dentro del panel: quedan dos `<h1>`, dos `id="alta-tarea"` y el
 * padding del layout dos veces.
 */
final class PartialRenderTest extends TestCase
{
    private static function app(): App
    {
        return AppFactory::make();
    }

    public function test_get_sin_hx_request_devuelve_la_pagina_completa(): void
    {
        $response = self::app()->handle(
            self::request(false)
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('id="alta-tarea"', $html);
    }

    public function test_get_con_hx_request_devuelve_solo_el_panel(): void
    {
        $response = self::app()->handle(
            self::request(true)
        );

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        self::assertStringNotContainsString('<!DOCTYPE html>', $html, 'Un <html> completo dentro del panel duplica la página.');
        self::assertStringContainsString('id="tareas-panel"', $html);
    }

    private static function request(bool $htmx): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost/tareas');

        return $htmx
            ? $request->withHeader('HX-Request', 'true')
            : $request;
    }
}
