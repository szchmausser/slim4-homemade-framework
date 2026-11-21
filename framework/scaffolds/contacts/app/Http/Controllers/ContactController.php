<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use Framework\Http\Controller;
use Framework\Http\Flash;
use Framework\Http\Pagination;
use Framework\Http\Url;
use Framework\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

/**
 * Controlador del demo Contactos: atiende las rutas de `routes/contacts.php` y
 * decide qué plantilla devuelve cada una.
 *
 * Entra con el CSRF y `RequireAuthMiddleware` ya corridos; sale al modelo
 * `Contact`, a `Pagination`, `Validator`, `Flash` y a las vistas de la demo.
 *
 * Para tu entidad cambian el modelo, `SORTABLE`, las reglas de
 * `validationErrors()` y los nombres de plantilla. El modelo vive en
 * `app/Models/Contact.php`: layout plano, sin namespace por demo.
 */
final class ContactController extends Controller
{
    // Escapan `<`, `>`, `'` y `"` en el JSON del cuerpo y del header `HX-Trigger`;
    // `JSON_THROW_ON_ERROR` falla el render en vez de devolver `false`.
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Columnas por las que se puede ordenar: clave que llega en el request =>
     * columna SQL.
     *
     * Es un mapa y no una lista porque el valor recibido se usa solo como clave:
     * la columna que entra al `orderBy` sale de acá, nunca del request. Un
     * `password_hash` no matchea ninguna clave y no llega a la query; una
     * expresión regular para validar el nombre sí dejaría esa puerta abierta.
     *
     * @var array<string, string>
     */
    private const SORTABLE = ['name' => 'name', 'email' => 'email', 'phone' => 'phone'];

    /** `GET /contacts`: renderiza la página; las filas las pide la tabla a `data`. */
    public function index(Request $request, Response $response): Response
    {
        return $this->renderList($request, $response);
    }

    /**
     * `POST /contacts`: valida y crea. Con htmx responde 204 y eventos; sin JS
     * redirige con aviso, y un rechazo vuelve con el formulario repintado.
     */
    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            if ($this->isHtmx($request)) {
                // htmx reemplaza solo el formulario: el modal sigue abierto y no
                // se pierde lo escrito.
                return $this->renderForm($request, $response, [
                    'action' => Url::path('/contacts'),
                    'cancel_target' => 'contact-create-modal',
                    'submit_label' => 'Crear contacto',
                    'submit_icon' => 'plus',
                    'errors' => $errors,
                    'form_values' => $this->attributes($data),
                ], 422);
            }

            // Sin JS no hay swap parcial: vuelve la página con el modal de alta
            // abierto, lo tipeado y el mensaje de cada campo.
            return $this->renderList($request, $response, [
                'errors' => $errors,
                'form_values' => $this->attributes($data),
                'open_create_modal' => true,
            ], 422);
        }

        Contact::create($this->attributes($data));

        if ($this->isHtmx($request)) {
            return $this->hxSuccess($response, 'Contacto creado.', 'create');
        }

        Flash::set('success', 'Contacto creado.');

        return $this->back($response);
    }

    /** `GET /contacts/{id}/edit`: con htmx, solo el formulario; sin JS, la página. */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $contact = Contact::find((int) $args['id']);

        if ($contact === null) {
            throw new HttpNotFoundException($request);
        }

        if ($this->isHtmx($request)) {
            return $this->renderForm($request, $response, [
                'action' => Url::path('/contacts/' . $contact->getKey()),
                'contact' => $contact,
                'cancel_target' => 'contact-edit-modal',
                'submit_label' => 'Guardar cambios',
                'submit_icon' => 'check',
            ]);
        }

        return $this->renderEdit($request, $response, $contact);
    }

    /**
     * `POST /contacts/{id}`: valida y guarda. Un rechazo repinta el formulario
     * con lo tipeado; el 404 sale antes, cuando el contacto no existe.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $contact = Contact::find((int) $args['id']);

        if ($contact === null) {
            throw new HttpNotFoundException($request);
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            if ($this->isHtmx($request)) {
                // Mismo formulario, con los errores y lo tipeado: el modal no se
                // cierra.
                return $this->renderForm($request, $response, [
                    'action' => Url::path('/contacts/' . $contact->getKey()),
                    'contact' => $contact,
                    'cancel_target' => 'contact-edit-modal',
                    'submit_label' => 'Guardar cambios',
                    'submit_icon' => 'check',
                    'errors' => $errors,
                    'form_values' => $this->attributes($data),
                ], 422);
            }

            // Sin JS vuelve la página de edición entera, con el error y sin
            // perder lo tipeado.
            return $this->renderEdit($request, $response, $contact, [
                'errors' => $errors,
                'form_values' => $this->attributes($data),
            ], 422);
        }

        $contact->fill($this->attributes($data))->save();

        if ($this->isHtmx($request)) {
            return $this->hxSuccess($response, 'Contacto actualizado.', 'update');
        }

        Flash::set('success', 'Contacto actualizado.');

        return $this->back($response);
    }

    /**
     * `POST /contacts/{id}/delete`: borra y responde. Sin JS redirige con el
     * aviso que corresponda; con htmx devuelve 204 y los eventos que cierran el
     * modal y refrescan la tabla. Un id inexistente se avisa, no rompe.
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $deleted = Contact::destroy((int) $args['id']);

        if ($this->isHtmx($request)) {
            return $this->hxSuccess(
                $response,
                $deleted === 0 ? 'El contacto no existe.' : 'Contacto eliminado.',
                'delete',
                $deleted === 0 ? 'error' : 'success'
            );
        }

        $deleted === 0
            ? Flash::set('error', 'El contacto no existe.')
            : Flash::set('success', 'Contacto eliminado.');

        return $this->back($response);
    }

    /**
     * `GET /contacts/data`: el endpoint que consume el modo remoto de la tabla.
     *
     * Devuelve `{last_page, last_row, data}` con la página y el orden resueltos
     * en el servidor. `page` y `per_page` son los mismos nombres del resto de la
     * app y los resuelve `Pagination::fromRequest()`, que clampea el tamaño y la
     * página fuera de rango.
     */
    public function data(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        $listing = Pagination::fromRequest(
            $this->sortedContacts($query['sort'] ?? null),
            $request,
            Url::path('/contacts/data')
        );

        // `#` es la posición en el orden visible, no el id: con un sorter activo
        // la primera fila de la página 2 sigue siendo la 11. Sale del offset de
        // la página, así que no puede desincronizarse del slice recibido.
        $offset = ($listing['current_page'] - 1) * $listing['per_page'];

        $rows = [];
        foreach ($listing['items'] as $index => $contact) {
            /** @var Contact $contact */
            $rows[] = ['number' => $offset + $index + 1] + $this->row($contact);
        }

        $response->getBody()->write(json_encode([
            'last_page' => $listing['last_page'],
            // Tabulator calcula el total como last_page * size si no recibe
            // last_row; con el conteo exacto el "de N filas" no miente.
            'last_row'  => $listing['total'],
            'data'      => $rows,
        ], self::JSON_FLAGS));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /** Toda petición de htmx trae `HX-Request`; eso separa las dos respuestas. */
    private function isHtmx(Request $request): bool
    {
        return $request->hasHeader('HX-Request');
    }

    /**
     * Respuesta de una mutación hecha con htmx: 204, sin cuerpo, y dos eventos en
     * el header.
     *
     * `toasts` alimenta el mismo host de avisos que el resto de la app;
     * `contacts:changed` lo escucha `public/assets/js/contacts-tabulator.js` para
     * recargar la tabla sin perder página ni orden.
     */
    private function hxSuccess(
        Response $response,
        string $message,
        string $action,
        string $type = 'success'
    ): Response {
        $payload = [
            'toasts' => ['items' => [['message' => $message, 'type' => $type]]],
            'contacts:changed' => [
                'action' => $action,
                'count' => Contact::query()->count(),
            ],
        ];

        return $response
            ->withStatus(204)
            ->withHeader('HX-Trigger', json_encode($payload, self::JSON_FLAGS));
    }

    /**
     * Render del formulario suelto: es lo que reemplaza al formulario del modal
     * cuando la petición viene de htmx.
     *
     * @param array<string, mixed> $data
     */
    private function renderForm(Request $request, Response $response, array $data, int $status = 200): Response
    {
        return $this->render($request, $response, 'pages/contacts/_form.twig', $data)->withStatus($status);
    }

    /** Vuelta al listado con 303: el `GET` que sigue al `POST` del patrón PRG. */
    private function back(Response $response): Response
    {
        return $this->redirect($response, '/contacts');
    }

    /**
     * Render de la página de listado, con el `active` y la miga de pan armados
     * en un solo lugar.
     *
     * Lo reusa el alta sin JS que falla la validación: necesita la misma página
     * más el modal abierto y lo tipeado.
     *
     * @param array<string, mixed> $extra
     */
    private function renderList(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $response = $this->render($request, $response, 'pages/contacts/index.twig', [
            'active' => 'contacts',
            'breadcrumbs' => $this->breadcrumbs('Contactos', 'contacts'),
        ] + $extra);

        return $status === 200 ? $response : $response->withStatus($status);
    }

    /**
     * Render de la página de edición, con el contacto y la miga de tres niveles.
     *
     * `form_values` y `errors` los lee `pages/contacts/_form.twig`: con ellos el
     * formulario se repinta con lo tipeado y el mensaje; sin ellos sale con los
     * valores guardados.
     *
     * @param array<string, mixed> $extra
     */
    private function renderEdit(
        Request $request,
        Response $response,
        Contact $contact,
        array $extra = [],
        int $status = 200
    ): Response {
        $response = $this->render($request, $response, 'pages/contacts/edit.twig', [
            'active' => 'contacts',
            'contact' => $contact,
            'breadcrumbs' => $this->breadcrumbsFor('Contactos', Url::path('/contacts'), 'Editar'),
        ] + $extra);

        return $status === 200 ? $response : $response->withStatus($status);
    }

    /**
     * Query ordenada según los sorters del request.
     *
     * La tabla manda `sort[i][field]` y `sort[i][dir]`, que en PHP llega como
     * `[['field' => 'name', 'dir' => 'asc']]`. Se aplican todos, en orden de
     * llegada: con shift+click el usuario arma un orden compuesto y quedarse con
     * el primero lo rompería. Un campo fuera de `SORTABLE` se descarta en
     * silencio y, si no queda ninguno válido, el default es id descendente.
     *
     * @return Builder<Contact>
     */
    private function sortedContacts(mixed $sorters): Builder
    {
        $query = Contact::query();

        $applied = 0;

        foreach (is_array($sorters) ? $sorters : [] as $sorter) {
            if (!is_array($sorter)) {
                continue;
            }

            $field = $sorter['field'] ?? null;
            $column = is_string($field) ? (self::SORTABLE[$field] ?? null) : null;

            if ($column === null) {
                continue;
            }

            // La dirección no se interpola: se normaliza a dos literales.
            $query->orderBy($column, ($sorter['dir'] ?? null) === 'desc' ? 'desc' : 'asc');
            $applied++;
        }

        return $applied === 0 ? $query->orderByDesc('id') : $query;
    }

    /**
     * Reglas de validación de un alta o una edición: devuelve `campo => mensaje`
     * y un array vacío cuando pasa.
     *
     * Las reglas viven acá y no en las acciones porque `store` y `update`
     * validan lo mismo: cambia esta tabla y las dos quedan cubiertas.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> campo => mensaje
     */
    private function validationErrors(array $data): array
    {
        return Validator::make($data, [
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'max:50',
        ])->errors();
    }

    /**
     * Convierte lo que llegó del formulario en el array que reciben `create()` y
     * `fill()`: solo `name`, `email` y `phone`, sin espacios y con el teléfono
     * vacío en `null` en vez de cadena vacía.
     *
     * Es el límite de asignación masiva del demo: un campo extra del request no
     * aparece acá y por lo tanto no llega a la base.
     *
     * @param array<string, mixed> $data
     * @return array<string, string|null>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
        ];
    }

    /**
     * Una fila del JSON: los campos de la columna y las dos URLs que el script
     * del demo usa para las acciones de cada fila.
     *
     * @return array{id: int, name: string, email: string, phone: string, edit_url: string, delete_url: string}
     */
    private function row(Contact $contact): array
    {
        $id = (int) $contact->getKey();

        return [
            'id' => $id,
            'name' => (string) $contact->getAttribute('name'),
            'email' => (string) $contact->getAttribute('email'),
            'phone' => (string) ($contact->getAttribute('phone') ?? ''),
            'edit_url' => Url::path('/contacts/' . $id . '/edit'),
            'delete_url' => Url::path('/contacts/' . $id . '/delete'),
        ];
    }
}
