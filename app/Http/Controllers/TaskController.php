<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Support\Flash;
use App\Support\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Csrf\Guard;
use Slim\Views\Twig;

class TaskController
{
    public function __construct(private Twig $view, private Guard $guard) {}

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

        return $this->view->render($response, $template, [
            'tasks'       => Task::orderByDesc('id')->get(),
            'flash_error' => Flash::get('error'),
            ...$this->csrf($request),
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
        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'flash_error' => Flash::get('error'),
            ...$this->csrf($request),
        ]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        Task::destroy((int) $args['id']);

        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'flash_error' => null,
            ...$this->csrf($request),
        ]);
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

        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'editing_id'  => $task?->id,
            'flash_error' => $flash,
            ...$this->csrf($request),
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en edit(): sin fila no hay nada que validar ni
        // que guardar, y un 404 llegaría al panel como HTML ajeno.
        if ($task === null) {
            Flash::set('error', 'La tarea no existe o ya fue eliminada.');

            return $this->view->render($response, 'tasks/_panel.twig', [
                'tasks'       => Task::orderByDesc('id')->get(),
                'editing_id'  => null,
                'flash_error' => Flash::get('error'),
                ...$this->csrf($request),
            ]);
        }

        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
            // La fila sigue en modo formulario: si volviera a la lista, el
            // usuario pierde el lugar que estaba editando junto con el error.
            $editingId = $task->id;
        } else {
            $task->update(['title' => $data['title']]);
            $editingId = null;
        }

        return $this->view->render($response, 'tasks/_panel.twig', [
            'tasks'       => Task::orderByDesc('id')->get(),
            'editing_id'  => $editingId,
            'flash_error' => Flash::get('error'),
            ...$this->csrf($request),
        ]);
    }

    private function csrf(Request $request): array
    {
        $nameKey  = $this->guard->getTokenNameKey();
        $valueKey = $this->guard->getTokenValueKey();

        return [
            'csrf_name_key'  => $nameKey,
            'csrf_name'      => $request->getAttribute($nameKey),
            'csrf_value_key' => $valueKey,
            'csrf_value'     => $request->getAttribute($valueKey),
        ];
    }
}
