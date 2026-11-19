<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Crea la tabla `tasks` con sus primeras columnas.
 *
 * Capa migración (Phinx): es la única fuente del esquema. El modelo no declara la
 * tabla y los tests no la crean a mano. `addTimestamps()` agrega `created_at` y
 * `updated_at`, que Eloquent mantiene solo.
 *
 * Para tu entidad, copia el archivo con el número siguiente y cambia tus columnas.
 */
final class CreateTasksTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tasks')
            // 120 es el límite que valida el controlador con `max:120` y el que
            // declara `maxlength` en los formularios.
            ->addColumn('title', 'string', ['limit' => 120])
            // `done` nace acá y la migración 005 la retira: una migración ya
            // aplicada no se edita, se corrige con otra.
            ->addColumn('done', 'boolean', ['default' => false])
            ->addTimestamps()
            ->create();
    }
}
