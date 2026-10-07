<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\Flash;
use App\Support\Pagination;
use App\Support\Validator;
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
        // "Cancelar" apunta a /tareas con hx-target="#tareas-panel". Si
        // devolvemos index.twig (que extiende el layout) HTMX recibe un
        // <html> completo, se queda con el <body> y lo inserta dentro del
        // panel: dos <h1>, dos #alta-tarea y padding doble. Con HX-Request
        // devolvemos solo la región, igual que hace edit().
        $template = $request->hasHeader('HX-Request')
            ? 'tasks/_panel.twig'
            : 'tasks/index.twig';

        return $this->render($request, $response, $template, $this->pageData($request) + [
            'active' => 'tareas',
        ]);
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
        }

        // Devolvemos el panel completo, no solo la lista: el flash vive en el
        // mismo swap, así se ve sin recargar.
        return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request));
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        Task::destroy((int) $args['id']);

        return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request));
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        // La edición es inline: el panel vuelve entero con una sola fila en
        // modo formulario. Sin `editing_id` la lista no sabe cuál editar.
        $task = Task::find((int) $args['id']);

        // Un id que no existe no responde 404: htmx metería la página de
        // error dentro de #tareas-panel. Devolvemos el panel con un flash,
        // que es donde la app ya comunica todo lo que el usuario tiene que
        // ver sin recargar.
        $flash = null;
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');
            $flash = Flash::get('error');
        }

        return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request) + [
            'editing_id'  => $task?->id,
            'flash_error' => $flash,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en edit(): sin fila no hay nada que validar ni
        // que guardar, y un 404 llegaría al panel como HTML ajeno.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request) + [
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
            return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request) + [
                'editing_id'    => $task->id,
                'editing_title' => $data['title'] ?? '',
            ]);
        }

        $task->update(['title' => $data['title']]);

        return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request) + [
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

            return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request));
        }

        if ($task->status === 'completado') {
            $task->update(['status' => 'pendiente', 'completed_at' => null]);
        } else {
            $task->update(['status' => 'completado', 'completed_at' => now()]);
        }

        return $this->render($request, $response, 'tasks/_panel.twig', $this->pageData($request));
    }
}
