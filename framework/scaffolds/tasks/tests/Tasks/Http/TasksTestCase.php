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

    /**
     * El shell está dividido en partials; los contratos de toast/CSRF se
     * verifican sobre el conjunto, no sobre un único archivo.
     */
    protected function shellSource(): string
    {
        $root = dirname(__DIR__, 3);
        $files = [
            $root . '/resources/views/layouts/app.twig',
            $root . '/resources/views/partials/layout/head.twig',
            $root . '/resources/views/partials/layout/sidebar.twig',
            $root . '/resources/views/partials/layout/header.twig',
            $root . '/resources/views/partials/layout/toasts.twig',
            $root . '/resources/views/partials/layout/footer.twig',
        ];

        $source = '';
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            self::assertNotFalse($contents, 'No se pudo leer ' . $file);
            $source .= $contents;
        }

        return $source;
    }
}
