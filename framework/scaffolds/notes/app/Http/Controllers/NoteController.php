<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use Framework\Http\Controller;
use Framework\Http\Flash;
use Framework\Http\Pagination;
use Framework\Http\Url;
use Framework\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

/**
 * Controlador de Notas: las cinco acciones de la entidad y las dos utilidades
 * que comparten.
 *
 * Lo llama Slim desde `routes/notes.php`, ya con sesión, CSRF y login resueltos.
 * Usa `Note`, `Validator` y `Pagination`; responde por el `Controller` base.
 *
 * Para tu entidad: copia el archivo, cambia el modelo y cierra cada mutación
 * con `redirect()`.
 */
final class NoteController extends Controller
{
    // Utilidades primero: no son acciones de ruta y las consumen las de abajo.
    // Las acciones siguen el recorrido —listado, alta, edición, guardado y
    // borrado— y cada mutación vuelve con `redirect()`.

    /**
     * Datos del listado: las notas de la página pedida, la metadata del
     * paginador y la miga de pan.
     *
     * La comparten las cuatro acciones que renderizan la pantalla (`index`,
     * `edit` y los rechazos de `store` y `update`), así el recorte de página y la
     * miga no se repiten en cada una.
     *
     * @return array<string, mixed>
     */
    private function pageData(Request $request): array
    {
        $pagination = Pagination::fromRequest(
            Note::orderByDesc('id'),
            $request,
            Url::path('/notes')
        );

        return [
            'notes'      => $pagination['items'],
            'pagination' => $pagination,
            // La miga la pinta el header del shell: Inicio como enlace y Notes
            // como página actual. Sin ella, el header cae en su fallback y
            // muestra «Inicio» como si estuvieras en la portada.
            'breadcrumbs' => $this->breadcrumbs('Notes', 'list'),
        ];
    }

    /**
     * Render único de la pantalla de listado: lo usan las cuatro acciones que la
     * muestran.
     *
     * Con `HX-Request` devuelve sólo el panel, porque la página entera anidaría
     * un segundo shell. El `HX-Push-Url` sale sólo en 2xx: un 422 no es una
     * página que el usuario haya navegado.
     *
     * @param array<string, mixed> $extra  datos del llamador: errores, lo tipeado, el modal a abrir
     * @param int                  $status estado HTTP de la respuesta; 422 en un rechazo
     */
    private function renderList(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $data = $this->pageData($request);

        $template = $request->hasHeader('HX-Request') ? 'pages/notes/_panel.twig' : 'pages/notes/index.twig';

        $rendered = $this->render($request, $response, $template, $data + $extra + [
            'active' => 'notes',
        ])->withStatus($status);

        if ($status >= 400) {
            return $rendered;
        }

        return $rendered->withHeader('HX-Push-Url', Url::path('/notes')
            . '?page=' . $data['pagination']['current_page']
            . '&per_page=' . $data['pagination']['per_page']);
    }

    /* Acciones de ruta: las invoca Slim, una por cada ruta de `routes/notes.php`. */

    /** `GET /notes`: el listado. Delega en `renderList()`; el endpoint no arma datos. */
    public function index(Request $request, Response $response): Response
    {
        return $this->renderList($request, $response);
    }

    /**
     * `POST /notes`: el alta.
     *
     * Valida `title` y `body`; si pasa, guarda y redirige al listado con el
     * aviso, y si falla devuelve 422 con el modal de alta abierto y lo tipeado.
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $data['body'] = trim((string) ($data['body'] ?? ''));

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
            'body'  => 'max:2000',
        ]);

        if ($validator->fails()) {
            return $this->renderList($request, $response, [
                'errors'            => $validator->errors(),
                'form_values'       => $data,
                'open_create_modal' => true,
            ], 422);
        }

        Note::create([
            'title' => (string) $data['title'],
            'body'  => $data['body'] !== '' ? $data['body'] : null,
        ]);

        Flash::set('success', 'Note created.');

        return $this->redirect($response, '/notes');
    }

    /**
     * `GET /notes/{id}/edit`: el formulario de edición.
     *
     * No abre una pantalla aparte: reusa el listado y muestra el modal de
     * edición con la nota cargada. Un id que no existe es un 404.
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $note = Note::find((int) $args['id']);

        if ($note === null) {
            throw new HttpNotFoundException($request);
        }

        return $this->renderList($request, $response, [
            'note'            => $note,
            'open_edit_modal' => true,
        ]);
    }

    /**
     * `POST /notes/{id}`: guarda la edición.
     *
     * Valida igual que el alta; si falla devuelve 422 con el modal de edición
     * abierto, y si pasa guarda y redirige al listado.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $note = Note::find((int) $args['id']);

        if ($note === null) {
            throw new HttpNotFoundException($request);
        }

        $data = (array) $request->getParsedBody();
        $data['body'] = trim((string) ($data['body'] ?? ''));

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
            'body'  => 'max:2000',
        ]);

        if ($validator->fails()) {
            // `note` no es opcional: `index.twig` envuelve el modal de edición en
            // `{% if note is defined %}`, así que sin la clave no se renderiza y lo
            // tipeado termina en el modal de alta.
            return $this->renderList($request, $response, [
                'errors'          => $validator->errors(),
                'form_values'     => $data,
                'note'            => $note,
                'open_edit_modal' => true,
            ], 422);
        }

        $note->fill([
            'title' => (string) $data['title'],
            'body'  => $data['body'] !== '' ? $data['body'] : null,
        ])->save();

        Flash::set('success', 'Note updated.');

        return $this->redirect($response, '/notes');
    }

    /** `POST /notes/{id}/delete`: borra la nota y vuelve al listado con el aviso. */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $note = Note::find((int) $args['id']);

        if ($note === null) {
            throw new HttpNotFoundException($request);
        }

        $note->delete();

        Flash::set('success', 'Note deleted.');

        return $this->redirect($response, '/notes');
    }
}
