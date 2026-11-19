<?php

declare(strict_types=1);

namespace Tests\Tasks\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * El esquema de tareas solo existe cuando el demo está instalado.
 *
 * Capa tests. Vive en el scaffold y no en `tests/Support` para que la suite base no
 * exija la tabla `tasks`. Para tu entidad, copia el archivo y cambia las columnas
 * esperadas.
 */
final class TaskSchemaTest extends TestCase
{
    public function testTasksNoLongerCarryTheDeadDoneColumn(): void
    {
        $pdo = Capsule::connection()->getPdo();
        // `PRAGMA table_info` devuelve las columnas reales de la tabla.
        $columns = $pdo->query("PRAGMA table_info('tasks')")->fetchAll(PDO::FETCH_COLUMN, 1);

        self::assertContains('status', $columns);
        self::assertContains('completed_at', $columns);
        self::assertNotContains('done', $columns, 'tasks.done fue reemplazada por status (migracion 005).');
    }
}
