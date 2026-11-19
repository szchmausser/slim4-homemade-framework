<?php

declare(strict_types=1);

namespace Tests\Notas\Http;

use App\Notas\Models\Nota;
use Tests\Support\HttpTestCase;

/**
 * Tests HTTP del demo Notas: alta, edición, borrado, rechazos y paginación por
 * región.
 *
 * Cada test pasa por la app completa (`HttpTestCase` arma el request) y comprueba
 * lo que ve el usuario —código, `Location` y HTML— más el efecto en la base.
 *
 * Para tu entidad: copia el archivo, cambia rutas, campos y avisos.
 */
final class NotaTest extends HttpTestCase
{
    protected function setUp(): void
    {
        // Cada test arranca con sesión y con la tabla vacía: los conteos de
        // abajo valen sin depender del orden de ejecución.
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
        // `$datos` va primero: si el default quedara a la izquierda, el `+` de
        // PHP descartaría el título que pasa cada test y todas las filas se
        // llamarían «De prueba».
        return Nota::create($datos + ['titulo' => 'De prueba']);
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
        self::assertStringContainsString('aria-label="Miga de pan"', (string) $lista->getBody());
        self::assertStringContainsString('aria-current="page">Notas', (string) $lista->getBody());
    }

    public function test_el_aviso_de_exito_llega_al_host_de_toasts_por_defecto(): void
    {
        // No hace falta declarar nada: el default de `Controller` manda el flash
        // como toast del shell, que es lo que el layout pinta en una página
        // completa.
        $token = self::token('/notas');

        self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => 'Con aviso',
            'cuerpo'     => '',
        ]));

        $html = (string) self::app()->handle(self::request('GET', '/notas'))->getBody();

        self::assertStringContainsString('toast-host', $html);
        self::assertStringContainsString('Nota creada.', $html);
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

        // El alta comparte `_form.twig` con la edición, así que en la página de
        // edición no tiene que heredar la nota que se está editando.
        $paginaEdicion = (string) self::app()->handle(
            self::request('GET', '/notas/' . $nota->id . '/edit')
        )->getBody();
        self::assertStringContainsString('aria-label="Miga de pan"', $paginaEdicion);
        self::assertStringContainsString('aria-current="page">Notas', $paginaEdicion);
        self::assertStringNotContainsString(
            'value="Antes"',
            self::dialogHtml($paginaEdicion, 'nota-create-modal'),
            'El alta no tiene que heredar la nota que se está editando.'
        );

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

    public function test_el_422_no_empuja_la_url(): void
    {
        $token = self::token('/notas');

        $response = self::app()->handle(self::request('POST', '/notas', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'titulo'     => '',
            'cuerpo'     => '',
        ]));

        self::assertSame(422, $response->getStatusCode());
        // El rechazo no es una navegación: `renderList()` sólo emite
        // `HX-Push-Url` en 2xx, así que la URL se queda donde estaba.
        self::assertSame('', $response->getHeaderLine('HX-Push-Url'));
    }

    public function test_sin_token_csrf_redirige_al_login_y_no_crea_nada(): void
    {
        // El CSRF es global: sin token, el Guard corta antes del controlador y
        // manda al login. Por eso no se crea ninguna fila.
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
        // Región, no página entera: la respuesta no trae shell.
        self::assertStringNotContainsString('<aside', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertSame(5, substr_count($html, 'rounded-box border border-base-300'));
    }

    public function test_los_formularios_deshabilitan_el_boton_para_no_duplicar(): void
    {
        $nota = $this->crear(['titulo' => 'Con guarda']);
        $html = (string) self::app()->handle(self::request('GET', '/notas'))->getBody();
        $guarda = "this.querySelector('[type=submit]').disabled = true";

        // El HTML entero también trae la guarda en cada modal de borrado. Si se
        // busca ahí, el test pasa aunque el alta y la edición la hayan perdido.
        self::assertStringContainsString(
            $guarda,
            self::dialogHtml($html, 'nota-create-modal'),
            'Sin esta guarda, un doble clic en un POST plano crea dos filas.'
        );

        $edicion = (string) self::app()->handle(
            self::request('GET', '/notas/' . $nota->id . '/edit')
        )->getBody();
        self::assertStringContainsString(
            $guarda,
            self::dialogHtml($edicion, 'nota-edit-modal'),
            'La guarda también tiene que estar en el formulario de edición.'
        );
    }

    public function test_la_portada_lista_el_modulo_con_su_estado(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        // El catálogo sale de los scaffolds, así que están los tres aunque solo
        // este esté instalado.
        self::assertSame(3, substr_count($html, 'Demo de '), 'La portada lista los tres módulos.');
        self::assertStringContainsString('Demo de notas', $html);
        self::assertStringContainsString('composer demo:notas:remove', $html, 'Instalado: ofrece desinstalar.');
    }

    public function test_el_menu_muestra_la_entrada_de_notas_y_la_marca_activa(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/notas'))->getBody();
        $item = self::navItemHtml($html, 'Notas');

        self::assertStringContainsString('href="/notas"', $item, 'La entrada del menú tiene que apuntar a /notas.');
        self::assertStringContainsString('aria-current="page"', $item, 'En /notas, su entrada va marcada como activa.');

        // La entrada vive en config/navigation/notas.php, que instala y quita
        // el demo: no hay ninguna condición en la plantilla del sidebar.
        $sinNav = (string) self::app()->handle(self::request('GET', '/'))->getBody();
        self::assertStringContainsString('aria-label="Inicio"', $sinNav, 'Inicio es fijo.');
    }

    private static function navItemHtml(string $html, string $label): string
    {
        $anchorEnd = strpos($html, 'aria-label="' . $label . '"');
        self::assertNotFalse($anchorEnd, 'No se encontró la entrada de menú ' . $label);

        $start = (int) strrpos(substr($html, 0, $anchorEnd), '<a ');
        $end = strpos($html, '</a>', $anchorEnd);
        self::assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    private static function dialogHtml(string $html, string $id): string
    {
        $start = strpos($html, 'id="' . $id . '"');
        self::assertNotFalse($start, 'No se encontró el modal ' . $id);
        $end = strpos($html, '</dialog>', $start);
        self::assertNotFalse($end, 'No se cerró el modal ' . $id);

        return substr($html, $start, $end - $start);
    }
}
