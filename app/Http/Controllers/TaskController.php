<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\Flash;
use App\Support\Pagination;
use App\Support\Validator;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TaskController extends Controller
{
    /**
     * Slice paginado + metadata para TODA respuesta de esta ruta (página
     * completa o swap parcial: ambas regiones llevan su paginación al día).
     * La página y el tamaño salen del query string (?page=, ?per_page=), que
     * mandan tanto el navegador como los links HTMX del partial.
     */
    private function pageData(Request $request): array
    {
        $query = $request->getQueryParams();

        $listing = Pagination::paginate(
            Task::orderByDesc('id'),
            (int) ($query['page'] ?? 1),
            (int) ($query['per_page'] ?? Pagination::DEFAULT_PER_PAGE),
            '/tareas'
        );

        return [
            'tasks'      => $listing['items'],
            'pagination' => $listing,
        ];
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->respond($request, $response);
    }

    /**
     * Toda acción de esta ruta responde por acá, y el template lo elige el
     * request, no la acción.
     *
     * Con `HX-Request` va sólo la región: "Cancelar" apunta a /tareas con
     * hx-target="#tareas-panel", y si htmx recibiera index.twig (que extiende
     * el layout) se quedaría con el <body> y lo insertaría dentro del panel:
     * dos <h1>, dos #alta-tarea y el padding del layout dos veces.
     *
     * Sin `HX-Request` va la página entera: un form con JS apagado, una URL
     * abierta a mano o un curl son un navegador pidiendo un documento. La
     * mutación ocurre igual, pero el fragmento llega sin <head>, sin hoja de
     * estilos y sin shell, así que una acción exitosa se ve rota (S9).
     *
     * `active => 'tareas'` lo lee el layout (menú y miga de pan) y lo ignoran
     * los parciales, así que puede viajar en las dos formas.
     */
    private function respond(Request $request, Response $response, array $extra = []): Response
    {
        return $this->render(
            $request,
            $response,
            $request->hasHeader('HX-Request') ? 'tasks/_panel.twig' : 'tasks/index.twig',
            $this->pageData($request) + $extra + ['active' => 'tareas']
        );
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
        } else {
            Task::create(['title' => $data['title']]);
            Flash::set('success', 'Tarea creada.');
        }

        // Devolvemos el panel completo, no solo la lista: el flash vive en el
        // mismo swap, así se ve sin recargar.
        return $this->respond($request, $response);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $deleted = Task::destroy((int) $args['id']);

        // destroy() de un id inexistente no falla: devuelve 0. Sin este branch
        // el toast diría "eliminada" sobre una fila que nunca existió.
        Flash::set(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0 ? 'Tarea eliminada.' : 'La tarea no existe o ya fue eliminada.'
        );

        return $this->respond($request, $response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        // La edición es inline: el panel vuelve entero con una sola fila en
        // modo formulario. Sin `editing_id` la lista no sabe cuál editar.
        $task = Task::find((int) $args['id']);

        // Un id que no existe no responde 404: htmx metería la página de
        // error dentro de #tareas-panel. Devolvemos el panel con un flash,
        // que es donde la app ya comunica todo lo que el usuario tiene que
        // ver sin recargar. El flash NO se consume acá: lo levanta render(),
        // que decide si va inline o en el header de htmx.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');
        }

        return $this->respond($request, $response, [
            'editing_id' => $task?->id,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en edit(): sin fila no hay nada que validar ni
        // que guardar, y un 404 llegaría al panel como HTML ajeno.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->respond($request, $response, [
                'editing_id' => null,
            ]);
        }

        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
            // La fila sigue en modo formulario Y conserva lo tipeado: sin esto
            // el input se repinta con task.title (guardado) y el usuario pierde
            // lo que escribió justo cuando aparece el error.
            return $this->respond($request, $response, [
                'editing_id'    => $task->id,
                'editing_title' => $data['title'] ?? '',
            ]);
        }

        $task->update(['title' => $data['title']]);
        Flash::set('success', 'Tarea actualizada.');

        return $this->respond($request, $response, [
            'editing_id'    => null,
            'editing_title' => null,
        ]);
    }

    /**
     * Cambia el estado entre pendiente y completado, guardando la fecha
     * de finalización cuando se marca como completada y limpiándola cuando
     * se desactiva. El mismo panel vuelve entero con la lista actualizada.
     */
    public function toggle(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->respond($request, $response);
        }

        if ($task->status === 'completado') {
            $task->update(['status' => 'pendiente', 'completed_at' => null]);
            Flash::set('success', 'Tarea marcada como pendiente.');
        } else {
            $task->update(['status' => 'completado', 'completed_at' => Carbon::now()]);
            Flash::set('success', 'Tarea completada.');
        }

        return $this->respond($request, $response);
    }
}
