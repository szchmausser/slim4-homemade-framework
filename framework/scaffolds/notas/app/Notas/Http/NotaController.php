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
            'total'      => $paginacion['total'],
            'pagination' => $paginacion,
        ];
    }

    private function renderIndex(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $data = $this->pageData($request);

        // Con HX-Request va SÓLO la región. Si devuelves la página entera, el
        // swap mete el shell del layout adentro del panel: dos sidebars, dos
        // headers y el contenido duplicado.
        $template = $request->hasHeader('HX-Request') ? 'notas/_panel.twig' : 'notas/index.twig';

        return $this->render($request, $response, $template, $data + $extra + [
            'active' => 'notas',
        ])->withStatus($status)
            ->withHeader('HX-Push-Url', Url::path('/notas')
                . '?page=' . $data['pagination']['current_page']
                . '&per_page=' . $data['pagination']['per_page']);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderIndex($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $data['cuerpo'] = trim((string) ($data['cuerpo'] ?? ''));

        $validator = Validator::make($data, [
            'titulo' => 'required|max:120',
            'cuerpo' => 'max:2000',
        ]);

        if ($validator->fails()) {
            return $this->renderIndex($request, $response, [
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

    public function edit(Request $request, Response $response, array $args): Response
    {
        $nota = Nota::find((int) $args['id']);

        if ($nota === null) {
            throw new HttpNotFoundException($request);
        }

        return $this->renderIndex($request, $response, [
            'nota'            => $nota,
            'open_edit_modal' => true,
        ]);
    }

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
            return $this->renderIndex($request, $response, [
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

    protected function usesToastFlash(): bool
    {
        return true;
    }
}
