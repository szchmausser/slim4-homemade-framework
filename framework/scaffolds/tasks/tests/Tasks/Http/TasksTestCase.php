<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Tasks\Models\Task;
use Tests\Support\HttpTestCase;

/**
 * La suite base limpia users; la del demo suma tasks. Cuando el scaffold no
 * está instalado, esta clase tampoco existe y la suite base no la referencia.
 */
abstract class TasksTestCase extends HttpTestCase
{
    /**
     * En el demo la página protegida es /tareas; los tests que no pasan path
     * esperan el formulario de esa pantalla, no el login (que ya redirige).
     */
    protected static function token(string $path = '/tareas'): array
    {
        return parent::token($path);
    }

    protected function tearDown(): void
    {
        Task::query()->delete();
        parent::tearDown();
    }
}
