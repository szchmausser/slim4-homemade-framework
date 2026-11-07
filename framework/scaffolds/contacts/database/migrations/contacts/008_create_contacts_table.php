<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateContactsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('contacts')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
            ->addTimestamps()
            ->create();
    }
}
