<?php

declare(strict_types=1);

use App\Tasks\Models\Task;
use Phinx\Seed\AbstractSeed;

/**
 * Siembra las mismas 100 tareas demo, para bases que ya existen.
 *
 * Capa seeder. `TaskSeeder` corre al instalar el demo; este archivo sirve para
 * ejecutarlo a mano sobre una base que ya tenía las tareas. No corta cuando hay
 * filas: `firstOrCreate` hace que cada corrida agregue sólo lo que falta.
 *
 * Para tu entidad, la lección es la clave de idempotencia: re-ejecutar no duplica.
 */
final class DemoTasksSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../../bootstrap/app.php';

        $created = 0;
        for ($i = 1; $i <= 100; $i++) {
            // `wasRecentlyCreated` sólo es true en la fila que se acaba de crear:
            // así el total impreso ignora las que ya estaban.
            if (Task::firstOrCreate(['title' => "Tarea demo {$i}"])->wasRecentlyCreated) {
                $created++;
            }
        }

        echo "DemoTasksSeeder: {$created} nuevas (100 esperadas en base vacía)." . PHP_EOL;
    }
}
