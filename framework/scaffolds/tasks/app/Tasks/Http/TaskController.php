<?php

declare(strict_types=1);

namespace App\Tasks\Http;

use Framework\Http\Controller;
use Framework\Http\Flash;
use Framework\Http\Pagination;
use Framework\Http\Url;
use Framework\Validation\Validator;
use App\Tasks\Models\Task;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

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
            // Con el prefijo ya aplicado: base_url es la fuente de la
            // paginación, del `hx-get` del select de tamaño y del HX-Push-Url
            // de más abajo, así que las tres heredan el prefijo sin saberlo.
            Url::path('/tareas')
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
     * Toda acción de esta ruta responde por aquí, y el template lo elige el
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
     * `active` y `breadcrumbs` los lee el shell y los ignoran los parciales,
     * así que pueden viajar en las dos formas.
     *
     * `$status` es el status de la respuesta. 422 en un fallo de validación no
     * es cosmético: es lo que le dice a htmx (con el handler de app.twig) que
     * swapee igual, y lo que le dice al formulario de alta que NO se limpie.
     */
    private function respond(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        // El orden del merge importa: `pageData()` primero, así un $extra no
        // puede pisar `tasks`/`pagination` (ni el header de abajo).
        $data = $this->pageData($request) + $extra + [
            'active' => 'tareas',
            'breadcrumbs' => [
                ['label' => 'Inicio', 'href' => Url::path('/')],
                ['label' => 'Tareas', 'icon' => 'tasks', 'current' => true],
            ],
        ];

        $response = $this->render(
            $request,
            $response,
            $request->hasHeader('HX-Request') ? 'tasks/_panel.twig' : 'tasks/index.twig',
            $data
        );

        // Un 422 no es una posición nueva de la lista: es el mismo panel con el
        // mensaje de validación al lado del campo. Empujar la URL canónica ahí
        // le escribiría al usuario una entrada de historial por un intento que
        // no cambió nada, así que el `HX-Push-Url` queda reservado al 200.
        if ($status !== 200) {
            return $response->withStatus($status);
        }

        if (!$request->hasHeader('HX-Request') || !isset($data['pagination'])) {
            return $response;
        }

        // La posición de la lista viaja en la URL (S11) desde el HEADER, no
        // desde un `hx-push-url="/tareas?page=2"` en la plantilla:
        // Pagination::paginate() clampea la página pedida al rango válido, así
        // que borrar la última fila de la página 2 hace que el server
        // renderice la 1. Una URL fija en el markup empujaría una posición que
        // contradice el contenido que acaba de llegar; sólo el server que
        // renderizó sabe qué página mostró. htmx 2.0.11 lee el header antes que
        // el atributo (verificado en el vendor: `HX-Push-Url` gana sobre
        // `hx-push-url`). Se empuja la URL canónica de la vista y no la de la
        // acción: /tareas/5/toggle sólo acepta POST y recargarla sería un 404.
        // Sin `HX-Request` no se emite: la página entera ya trae su propia URL.
        $pagination = $data['pagination'];

        return $response->withHeader('HX-Push-Url', sprintf(
            '%s?page=%d&per_page=%d',
            $pagination['base_url'],
            $pagination['current_page'],
            $pagination['per_page']
        ));
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        $validator = Validator::make($data, [
            'title' => 'required|max:120',
        ]);

        if ($validator->fails()) {
            // El campo de alta vive FUERA de #tareas-panel (ver index.twig), así
            // que el swap no lo toca y ningún mensaje por campo puede quedar
            // pegado a él: el toast es su ÚNICO canal in-band, y por eso aquí el
            // flash SÍ se conserva (en update() no, porque el mensaje se lee al
            // lado del input). El 422 es la otra mitad: sin él el formulario se
            // limpiaría y el usuario perdería lo tipeado.
            // `errors` viaja para que el panel tenga la misma forma que en
            // update(); como la lista no está en modo edición, hoy no lo lee.
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');

            return $this->respond($request, $response, [
                'errors' => $validator->errors(),
            ], 422);
        }

        Task::create(['title' => $data['title']]);
        Flash::set('success', 'Tarea creada.');

        // Devolvemos el panel completo, no solo la lista: el flash vive en el
        // mismo swap, así se ve sin recargar.
        return $this->respond($request, $response);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $deleted = Task::destroy((int) $args['id']);

        // destroy() de un id inexistente no falla: devuelve 0. Con htmx el
        // panel vuelve con un aviso (sin este branch el toast diría "eliminada"
        // sobre una fila que nunca existió). Sin htmx no hay panel adonde
        // avisar: es un DELETE sobre un recurso que no existe, o sea un 404.
        if ($deleted === 0 && !$request->hasHeader('HX-Request')) {
            throw new HttpNotFoundException($request);
        }

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

        // Un id que no existe, CON htmx, no responde 404: htmx metería la
        // página de error dentro de #tareas-panel. Ahí vuelve el panel con un
        // flash, que es donde la app ya comunica todo lo que el usuario tiene
        // que ver sin recargar. El flash NO se consume aquí: lo levanta
        // render(), que decide si va inline o en el header de htmx.
        if ($task === null) {
            if (!$request->hasHeader('HX-Request')) {
                // Sin htmx es un navegador pidiendo el documento de un recurso
                // que no existe: 404 de verdad, con el mensaje de
                // errors/error.twig. Un 200 con un cartel mentiría al
                // protocolo (y un curl --fail no se enteraría).
                throw new HttpNotFoundException($request);
            }

            Flash::set('error', 'La tarea no existe o ya fue eliminada.');
        }

        return $this->respond($request, $response, [
            'editing_id' => $task?->id,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

        // Mismo criterio que en edit(): con htmx un 404 llegaría al panel como
        // HTML ajeno, así que la fila inexistente vuelve como panel + flash.
        // Sin htmx es un PUT sobre un recurso que no existe: 404.
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
            // Sin flash: el mensaje ya se lee pegado al campo (`errors.title`)
            // y la fila sigue en modo formulario conservando lo tipeado. Un
            // banner con el mismo texto lo diría dos veces, y el canal de
            // avisos queda para lo que no es un campo.
            // Sin esto el input se repinta con task.title (guardado) y el
            // usuario pierde lo que escribió justo cuando aparece el error.
            return $this->respond($request, $response, [
                'editing_id'    => $task->id,
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
     * Cambia el estado entre pendiente y completado, guardando la fecha
     * de finalización cuando se marca como completada y limpiándola cuando
     * se desactiva. El mismo panel vuelve entero con la lista actualizada.
     */
    public function toggle(Request $request, Response $response, array $args): Response
    {
        $task = Task::find((int) $args['id']);

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
