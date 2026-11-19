<?php

declare(strict_types=1);

namespace Tests\Contacts\Http;

use App\Contacts\Models\Contact;
use Tests\Support\HttpTestCase;

/**
 * Base de los tests del demo Contactos: deja la tabla `contacts` vacía al terminar
 * cada test.
 *
 * La extiende `ContactsTest`, que en `setUp()` entra con `loginAs()` y hereda de
 * `HttpTestCase` la app de prueba, `request()` y `token()`.
 *
 * Para tu entidad hereda de esta misma base y borra tu tabla en `tearDown()`: la
 * base de test es compartida, así que una fila olvidada cambia el `count()` siguiente.
 */
abstract class ContactsTestCase extends HttpTestCase
{
    protected function tearDown(): void
    {
        Contact::query()->delete();
        parent::tearDown();
    }
}
