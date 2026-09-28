<?php

declare(strict_types=1);

use App\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * Datos demo para desarrollo. Idempotente: si ya hay tareas no toca nada,
 * así re-correr el DatabaseSeeder (o seed:run pelado) nunca duplica.
 */
final class TaskSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../bootstrap/app.php';

        if (Task::query()->exists()) {
            echo "TaskSeeder: ya hay tareas, no se toca nada." . PHP_EOL;
            return;
        }

        foreach (['Comprar pan', 'Regar las plantas', 'Terminar la guía'] as $title) {
            Task::create(['title' => $title]);
        }

        echo "TaskSeeder: 3 tareas demo." . PHP_EOL;
    }
}
