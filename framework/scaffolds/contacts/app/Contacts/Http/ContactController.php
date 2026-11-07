<?php

declare(strict_types=1);

namespace App\Contacts\Http;

use App\Contacts\Models\Contact;
use Framework\Http\Controller;
use Framework\Http\Flash;
use Framework\Http\Url;
use Framework\Validation\Validator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class ContactController extends Controller
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

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
        $errors = $this->errors($data);

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
        $errors = $this->errors($data);

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
        $rows = [];
        foreach (Contact::query()->orderByDesc('id')->get() as $contact) {
            /** @var Contact $contact */
            $rows[] = $this->row($contact);
        }

        $response = $this->render($request, $response, 'contacts/index.twig', [
            'active' => 'contacts',
            'contacts_count' => count($rows),
            'contacts_json' => json_encode($rows, self::JSON_FLAGS),
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
     * Errores por campo de un alta o una edición; vacío cuando pasa.
     *
     * Reemplaza al viejo `valid()`, que mezclaba la validación con el flash: el
     * fallo sólo dejaba un mensaje suelto y los errores por campo —lo que la
     * vista necesita para marcar el input (S4)— se perdían. Quien llama decide
     * qué hacer con ellos.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> campo => mensaje
     */
    private function errors(array $data): array
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
