<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Tasks\Http\TasksTestCase;

/**
 * Estado de la lista: toda URL que dispara una mutación lleva la posición actual
 * (`page` y `per_page`), la paginación es un enlace real y cada request deshabilita
 * su disparador mientras está en vuelo.
 *
 * Capa tests. Son tres reglas con el mismo fixture: 12 filas alcanzan para que exista
 * una página 2; los ids no se hardcodean, las aserciones van sobre la forma de la URL.
 *
 * Para tu entidad, copia el archivo y cambia la ruta.
 */
final class ListStateContractTest extends TasksTestCase
{
    protected function setUp(): void
    {
        self::loginAs();

        for ($i = 1; $i <= 12; $i++) {
            Task::create(['title' => sprintf('Tarea %02d', $i)]);
        }
    }

    /**
     * Si una acción de fila no arrastra la posición, la respuesta se renderiza en la
     * página 1: el usuario pierde la fila que tocó y la URL deja de describir el
     * contenido que está viendo.
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
     * Guardar y cancelar la edición son las otras dos acciones de fila, y sólo existen
     * con una fila en modo formulario: se llega por la ruta de edición, que repinta el
     * panel con esa fila abierta. Sin la posición, volverían a la página 1.
     */
    public function test_guardar_y_cancelar_la_edicion_conservan_la_pagina_y_el_tamano(): void
    {
        $task = Task::orderBy('id')->first();
        self::assertNotNull($task, 'el fixture tiene que haber creado tareas');

        // La tarea más vieja cae en la página 2 con el orden desc por id.
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
     * Un `<a>` sin href no tiene rol de enlace ni entra en el orden de tabulación: con
     * teclado o lector de pantalla las páginas 2+ serían inalcanzables. El href tiene
     * que ser la misma URL que ya recibe el `hx-get`.
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
     * htmx deshabilita su disparador mientras hay un request en vuelo cuando existe
     * `hx-disabled-elt`. Sin eso, dos clicks en «Agregar» emiten dos POST y crean dos
     * tareas; el atributo se declara en el alta y en el panel, que lo hereda a sus filas.
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
     * Una acción de fila deja una entrada de historial cuya URL describe lo que quedó
     * en pantalla. La posición no puede vivir en la plantilla: `Pagination` recorta la
     * página pedida, así que el server publica en `HX-Push-Url` la que renderizó.
     *
     * La URL empujada es la canónica de la vista y no la de la acción:
     * `/tareas/5/toggle` sólo acepta POST y recargarla sería un 404.
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
     * La URL empujada no puede describir una página que ya no existe: con 12 filas y
     * 10 por página, el primer borrado de la página 2 la deja viva (quedan 11) y el
     * segundo la vacía, así que el server renderiza la 1.
     */
    public function test_borrar_la_ultima_fila_de_la_ultima_pagina_empuja_la_pagina_clampeada(): void
    {
        // Orden desc por id: las dos más viejas son las de la página 2.
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
     * El alta no manda la posición en el cuerpo, así que el tamaño de página viaja en
     * la URL del form. La página sí es 1: la tarea nueva queda arriba.
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
     * Sin htmx no hay swap que empujar: la respuesta es la página entera y la barra de
     * direcciones la maneja el navegador.
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
