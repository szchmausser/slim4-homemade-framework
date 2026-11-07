<?php

declare(strict_types=1);

use App\Tasks\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * Compatibilidad y ejecución puntual de 100 tareas demo. `TaskSeeder` ya las
 * crea al instalar el demo; este seeder queda para bases existentes o para
 * correrlo a mano. Idempotente por título.
 */
final class DemoTasksSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../../bootstrap/app.php';

        $created = 0;
        for ($i = 1; $i <= 100; $i++) {
            if (Task::firstOrCreate(['title' => "Tarea demo {$i}"])->wasRecentlyCreated) {
                $created++;
            }
        }

        echo "DemoTasksSeeder: {$created} nuevas (100 esperadas en base vacía)." . PHP_EOL;
    }
}
