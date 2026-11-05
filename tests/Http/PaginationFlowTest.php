<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use Tests\Support\HttpTestCase;

/**
 * Paginación de punta a punta: 12 filas, slices, tamaño y contadores.
 * Con pocas filas todo cae en la página 1 y estos tests serían verdes sin
 * paginar nada: por eso se siembran 12.
 */
final class PaginationFlowTest extends HttpTestCase
{
    protected function setUp(): void
    {
        // /tareas exige login (RequireAuth).
        self::loginAs();

        for ($i = 1; $i <= 12; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
    }

    public function test_pagina_dos_trae_las_mas_viejas_y_contadores(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?page=2'));

        self::assertSame(200, $response->getStatusCode());

        $html = (string) $response->getBody();

        // Orden desc por id: la 1 y la 2 caen en la página 2, la 12 queda en la 1.
        self::assertStringContainsString('Tarea 01', $html);
        self::assertStringNotContainsString('Tarea 12', $html);
        self::assertStringContainsString('Mostrando 11 al 12 de 12 tareas', $html);
    }

    public function test_per_page_grande_mete_todo_en_una(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?per_page=25'));

        $html = (string) $response->getBody();

        self::assertStringContainsString('Tarea 01', $html);
        self::assertStringContainsString('Tarea 12', $html);
        self::assertStringContainsString('Mostrando 1 al 12 de 12 tareas', $html);
    }

    public function test_per_page_invalido_cae_al_default(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?per_page=999&page=2'));

        $html = (string) $response->getBody();

        self::assertStringContainsString('Mostrando 11 al 12 de 12 tareas', $html);
    }

    public function test_los_links_apuntan_al_panel_y_conservan_el_tamano(): void
    {
        $response = self::app()->handle(self::request('GET', '/tareas?page=2&per_page=10'));

        $html = (string) $response->getBody();

        self::assertStringContainsString('hx-get="/tareas?page=1&per_page=10"', $html);
        self::assertStringContainsString('hx-target="#tareas-panel"', $html);
    }
}
