<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Models\Task;
use Tests\Support\HttpTestCase;

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
final class ListStateContractTest extends HttpTestCase
{
    protected function setUp(): void
    {
        self::loginAs();

        for ($i = 1; $i <= 12; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
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

    /**
     * S11 (T10): una accion de fila deja una entrada de historial cuya URL
     * describe lo que quedo en pantalla. La posicion no puede vivir en la
     * plantilla (hx-push-url fijo) porque Pagination clampea la pagina pedida:
     * el server es el unico que sabe que pagina renderizo, asi que la publica
     * en el header `HX-Push-Url`.
     *
     * La URL empujada es la canonica de la vista, no la de la accion:
     * /tareas/5/toggle solo acepta POST y recargarla seria un 404.
     */
    public function test_una_accion_de_fila_empuja_la_url_de_la_pagina_renderizada(): void
    {
        $task = Task::orderByDesc('id')->first();
        self::assertNotNull($task, 'el fixture tiene que haber creado tareas');

        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', sprintf('/tareas/%d/toggle?page=2&per_page=10', $task->id))
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            '/tareas?page=2&per_page=10',
            $response->getHeaderLine('HX-Push-Url'),
            'la accion tiene que empujar la posicion que el server renderizo'
        );
    }

    /**
     * S11 (T10) + el clamp de Pagination: la URL empujada no puede describir
     * una pagina que ya no existe. Con 12 filas y per_page=10 el borrado de la
     * primera tarea de la pagina 2 deja 11 (la pagina 2 sigue viva), pero el
     * segundo borrado deja 10 y el server renderiza la pagina 1: la barra de
     * direcciones tiene que decir pagina 1, no la 2 que se pidio.
     */
    public function test_borrar_la_ultima_fila_de_la_ultima_pagina_empuja_la_pagina_clampeada(): void
    {
        // Orden desc por id: las dos mas viejas son las de la pagina 2.
        $viejas = Task::orderBy('id')->limit(2)->get();
        self::assertCount(2, $viejas, 'el fixture tiene que haber creado 12 tareas');

        // El Guard corre en persistentTokenMode: un solo par sirve para las dos
        // peticiones del test.
        $token = self::token();

        $sigue = self::app()->handle(
            self::request('DELETE', sprintf('/tareas/%d?page=2&per_page=10', $viejas[0]->id))
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $sigue->getStatusCode());
        self::assertSame(
            '/tareas?page=2&per_page=10',
            $sigue->getHeaderLine('HX-Push-Url'),
            'quedan 11 filas: la pagina 2 existe y se renderiza'
        );

        $vacia = self::app()->handle(
            self::request('DELETE', sprintf('/tareas/%d?page=2&per_page=10', $viejas[1]->id))
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
        );

        self::assertSame(200, $vacia->getStatusCode());
        self::assertSame(
            '/tareas?page=1&per_page=10',
            $vacia->getHeaderLine('HX-Push-Url'),
            'quedan 10 filas: la pagina 2 desaparecio y el server renderizo la 1'
        );
    }

    /**
     * S11 (T10): el alta no lleva posicion en el cuerpo, asi que el tamano de
     * pagina tiene que viajar en la URL del form. Sin eso, crear desde
     * per_page=25 devuelve la lista en 10 y el usuario pierde la eleccion sin
     * haberla cambiado. La pagina si es 1: la tarea nueva queda arriba.
     */
    public function test_el_alta_conserva_el_tamano_de_pagina_y_empuja_la_pagina_uno(): void
    {
        $html = self::page('/tareas?page=1&per_page=25');

        self::assertStringContainsString(
            'hx-post="/tareas?page=1&per_page=25"',
            $html,
            'el form de alta tiene que arrastrar el tamano de pagina elegido'
        );

        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas?page=1&per_page=25')
                ->withHeader('HX-Request', 'true')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
                ->withParsedBody(['title' => 'Creada desde la pagina 25'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            '/tareas?page=1&per_page=25',
            $response->getHeaderLine('HX-Push-Url'),
            'crear tiene que empujar la pagina 1 con el tamano elegido'
        );

        // Y el panel tiene que haber salido con ese tamano: 13 filas entran en
        // una sola pagina de 25, en una de 10 serian dos.
        self::assertStringContainsString(
            'Mostrando 1 al 13 de 13 tareas',
            (string) $response->getBody(),
            'el panel se renderizo con otro tamano de pagina'
        );
    }

    /**
     * S11 (T10): sin htmx no hay swap que empujar. La respuesta es la pagina
     * entera y la barra de direcciones la maneja el navegador: un
     * HX-Push-Url aca seria un header que nadie escucha.
     */
    public function test_una_mutacion_sin_htmx_no_empuja_ninguna_url(): void
    {
        $token = self::token();

        $response = self::app()->handle(
            self::request('POST', '/tareas?page=1&per_page=25')
                ->withHeader('csrf_name', $token['csrf_name'])
                ->withHeader('csrf_value', $token['csrf_value'])
                ->withParsedBody(['title' => 'Alta sin htmx'])
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse(
            $response->hasHeader('HX-Push-Url'),
            'una respuesta completa no puede empujar URLs de htmx'
        );
    }

    private static function page(string $path): string
    {
        return (string) self::app()->handle(self::request('GET', $path))->getBody();
    }
}
