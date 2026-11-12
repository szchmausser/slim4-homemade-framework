<?php

declare(strict_types=1);

namespace Tests\Tasks\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * El esquema de tareas solo existe cuando el scaffold demo está instalado.
 * Vive aquí, no en tests/Support: la suite base no debe exigir la tabla tasks.
 */
final class TaskSchemaTest extends TestCase
{
    public function testTasksNoLongerCarryTheDeadDoneColumn(): void
    {
        $pdo = Capsule::connection()->getPdo();
        $columns = $pdo->query("PRAGMA table_info('tasks')")->fetchAll(PDO::FETCH_COLUMN, 1);

        self::assertContains('status', $columns);
        self::assertContains('completed_at', $columns);
        self::assertNotContains('done', $columns, 'tasks.done fue reemplazada por status (migracion 005).');
    }
}
