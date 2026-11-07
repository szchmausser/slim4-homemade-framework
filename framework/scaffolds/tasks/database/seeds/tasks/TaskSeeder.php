<?php

declare(strict_types=1);

use App\Tasks\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * Datos demo para desarrollo. Idempotente: si ya hay tareas no toca nada,
 * así re-correr el DatabaseSeeder (o seed:run pelado) nunca duplica.
 */
final class TaskSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../../bootstrap/app.php';

        if (Task::query()->exists()) {
            echo "TaskSeeder: ya hay tareas, no se toca nada." . PHP_EOL;
            return;
        }

        for ($index = 1; $index <= 100; $index++) {
            Task::create(['title' => "Tarea demo {$index}"]);
        }

        echo "TaskSeeder: 100 tareas demo." . PHP_EOL;
    }
}
