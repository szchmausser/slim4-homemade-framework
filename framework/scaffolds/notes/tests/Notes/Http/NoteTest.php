<?php

declare(strict_types=1);

namespace Tests\Notes\Http;

use App\Models\Note;
use Tests\Support\HttpTestCase;

/**
 * Tests HTTP del demo Notes: alta, edición, borrado, rechazos y paginación por
 * región.
 *
 * Cada test pasa por la app completa (`HttpTestCase` arma el request) y comprueba
 * lo que ve el usuario —código, `Location` y HTML— más el efecto en la base.
 *
 * Para tu entidad: copia el archivo, cambia rutas, campos y avisos.
 */
final class NoteTest extends HttpTestCase
{
    protected function setUp(): void
    {
        // Cada test arranca con sesión y con la tabla vacía: los conteos de
        // abajo valen sin depender del orden de ejecución.
        self::loginAs();
        Note::query()->delete();
    }

    protected function tearDown(): void
    {
        Note::query()->delete();
        parent::tearDown();
    }

    private function createNote(array $data = []): Note
    {
        // `$data` va primero: si el default quedara a la izquierda, el `+` de
        // PHP descartaría el título que pasa cada test y todas las filas se
        // llamarían «Sample».
        return Note::create($data + ['title' => 'Sample']);
    }

    public function test_crea_una_nota_y_la_muestra_en_la_lista(): void
    {
        $token = self::token('/notes');

        $response = self::app()->handle(self::request('POST', '/notes', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => 'First note',
            'body'       => 'Content',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/notes', $response->getHeaderLine('Location'));
        self::assertSame(1, Note::query()->count());

        $list = self::app()->handle(self::request('GET', '/notes'));
        self::assertStringContainsString('First note', (string) $list->getBody());
        self::assertStringContainsString('aria-label="Miga de pan"', (string) $list->getBody());
        self::assertStringContainsString('aria-current="page">Notes', (string) $list->getBody());
    }

    public function test_el_aviso_de_exito_llega_al_host_de_toasts_por_defecto(): void
    {
        // No hace falta declarar nada: el default de `Controller` manda el flash
        // como toast del shell, que es lo que el layout pinta en una página
        // completa.
        $token = self::token('/notes');

        self::app()->handle(self::request('POST', '/notes', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => 'With flash',
            'body'       => '',
        ]));

        $html = (string) self::app()->handle(self::request('GET', '/notes'))->getBody();

        self::assertStringContainsString('toast-host', $html);
        self::assertStringContainsString('Note created.', $html);
    }

    public function test_el_alta_invalida_reabre_el_modal_con_lo_tipeado(): void
    {
        $token = self::token('/notes');

        $response = self::app()->handle(self::request('POST', '/notes', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => '',
            'body'       => 'Text I want to keep',
        ]));
        $html = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, Note::query()->count());
        // El mensaje es de `Framework\Validation\Validator`, compartido con la
        // app: el validador no recibe mensajes del demo.
        self::assertStringContainsString('Este campo es obligatorio.', $html);
        self::assertStringContainsString('Text I want to keep', $html);
        self::assertMatchesRegularExpression('/note-create-modal[^>]*open/', $html);
    }

    public function test_edita_una_nota(): void
    {
        $note = $this->createNote(['title' => 'Before']);
        $token = self::token('/notes');

        // El alta comparte `_form.twig` con la edición, así que en la página de
        // edición no tiene que heredar la nota que se está editando.
        $editPage = (string) self::app()->handle(
            self::request('GET', '/notes/' . $note->id . '/edit')
        )->getBody();
        self::assertStringContainsString('aria-label="Miga de pan"', $editPage);
        self::assertStringContainsString('aria-current="page">Notes', $editPage);
        self::assertStringNotContainsString(
            'value="Before"',
            self::dialogHtml($editPage, 'note-create-modal'),
            'El alta no tiene que heredar la nota que se está editando.'
        );

        $response = self::app()->handle(self::request('POST', '/notes/' . $note->id, [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => 'After',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('After', (string) Note::find($note->id)?->title);
    }

    public function test_el_rechazo_de_la_edicion_reabre_su_modal_y_no_el_del_alta(): void
    {
        $note = $this->createNote(['title' => 'Current']);
        $token = self::token('/notes');

        $response = self::app()->handle(self::request('POST', '/notes/' . $note->id, [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => '',
        ]));
        $html = (string) $response->getBody();

        self::assertSame(422, $response->getStatusCode());
        self::assertMatchesRegularExpression('/note-edit-modal[^>]*open/', $html);
        self::assertDoesNotMatchRegularExpression('/note-create-modal[^>]*open/', $html);
    }

    public function test_borra_una_nota_con_confirmacion(): void
    {
        $note = $this->createNote();
        $token = self::token('/notes');

        $response = self::app()->handle(self::request('POST', '/notes/' . $note->id . '/delete', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertNull(Note::find($note->id));
    }

    public function test_el_422_no_empuja_la_url(): void
    {
        $token = self::token('/notes');

        $response = self::app()->handle(self::request('POST', '/notes', [
            'csrf_name'  => $token['csrf_name'],
            'csrf_value' => $token['csrf_value'],
            'title'      => '',
            'body'       => '',
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
        $response = self::app()->handle(self::request('POST', '/notes', [
            'title' => 'No token',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertSame(0, Note::query()->count());
    }

    public function test_pagina_por_region_y_expone_la_url_de_la_pagina_renderizada(): void
    {
        foreach (range(1, 12) as $i) {
            $this->createNote(['title' => 'Note ' . $i]);
        }

        $response = self::app()->handle(self::request(
            'GET',
            '/notes?page=2&per_page=5',
            [],
            ['HX-Request' => 'true']
        ));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/notes?page=2&per_page=5', $response->getHeaderLine('HX-Push-Url'));
        // Región, no página entera: la respuesta no trae shell.
        self::assertStringNotContainsString('<aside', $html);
        self::assertStringNotContainsString('<header', $html);
        self::assertSame(5, substr_count($html, 'rounded-box border border-base-300'));
    }

    public function test_los_formularios_deshabilitan_el_boton_para_no_duplicar(): void
    {
        $note = $this->createNote(['title' => 'With guard']);
        $html = (string) self::app()->handle(self::request('GET', '/notes'))->getBody();
        $guard = "this.querySelector('[type=submit]').disabled = true";

        // El HTML entero también trae la guarda en cada modal de borrado. Si se
        // busca ahí, el test pasa aunque el alta y la edición la hayan perdido.
        self::assertStringContainsString(
            $guard,
            self::dialogHtml($html, 'note-create-modal'),
            'Sin esta guarda, un doble clic en un POST plano crea dos filas.'
        );

        $edit = (string) self::app()->handle(
            self::request('GET', '/notes/' . $note->id . '/edit')
        )->getBody();
        self::assertStringContainsString(
            $guard,
            self::dialogHtml($edit, 'note-edit-modal'),
            'La guarda también tiene que estar en el formulario de edición.'
        );
    }

    public function test_la_portada_lista_el_modulo_con_su_estado(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/'))->getBody();

        // El catálogo sale de los scaffolds, así que están los tres aunque solo
        // este esté instalado.
        self::assertSame(3, substr_count($html, 'Demo de '), 'La portada lista los tres módulos.');
        self::assertStringContainsString('Demo de notes', $html);
        self::assertStringContainsString('composer demo:notes:remove', $html, 'Instalado: ofrece desinstalar.');
    }

    public function test_el_menu_muestra_la_entrada_de_notas_y_la_marca_activa(): void
    {
        $html = (string) self::app()->handle(self::request('GET', '/notes'))->getBody();
        $item = self::navItemHtml($html, 'Notes');

        self::assertStringContainsString('href="/notes"', $item, 'La entrada del menú tiene que apuntar a /notes.');
        self::assertStringContainsString('aria-current="page"', $item, 'En /notes, su entrada va marcada como activa.');

        // La entrada vive en config/navigation/notes.php, que instala y quita
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
