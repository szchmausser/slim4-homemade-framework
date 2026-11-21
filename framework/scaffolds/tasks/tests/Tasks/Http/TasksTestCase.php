<?php

declare(strict_types=1);

namespace Tests\Tasks\Http;

use App\Models\Task;
use Tests\Support\HttpTestCase;

/**
 * Base de los tests del demo: `app()`, `request()`, `token()` y `loginAs()` vienen
 * de `Tests\Support\HttpTestCase`, y acá se suma la limpieza de `tasks`.
 *
 * Capa tests. Cuando el scaffold no está instalado esta clase tampoco existe, así
 * que la suite base nunca depende de la tabla `tasks`.
 *
 * Para tu entidad, copia el archivo y cambia el modelo que se limpia.
 */
abstract class TasksTestCase extends HttpTestCase
{
    /**
     * En el demo la página protegida es `/tasks`, así que de ahí sale el token;
     * el login también renderiza el partial, pero esta ruta es la real.
     *
     * @return array{csrf_name: string, csrf_value: string}
     */
    protected static function token(string $path = '/tasks'): array
    {
        return parent::token($path);
    }

    // La tabla del demo se limpia antes de que la base borre los usuarios.
    protected function tearDown(): void
    {
        Task::query()->delete();
        parent::tearDown();
    }

    /**
     * El shell está dividido en partials: los contratos de avisos y de CSRF se
     * verifican sobre el conjunto, no sobre un archivo.
     */
    protected function shellSource(): string
    {
        // `dirname(__DIR__, 3)` sube de `tests/Tasks/Http` a la raíz de la app: el
        // shell se lee del proyecto instalado, no del scaffold.
        $root = dirname(__DIR__, 3);
        $files = [
            $root . '/resources/views/layouts/app.twig',
            $root . '/resources/views/layouts/parts/head.twig',
            $root . '/resources/views/layouts/parts/sidebar.twig',
            $root . '/resources/views/layouts/parts/topbar.twig',
            $root . '/resources/views/layouts/parts/toasts.twig',
            $root . '/resources/views/layouts/parts/footer.twig',
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
