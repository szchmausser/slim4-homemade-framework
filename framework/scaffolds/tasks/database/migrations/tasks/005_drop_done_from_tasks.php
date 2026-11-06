<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * `done` (001) y `status` (004) duplicaban el mismo estado y solo `status`
 * se usa desde entonces: la columna quedo muerta y el modelo la seguia
 * arrastrando en $fillable/$casts. Antes de tirarla se migran los datos de
 * quien venga de la v1, para no perder tareas completadas.
 */
final class DropDoneFromTasks extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(
            "UPDATE tasks SET status = 'completado', completed_at = COALESCE(completed_at, updated_at) "
            . "WHERE done = 1 AND status <> 'completado'"
        );

        // SQLite >= 3.35 soporta DROP COLUMN desde 2021; Phinx 0.13 no lo
        // implementa para su adapter SQLite, asi que va en SQL directo.
        $this->execute('ALTER TABLE tasks DROP COLUMN done');
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE tasks ADD COLUMN done INTEGER NOT NULL DEFAULT 0');
        $this->execute("UPDATE tasks SET done = CASE WHEN status = 'completado' THEN 1 ELSE 0 END");
    }
}
