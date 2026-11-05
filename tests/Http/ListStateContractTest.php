<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\AppFactory;

/**
 * Contrato de estado de la lista: toda URL que dispara una mutacion lleva la
 * posicion actual (page + per_page) en el query string, la paginacion es un
 * enlace real (href) y las peticiones htmx deshabilitan su disparador mientras
 * estan en vuelo.
 *
 * Son tres reglas distintas y comparten el mismo fixture: 12 filas alcanzan
 * para que exista una pagina 2 (con las 12 en una sola pagina, las reglas de
 * navegacion no se pueden observar).
 *
 * Los ids no se hardcodean: la PK sigue incrementando entre tests, asi que se
 * afirma sobre la forma de la URL, no sobre un id concreto.
 */
final class ListStateContractTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION['user_id'] = User::create([
            'email'         => 'contrato@ejemplo.com',
            'password_hash' => password_hash('secreto123', PASSWORD_DEFAULT),
        ])->id;

        for ($i = 1; $i <= 12; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user_id']);
        User::query()->delete();
        Task::query()->delete();
    }

    /**
     * P1-1: togglear, editar, borrar o cancelar desde la pagina 2 tiene que
     * volver a la pagina 2. Sin esto la respuesta se renderiza en la pagina 1:
     * el usuario pierde la fila que toco y la URL deja de describir el
     * contenido que esta viendo.
     */
    public function test_las_acciones_de_fila_conservan_la_pagina_y_el_tamano(): void
    {
        $html = self::page('/tareas?page=2&per_page=10');

        $posicion = 'page=2&(?:amp;)?per_page=10';

        self::assertMatchesRegularExpression(
            '#hx-post="/tareas/\d+/toggle\?' . $posicion . '"#',
            $html,
            'el toggle tiene que arrastrar la posicion actual'
        );
        self::assertMatchesRegularExpression(
            '#hx-get="/tareas/\d+/edit\?' . $posicion . '"#',
            $html,
            'editar tiene que arrastrar la posicion actual'
        );
        self::assertMatchesRegularExpression(
            '#hx-delete="/tareas/\d+\?' . $posicion . '"#',
            $html,
            'borrar tiene que arrastrar la posicion actual'
        );
    }

    /**
     * P1-1 (cierre): guardar la edicion y cancelar son las otras dos acciones
     * de fila, y solo existen con una fila en modo formulario. Se llega por la
     * ruta de edicion, que repinta el panel con esa fila abierta; ahi las dos
     * URLs aparecen. Guardar o cancelar desde la pagina 2 sin arrastrar la
     * posicion devuelve al usuario a la pagina 1 igual que las demas acciones.
     */
    public function test_guardar_y_cancelar_la_edicion_conservan_la_pagina_y_el_tamano(): void
    {
        $task = Task::orderBy('id')->first();
        self::assertNotNull($task, 'el fixture tiene que haber creado tareas');

        // La tarea mas vieja cae en la pagina 2 con el orden desc por id.
        $html = self::page(sprintf('/tareas/%d/edit?page=2&per_page=10', $task->id));

        $posicion = 'page=2&(?:amp;)?per_page=10';

        self::assertMatchesRegularExpression(
            '#hx-put="/tareas/' . $task->id . '\?' . $posicion . '"#',
            $html,
            'guardar la edicion tiene que conservar la posicion'
        );
        self::assertMatchesRegularExpression(
            '#hx-get="/tareas\?' . $posicion . '"#',
            $html,
            'cancelar tiene que volver a la pagina desde la que se edito'
        );
    }

    /**
     * P1-2: la paginacion es navegacion, no solo htmx. Un <a> sin href no
     * tiene rol de enlace ni entra en el orden de tabulacion: con teclado o
     * lector de pantalla las paginas 2+ son inalcanzables. El href tiene que
     * ser la misma URL que ya se le pasa a hx-get.
     */
    public function test_la_paginacion_es_un_enlace_real_con_href(): void
    {
        $html = self::page('/tareas?page=2&per_page=10');

        self::assertMatchesRegularExpression(
            '#<a[^>]*href="/tareas\?page=1&(?:amp;)?per_page=10"[^>]*>#',
            $html,
            'el enlace a la pagina anterior tiene que tener href'
        );
        self::assertMatchesRegularExpression(
            '#<a[^>]*href="/tareas\?page=1&(?:amp;)?per_page=10"[^>]*hx-get="#',
            $html,
            'el href y el hx-get tienen que convivir en el mismo enlace'
        );
    }

    /**
     * P1-3: htmx deshabilita su disparador mientras la peticion esta en vuelo
     * cuando existe hx-disabled-elt. Sin eso, dos clicks en "Agregar" emiten
     * dos POST y crean dos tareas. El atributo se declara en el formulario de
     * alta y en el panel (hereda a los botones de fila).
     */
    public function test_las_peticiones_deshabilitan_su_disparador_mientras_estan_en_vuelo(): void
    {
        $html = self::page('/tareas?page=2&per_page=10');

        self::assertMatchesRegularExpression(
            '#<form[^>]*id="alta-tarea"[^>]*hx-disabled-elt="[^"]+"#',
            $html,
            'el alta tiene que deshabilitar su submit mientras esta en vuelo'
        );
        self::assertMatchesRegularExpression(
            '#<div[^>]*id="tareas-panel"[^>]*hx-disabled-elt="[^"]+"#',
            $html,
            'el panel tiene que deshabilitar los disparadores de sus filas'
        );
    }

    private static function page(string $path): string
    {
        return (string) self::app()->handle(self::request('GET', $path))->getBody();
    }

    private static function app(): App
    {
        return AppFactory::make();
    }

    private static function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())
            ->createServerRequest($method, 'http://localhost' . $path);
    }
}
