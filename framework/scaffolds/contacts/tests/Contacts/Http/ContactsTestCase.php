<?php

declare(strict_types=1);

namespace Tests\Contacts\Http;

use App\Contacts\Models\Contact;
use Tests\Support\HttpTestCase;

abstract class ContactsTestCase extends HttpTestCase
{
    protected function tearDown(): void
    {
        Contact::query()->delete();
        parent::tearDown();
    }
}
