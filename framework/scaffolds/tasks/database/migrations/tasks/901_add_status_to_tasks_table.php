<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Suma el estado y su fecha de finalización.
 *
 * Capa migración. `status` guarda `pendiente` o `completado`, y `completed_at` la
 * fecha en que se completó. `done` sigue en la tabla hasta que la 902 mueve sus
 * datos.
 *
 * Para tu entidad, hazlo así: cuando el modelo de un dato cambia, migración nueva.
 */
final class AddStatusToTasksTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tasks')
            // Con default: las filas que ya existen quedan `pendiente` sin un
            // UPDATE aparte.
            ->addColumn('status', 'string', ['default' => 'pendiente', 'limit' => 20])
            // Nula mientras la tarea está pendiente; el toggle la escribe al
            // completar y la limpia al volver atrás.
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
