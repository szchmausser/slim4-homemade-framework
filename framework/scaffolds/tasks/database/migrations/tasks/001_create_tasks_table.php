<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTasksTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tasks')
            ->addColumn('title', 'string', ['limit' => 120])
            ->addColumn('done', 'boolean', ['default' => false])
            ->addTimestamps()
            ->create();
    }
}
