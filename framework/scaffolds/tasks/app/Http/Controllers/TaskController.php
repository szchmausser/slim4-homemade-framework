<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Framework\Http\Controller;
use Framework\Http\Flash;
use Framework\Http\Pagination;
use Framework\Http\Url;
use Framework\Validation\Validator;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

/**
 * Controlador de `tasks`: las seis acciones y el único render de su pantalla.
 *
 * Capa controlador. Slim lo invoca desde `routes/tasks.php`; suma `Pagination`
 * para el recorte, `Validator` para el título, `Flash` para los avisos y
 * `Controller::render()` para la plantilla. El modelo vive en `app/Models/Task.php`,
 * el layout plano que enseña la convención.
 *
 * Para tu entidad, copia las acciones que necesites y deja `respond()` como el
 * único lugar que elige plantilla y estado.
 */
class TaskController extends Controller
{
    /**
     * Slice paginado más la metadata, para toda respuesta de esta ruta: la página
     * completa y el swap parcial necesitan la paginación al día. La página y el
     * tamaño salen del query string (`page`, `per_page`), que mandan el navegador
     * y los enlaces de HTMX por igual.
     *
     * @return array<string, mixed>
     */
    private function pageData(Request $request): array
    {
        $listing = Pagination::fromRequest(
            Task::orderByDesc('id'),
            $request,
            // Con el prefijo ya aplicado: el `base_url` de este resultado es la
            // fuente de los enlaces del paginador y del `HX-Push-Url`, así que
            // los dos heredan el prefijo sin saberlo.
            Url::path('/tasks')
        );

        return [
            'tasks'      => $listing['items'],
            'pagination' => $listing,
        ];
    }

    /** `GET /tasks`. Sólo delega en `respond()`: el endpoint no arma datos. */
    public function index(Request $request, Response $response): Response
    {
        return $this->respond($request, $response);
    }

    /**
     * Render de la pantalla: elige plantilla y estado, y suma los datos comunes.
     *
     * La llaman las seis acciones, así que el `active`, la miga y la paginación
     * viven aquí y no se repiten en cada una. Con `HX-Request` devuelve sólo el
     * panel; sin él, la página completa.
     *
     * @param array<string, mixed> $extra  datos del llamador: errores, lo tipeado, `editing_id`
     * @param int                  $status estado HTTP; 422 cuando un dato no valida
     */
    private function respond(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        // El orden del merge importa: `pageData()` primero, así un `$extra` no
        // puede pisar `tasks` ni `pagination`.
        $data = $this->pageData($request) + $extra + [
            'active' => 'tasks',
            'breadcrumbs' => $this->breadcrumbs('Tareas', 'tasks'),
        ];

        // La plantilla la elige el request y no la acción: `_panel.twig` es la
        // región que se intercambia e `index.twig` la página que extiende el
        // shell. Devolver el documento entero dentro del panel lo duplicaría.
        $response = $this->render(
            $request,
            $response,
            $request->hasHeader('HX-Request') ? 'pages/tasks/_panel.twig' : 'pages/tasks/index.twig',
            $data
        );

        // Un 422 no es una posición nueva de la lista: es el mismo panel con el
        // mensaje al lado del campo. Empujar la URL canónica ahí sería una
        // entrada de historial por un intento que no cambió nada.
        if ($status !== 200) {
            return $response->withStatus($status);
        }

        // Sin `HX-Request` no se emite: la página entera ya trae su propia URL.
        if (!$request->hasHeader('HX-Request') || !isset($data['pagination'])) {
            return $response;
        }

        // La posición se publica desde acá y no con un `hx-push-url` fijo en la
        // plantilla: `Pagination` recorta la página pedida al rango real, así que
        // sólo el servidor que renderizó sabe qué página mostró.
        $pagination = $data['pagination'];

        // La URL empujada es la canónica de la vista y no la de la acción:
        // `/tasks/5/toggle` sólo acepta POST, y recargarla sería un 404.
        return $response->withHeader('HX-Push-Url', sprintf(
            '%s?page=%d&per_page=%d',
            $pagination['base_url'],
            $pagination['current_page'],
            $pagination['per_page']
        ));
    }

    /**
     * `POST /tasks`. Valida el título, guarda y vuelve al panel con el aviso; si
     * no valida, responde 422.
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            // El campo de alta vive fuera de `#tareas-panel`, así que el swap no
            // lo toca: el toast es el único canal del mensaje. El 422 evita que
            // el formulario se limpie y el usuario pierda lo tipeado.
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');

            return $this->respond($request, $response, [
                'errors' => $validator->errors(),
            ], 422);
        }

        Task::create(['title' => $data['title']]);
        Flash::set('success', 'Tarea creada.');

        // El panel vuelve completo, no sólo la lista: el aviso se emite en este
        // mismo swap.
        return $this->respond($request, $response);
    }

    /**
     * `DELETE /tasks/{id}`. Borra y vuelve al panel con el aviso.
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $deleted = Task::destroy((int) $args['id']);

        // `destroy()` de un id inexistente no falla: devuelve 0. Con htmx el panel
        // vuelve con un aviso; sin htmx no hay panel adonde avisar, así que el
        // recurso que no existe responde 404.
        if ($deleted === 0 && !$request->hasHeader('HX-Request')) {
            throw new HttpNotFoundException($request);
        }

        Flash::set(
            $deleted > 0 ? 'success' : 'error',
            $deleted > 0 ? 'Tarea eliminada.' : 'La tarea no existe o ya fue eliminada.'
        );

        return $this->respond($request, $response);
    }

    /**
     * `GET /tasks/{id}/edit`. Vuelve el panel entero con esa fila en modo
     * formulario.
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        // La edición es inline: sin `editing_id` la lista no sabe qué fila abrir.
        $task = Task::find((int) $args['id']);

        // Con htmx un 404 metería la página de error dentro del panel, así que un
        // id inexistente vuelve como panel con aviso. El flash no se consume acá:
        // lo levanta `render()`, que decide si va inline o en el header.
        if ($task === null) {
            if (!$request->hasHeader('HX-Request')) {
                // Sin htmx es un navegador pidiendo un recurso que no existe:
                // 404 real, con la página de error. Un 200 con un cartel le
                // mentiría al protocolo.
                throw new HttpNotFoundException($request);
            }

            Flash::set('error', 'La tarea no existe o ya fue eliminada.');
        }

        return $this->respond($request, $response, [
            'editing_id' => $task?->id,
        ]);
    }

    /**
     * `PUT /tasks/{id}`. Guarda el título; un fallo de validación devuelve 422 con
     * la fila en modo formulario.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en `edit()`: con htmx un 404 llegaría al panel como
        // HTML ajeno. Sin htmx es un recurso que no existe: 404.
        if ($task === null) {
            if (!$request->hasHeader('HX-Request')) {
                throw new HttpNotFoundException($request);
            }

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
            // Sin flash: el mensaje ya se lee pegado al campo (`errors.title`) y
            // un banner con el mismo texto lo diría dos veces.
            return $this->respond($request, $response, [
                'editing_id'    => $task->id,
                // Sin esto el input se repinta con el título guardado y el
                // usuario pierde lo que escribió justo cuando aparece el error.
                'editing_title' => $data['title'] ?? '',
                'errors'        => $validator->errors(),
            ], 422);
        }

        $task->update(['title' => $data['title']]);
        Flash::set('success', 'Tarea actualizada.');

        return $this->respond($request, $response, [
            'editing_id'    => null,
            'editing_title' => null,
        ]);
    }

    /**
     * `POST /tasks/{id}/toggle`. Alterna `pendiente` y `completado`, guardando
     * `completed_at` al completar y limpiándola al volver atrás.
     *
     * Vuelve el panel entero con la lista actualizada.
     */
    public function toggle(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en `edit()`: panel con aviso si el request es de
        // htmx, y 404 si es un navegador pidiendo un recurso inexistente.
        if ($task === null) {
            if (!$request->hasHeader('HX-Request')) {
                throw new HttpNotFoundException($request);
            }

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
