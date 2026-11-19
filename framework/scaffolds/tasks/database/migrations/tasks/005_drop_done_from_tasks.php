<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Retira `tasks.done`, sin uso desde que `status` describe el estado.
 *
 * Capa migración. Una columna no se elimina sin mover antes sus datos, y una
 * migración ya aplicada no se edita: este archivo corrige a la 001. Se escribe a
 * mano (`up`/`down`) porque el borrado de columnas no es reversible solo.
 *
 * Para tu entidad, el patrón es este: corregir con una migración nueva.
 */
final class DropDoneFromTasks extends AbstractMigration
{
    public function up(): void
    {
        // Backfill antes del DROP: una fila de la versión anterior que estaba
        // completada conserva su estado, y `completed_at` hereda `updated_at`
        // cuando era nula.
        $this->execute(
            "UPDATE tasks SET status = 'completado', completed_at = COALESCE(completed_at, updated_at) "
            . "WHERE done = 1 AND status <> 'completado'"
        );

        // Phinx no implementa `DROP COLUMN` en su adapter de SQLite, así que va en
        // SQL directo.
        $this->execute('ALTER TABLE tasks DROP COLUMN done');
    }

    // Reconstruye `done` desde `status` para que la migración sea reversible.
    public function down(): void
    {
        $this->execute('ALTER TABLE tasks ADD COLUMN done INTEGER NOT NULL DEFAULT 0');
        $this->execute("UPDATE tasks SET done = CASE WHEN status = 'completado' THEN 1 ELSE 0 END");
    }
}
