<?php

declare(strict_types=1);

namespace App\Contacts\Http;

use App\Contacts\Models\Contact;
use Framework\Http\Controller;
use Framework\Http\Flash;
use Framework\Http\Pagination;
use Framework\Http\Url;
use Framework\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class ContactController extends Controller
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Columnas por las que se puede ordenar: campo del request => columna SQL.
     *
     * Es un mapa y no una lista de nombres permitidos porque el valor que
     * llega se usa SÓLO como clave: la columna que entra al `orderBy` sale de
     * aquí, nunca del request. Un `password_hash`, un `id` o una expresión
     * inventada no matchean ninguna clave y no llegan a la query ni siquiera
     * para ser rechazados por la base. Validar el nombre con una regex deja
     * abierta la puerta que este mapa cierra.
     *
     * @var array<string, string>
     */
    private const SORTABLE = ['name' => 'name', 'email' => 'email', 'phone' => 'phone'];

    /**
     * Las acciones de contactos que completan su trabajo redirigen a
     * /contactos; el flash de esa acción se muestra como toast al cargar la
     * página, con el mismo host que usa el demo de tareas en sus swaps HTMX.
     *
     * El redirect es PRG y no la única respuesta: un fallo de validación (o un
     * recurso inexistente) responde 422/404 con la pantalla re-renderizada,
     * porque ahí redirigir descartaba lo tipeado (S7) y escondía el error
     * detrás de un toast de cinco segundos.
     */
    protected function usesToastFlash(): bool
    {
        return true;
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->renderIndex($request, $response);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            // S7: nada de redirect. El modal de alta vuelve ABIERTO, con los
            // valores tipeados y el mensaje de cada campo: el usuario sigue
            // donde estaba escribiendo en vez de reabrirlo y tipear todo de
            // nuevo. Sin flash: el error ya vive al lado del campo.
            return $this->renderIndex($request, $response, [
                'errors' => $errors,
                'form_values' => $this->attributes($data),
                'open_create_modal' => true,
            ], 422);
        }

        Contact::create($this->attributes($data));
        Flash::set('success', 'Contacto creado.');

        return $this->back($response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $contact = Contact::find((int) $args['id']);

        if ($contact === null) {
            // Un contacto inexistente es un 404, no un redirect al listado: el
            // redirect escondía el problema detrás de un aviso de cinco
            // segundos y el usuario no entendía por qué no se abrió nada.
            throw new HttpNotFoundException($request);
        }

        return $this->renderEdit($request, $response, $contact);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $contact = Contact::find((int) $args['id']);

        if ($contact === null) {
            throw new HttpNotFoundException($request);
        }

        $data = (array) $request->getParsedBody();
        $errors = $this->validationErrors($data);

        if ($errors !== []) {
            // S7: esto era un 303 a /contactos, que descartaba la pantalla de
            // edición entera —lo tipeado, el mensaje y hasta el contacto que
            // se estaba editando—. Vuelve la misma página con los valores y
            // los errores.
            return $this->renderEdit($request, $response, $contact, [
                'errors' => $errors,
                'form_values' => $this->attributes($data),
            ], 422);
        }

        $contact->fill($this->attributes($data))->save();
        Flash::set('success', 'Contacto actualizado.');

        return $this->back($response);
    }

    /**
     * El borrado SÍ se queda en PRG: es una acción que muta y vuelve al
     * listado, no una vista de recurso. Un id inexistente no rompe nada (el
     * listado es un destino válido) y el aviso viaja en el flash de siempre.
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $deleted = Contact::destroy((int) $args['id']);

        $deleted === 0
            ? Flash::set('error', 'El contacto no existe.')
            : Flash::set('success', 'Contacto eliminado.');

        return $this->back($response);
    }

    /**
     * Listado del modo remoto de Tabulator: `{last_page, data}` con la página y
     * el orden resueltos en el servidor.
     *
     * La página y el tamaño se leen con los nombres que ya usa el resto de la
     * app (`page`, `per_page`) y se delegan a Pagination::paginate, que cuenta,
     * clampea y recorta. No hay una segunda paginación aquí: duplicarla era
     * parte del defecto que este endpoint cierra.
     */
    public function data(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();

        $listing = Pagination::paginate(
            $this->sortedContacts($query['sort'] ?? null),
            (int) ($query['page'] ?? 1),
            (int) ($query['per_page'] ?? Pagination::DEFAULT_PER_PAGE),
            Url::path('/contactos/datos')
        );

        // `#` es la posición en el orden que se está viendo, no el id: con un
        // sorter activo la primera fila de la página 2 sigue siendo la 11.
        // Sale del offset de la página, así que el cliente no lo recalcula ni
        // puede desincronizarse del slice que recibió.
        $offset = ($listing['current_page'] - 1) * $listing['per_page'];

        $rows = [];
        foreach ($listing['items'] as $index => $contact) {
            /** @var Contact $contact */
            $rows[] = ['number' => $offset + $index + 1] + $this->row($contact);
        }

        $response->getBody()->write(json_encode([
            'last_page' => $listing['last_page'],
            'data'      => $rows,
        ], self::JSON_FLAGS));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function back(Response $response): Response
    {
        return $response->withStatus(303)->withHeader('Location', Url::path('/contactos'));
    }

    /**
     * Página del listado. La reusa el alta inválida, que necesita devolver el
     * modal abierto con lo tipeado (S7): el contexto de la página se arma en
     * un solo lugar y no se puede desincronizar entre las dos entradas.
     *
     * @param array<string, mixed> $extra
     */
    private function renderIndex(Request $request, Response $response, array $extra = [], int $status = 200): Response
    {
        $response = $this->render($request, $response, 'contacts/index.twig', [
            'active' => 'contacts',
            // El badge cuenta en la base: las filas ya no viajan en el HTML, así
            // que un `count($rows)` sobre un array local ya no existe. El
            // listado completo lo pide la tabla a /contactos/datos.
            'contacts_count' => Contact::query()->count(),
            'breadcrumbs' => [
                ['label' => 'Inicio', 'href' => Url::path('/')],
                ['label' => 'Contactos', 'icon' => 'contacts', 'current' => true],
            ],
        ] + $extra);

        return $status === 200 ? $response : $response->withStatus($status);
    }

    /**
     * Página de edición. `form_values` y `errors` los lee contacts/_form.twig
     * (S7): con ellos el formulario se repinta con lo tipeado y el mensaje; sin
     * ellos sale con lo guardado, como cualquier apertura normal.
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
        $response = $this->render($request, $response, 'contacts/edit.twig', [
            'active' => 'contacts',
            'contact' => $contact,
            'breadcrumbs' => [
                ['label' => 'Inicio', 'href' => Url::path('/')],
                ['label' => 'Contactos', 'href' => Url::path('/contactos')],
                ['label' => 'Editar', 'current' => true],
            ],
        ] + $extra);

        return $status === 200 ? $response : $response->withStatus($status);
    }

    /**
     * Query ordenada según los sorters del request.
     *
     * Tabulator 6.7.1 manda `sort[i][field]` y `sort[i][dir]` como arrays
     * anidados en el query string: el generador de URLs del bundle serializa
     * los objetos de parámetros con corchetes (`sort%5B0%5D%5Bfield%5D`), así
     * que aquí llega `[['field' => 'name', 'dir' => 'asc']]`.
     *
     * Se aplican TODOS los campos de la whitelist, en orden de llegada: con
     * shift+click el usuario arma un orden compuesto y quedarse con el primero
     * lo rompería. Un sorter fuera de la whitelist se descarta en silencio —sin
     * error y sin orden— porque el cliente no tiene por qué romperse por una
     * columna asumida y el servidor no tiene por qué adivinar qué quiso decir.
     * Sin ningún sorter válido el default es el listado de siempre: id
     * descendente, lo último arriba.
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
     * Errores por campo de un alta o una edición; vacío cuando pasa.
     *
     * Reemplaza al viejo `valid()`, que mezclaba la validación con el flash: el
     * fallo sólo dejaba un mensaje suelto y los errores por campo —lo que la
     * vista necesita para marcar el input (S4)— se perdían. Quien llama decide
     * qué hacer con ellos.
     *
     * El nombre NO es `errors()` a propósito: `Validator::errors()` es un
     * accesor de sólo lectura y este helper valida (construye un validador y
     * recorre reglas). Dos cosas con el mismo nombre y distinto costo invitan a
     * leerlo como gratis.
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
            'edit_url' => Url::path('/contactos/' . $id . '/edit'),
            'delete_url' => Url::path('/contactos/' . $id . '/delete'),
        ];
    }
}
