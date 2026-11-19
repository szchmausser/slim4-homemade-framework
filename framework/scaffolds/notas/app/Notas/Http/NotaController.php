<?php

declare(strict_types=1);

namespace App\Notas\Http;

use App\Notas\Models\Nota;
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
 * Lo llama Slim desde `routes/notas.php`, ya con sesión, CSRF y login resueltos.
 * Usa `Nota`, `Validator` y `Pagination`; responde por el `Controller` base.
 *
 * Para tu entidad: copia el archivo, cambia el modelo y cierra cada mutación
 * con `redirect()`.
 */
final class NotaController extends Controller
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
        $paginacion = Pagination::fromRequest(
            Nota::orderByDesc('id'),
            $request,
            Url::path('/notas')
        );

        return [
            'notas'      => $paginacion['items'],
            'pagination' => $paginacion,
            // La miga la pinta el header del shell: Inicio como enlace y Notas
            // como página actual. Sin ella, el header cae en su fallback y
            // muestra «Inicio» como si estuvieras en la portada.
            'breadcrumbs' => $this->breadcrumbs('Notas', 'list'),
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

        $template = $request->hasHeader('HX-Request') ? 'pages/notas/_panel.twig' : 'pages/notas/index.twig';

        $rendered = $this->render($request, $response, $template, $data + $extra + [
            'active' => 'notas',
        ])->withStatus($status);

        if ($status >= 400) {
            return $rendered;
        }

        return $rendered->withHeader('HX-Push-Url', Url::path('/notas')
            . '?page=' . $data['pagination']['current_page']
            . '&per_page=' . $data['pagination']['per_page']);
    }

    /* Acciones de ruta: las invoca Slim, una por cada ruta de `routes/notas.php`. */

    /** `GET /notas`: el listado. Delega en `renderList()`; el endpoint no arma datos. */
    public function index(Request $request, Response $response): Response
    {
        return $this->renderList($request, $response);
    }

    /**
     * `POST /notas`: el alta.
     *
     * Valida el título y el cuerpo; si pasa, guarda y redirige al listado con el
     * aviso, y si falla devuelve 422 con el modal de alta abierto y lo tipeado.
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $data['cuerpo'] = trim((string) ($data['cuerpo'] ?? ''));

        $validator = Validator::make($data, [
            'titulo' => 'required|max:120',
            'cuerpo' => 'max:2000',
        ]);

        if ($validator->fails()) {
            return $this->renderList($request, $response, [
                'errors'            => $validator->errors(),
                'form_values'       => $data,
                'open_create_modal' => true,
            ], 422);
        }

        Nota::create([
            'titulo' => (string) $data['titulo'],
            'cuerpo' => $data['cuerpo'] !== '' ? $data['cuerpo'] : null,
        ]);

        Flash::set('success', 'Nota creada.');

        return $this->redirect($response, '/notas');
    }

    /**
     * `GET /notas/{id}/edit`: el formulario de edición.
     *
     * No abre una pantalla aparte: reusa el listado y muestra el modal de
     * edición con la nota cargada. Un id que no existe es un 404.
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request);
        }

        return $this->renderList($request, $response, [
            'nota'            => $nota,
            'open_edit_modal' => true,
        ]);
    }

    /**
     * `POST /notas/{id}`: guarda la edición.
     *
     * Valida igual que el alta; si falla devuelve 422 con el modal de edición
     * abierto, y si pasa guarda y redirige al listado.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request);
        }

        $data = (array) $request->getParsedBody();
        $data['cuerpo'] = trim((string) ($data['cuerpo'] ?? ''));

        $validator = Validator::make($data, [
            'titulo' => 'required|max:120',
            'cuerpo' => 'max:2000',
        ]);

        if ($validator->fails()) {
            // `nota` no es opcional: `index.twig` envuelve el modal de edición en
            // `{% if nota is defined %}`, así que sin la clave no se renderiza y lo
            // tipeado termina en el modal de alta.
            return $this->renderList($request, $response, [
                'errors'          => $validator->errors(),
                'form_values'     => $data,
                'nota'            => $nota,
                'open_edit_modal' => true,
            ], 422);
        }

        $nota->fill([
            'titulo' => (string) $data['titulo'],
            'cuerpo' => $data['cuerpo'] !== '' ? $data['cuerpo'] : null,
        ])->save();

        Flash::set('success', 'Nota actualizada.');

        return $this->redirect($response, '/notas');
    }

    /** `POST /notas/{id}/delete`: borra la nota y vuelve al listado con el aviso. */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request);
        }

        $nota->delete();

        Flash::set('success', 'Nota borrada.');

        return $this->redirect($response, '/notas');
    }
}
