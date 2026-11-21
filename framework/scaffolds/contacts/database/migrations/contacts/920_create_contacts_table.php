<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Esquema de la tabla `contacts`: la única fuente de sus columnas.
 *
 * Phinx la aplica con `composer migrate` (o con el instalador del demo) en el
 * orden de su número; el modelo `Contact` declara los mismos nombres y el seeder
 * los llena.
 *
 * Para tu entidad crea una migración con el siguiente número libre y no edites
 * una ya aplicada: en las bases existentes no vuelve a correr.
 */
final class CreateContactsTable extends AbstractMigration
{
    public function change(): void
    {
        // Los límites de cada columna son los mismos `max` que valida
        // `ContactController::validationErrors()`.
        $this->table('contacts')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
            ->addTimestamps()
            ->create();
    }
}
