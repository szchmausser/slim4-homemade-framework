<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotasTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('notas')
            ->addColumn('titulo', 'string', ['limit' => 120])
            ->addColumn('cuerpo', 'text', ['null' => true])
            ->addTimestamps()
            ->create();
    }
}
