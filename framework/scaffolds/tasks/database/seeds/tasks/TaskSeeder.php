<?php

declare(strict_types=1);

use App\Tasks\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * Siembra 100 tareas demo para desarrollo.
 *
 * Capa seeder (Phinx): datos, no esquema. La tabla la crea la migración y el demo
 * lo ejecuta al instalarse. Es idempotente: si ya hay filas no toca nada, así que
 * re-ejecutarlo no duplica.
 *
 * Para tu entidad, copia el archivo, cambia el modelo y el texto de las filas.
 */
final class TaskSeeder extends AbstractSeed
{
    public function run(): void
    {
        // Phinx ejecuta el seeder fuera de la app: sin este require no hay
        // Eloquent ni configuración cargada.
        require_once __DIR__ . '/../../../bootstrap/app.php';

        if (Task::query()->exists()) {
            echo "TaskSeeder: ya hay tareas, no se toca nada." . PHP_EOL;
            return;
        }

        // 100 filas: con 10 por página el listado ya muestra paginación.
        for ($index = 1; $index <= 100; $index++) {
            Task::create(['title' => "Tarea demo {$index}"]);
        }

        echo "TaskSeeder: 100 tareas demo." . PHP_EOL;
    }
}
