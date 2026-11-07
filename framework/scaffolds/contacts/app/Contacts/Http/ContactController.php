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

final class ContactController extends Controller
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Las acciones de contactos redirigen a /contactos; el flash de esa
     * acción se muestra como toast al cargar la página, con el mismo host
     * que usa el demo de tareas en sus swaps HTMX.
     */
    protected function usesToastFlash(): bool
    {
        return true;
    }

    public function index(Request $request, Response $response): Response
    {
        $rows = [];
        foreach (Contact::query()->orderByDesc('id')->get() as $contact) {
            /** @var Contact $contact */
            $rows[] = $this->row($contact);
        }

        return $this->render($request, $response, 'contacts/index.twig', [
            'active' => 'contacts',
            'contacts_count' => count($rows),
            'contacts_json' => json_encode($rows, self::JSON_FLAGS),
            'breadcrumbs' => [
                ['label' => 'Inicio', 'href' => Url::path('/')],
                ['label' => 'Contactos', 'icon' => 'contacts', 'current' => true],
            ],
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();

        if (!$this->valid($data)) {
            return $this->back($response);
        }

        Contact::create($this->attributes($data));
        Flash::set('success', 'Contacto creado.');

        return $this->back($response);
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $contact = Contact::find((int) $args['id']);

        if ($contact === null) {
            Flash::set('error', 'El contacto no existe.');

            return $this->back($response);
        }

        return $this->render($request, $response, 'contacts/edit.twig', [
            'active' => 'contacts',
            'contact' => $contact,
            'breadcrumbs' => [
                ['label' => 'Inicio', 'href' => Url::path('/')],
                ['label' => 'Contactos', 'href' => Url::path('/contactos')],
                ['label' => 'Editar', 'current' => true],
            ],
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $contact = Contact::find((int) $args['id']);

        if ($contact === null) {
            Flash::set('error', 'El contacto no existe.');

            return $this->back($response);
        }

        $data = (array) $request->getParsedBody();
        if (!$this->valid($data)) {
            return $this->back($response);
        }

        $contact->fill($this->attributes($data))->save();
        Flash::set('success', 'Contacto actualizado.');

        return $this->back($response);
    }

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
     * @param array<string, mixed> $data
     */
    private function valid(array $data): bool
    {
        $validator = Validator::make($data, [
            'name' => 'required|max:120',
            'email' => 'required|email|max:190',
            'phone' => 'max:50',
        ]);

        if ($validator->fails()) {
            Flash::set('error', $validator->firstError() ?? 'Datos inválidos.');
            return false;
        }

        return true;
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
