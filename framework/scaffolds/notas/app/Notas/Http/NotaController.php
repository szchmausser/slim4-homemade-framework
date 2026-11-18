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

final class NotaController extends Controller
{
    /*
     * Utilidades del controlador. Van primero a propósito: no son acciones de
     * ruta, las consumen las acciones de abajo.
     */

    /**
     * Datos de la pantalla de listado: notas de la página pedida, metadata de
     * paginación y miga de pan.
     *
     * La comparten las cuatro acciones que renderizan esta pantalla (`index`,
     * `edit` y los rechazos de `store` y `update`). Si viviera dentro de
     * `index()`, cada una tendría que repetir el recorte, la miga y el `active`.
     *
     * @return array<string, mixed>
     */
    private function pageData(Request $request): array
    {
        $query = $request->getQueryParams();

        $paginacion = Pagination::paginate(
            Nota::orderByDesc('id'),
            (int) ($query['page'] ?? 1),
            (int) ($query['per_page'] ?? Pagination::DEFAULT_PER_PAGE),
            Url::path('/notas')
        );

        return [
            'notas'      => $paginacion['items'],
            'pagination' => $paginacion,
            // La miga la lee el header del shell: Inicio como enlace y Notas
            // como página actual. Sin esto el header cae en su fallback y
            // muestra «Inicio» como si estuvieras en la portada.
            'breadcrumbs' => [
                ['label' => 'Inicio', 'href' => Url::path('/')],
                ['label' => 'Notas', 'icon' => 'list', 'current' => true],
            ],
        ];
    }

    /**
     * Render único de la pantalla de listado.
     *
     * No es una acción de ruta: la llaman `index()`, `edit()` y los rechazos de
     * `store()` y `update()`. Existe para que esos cuatro caminos compartan la
     * plantilla, el `active`, la paginación y la URL, en un solo lugar.
     *
     * @param array<string, mixed> $extra  datos del llamador: errores, lo tipeado, el modal a abrir
     * @param int                  $status estado HTTP de la respuesta; 422 en un rechazo
     *
     * Con `HX-Request` devuelve sólo el panel: si vuelve la página entera, el
     * swap anida el shell (dos sidebars, dos headers). Emite `HX-Push-Url` sólo
     * en 2xx, porque un 422 no es una página que el usuario haya navegado.
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

    /*
     * Acciones de ruta: las invoca Slim, cada una con su método HTTP.
     */

    /** `GET /notas`. Sólo delega en `renderList()`: el endpoint no arma datos. */
    public function index(Request $request, Response $response): Response
    {
        return $this->renderList($request, $response);
    }

    /**
     * `POST /notas`. Si valida, guarda y redirige con aviso; si no, devuelve
     * 422 con el modal de alta abierto y lo tipeado.
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

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }

    /**
     * `GET /notas/{id}/edit`. No es una pantalla aparte: reusa el listado y
     * abre el modal de edición con la nota cargada.
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
     * `POST /notas/{id}`. Si valida, guarda y redirige; si no, devuelve 422 con
     * el modal de edición abierto.
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
            // `nota` no es decorativo: index.twig envuelve el modal de edición
            // en {% if nota is defined %}. Sin la clave no se renderiza y lo
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

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }

    /** `POST /notas/{id}/delete`. Borra y redirige con aviso. */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request);
        }

        $nota->delete();

        Flash::set('success', 'Nota borrada.');

        return $response->withStatus(303)->withHeader('Location', Url::path('/notas'));
    }

    /** El aviso de esta pantalla viaja como toast del shell, no inline. */
    protected function usesToastFlash(): bool
    {
        return true;
    }
}
