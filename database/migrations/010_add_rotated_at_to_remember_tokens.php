<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Tombstone de rotacion para detectar el replay de un token ya usado.
 * La fila no se borra al rotar: se marca rotated_at y se conserva durante
 * una ventana corta. Si vuelve a aparecer, se quema toda la familia.
 */
final class AddRotatedAtToRememberTokens extends AbstractMigration
{
    public function change(): void
    {
        $this->table('remember_tokens')
            ->addColumn('rotated_at', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['rotated_at'], ['name' => 'remember_tokens_rotated_at_index'])
            ->update();
    }
}
