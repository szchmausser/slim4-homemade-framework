<?php

declare(strict_types=1);

use App\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * 100 tareas demo para ejercitar la paginación. A propósito FUERA del
 * DatabaseSeeder (una instalación fresca no quiere 100 filas de ruido):
 * se corre solo con -s DemoTasksSeeder. Idempotente por título.
 */
final class DemoTasksSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../bootstrap/app.php';

        $created = 0;
        for ($i = 1; $i <= 100; $i++) {
            if (Task::firstOrCreate(['title' => "Tarea demo {$i}"])->wasRecentlyCreated) {
                $created++;
            }
        }

        echo "DemoTasksSeeder: {$created} nuevas (100 esperadas en base vacía)." . PHP_EOL;
    }
}
