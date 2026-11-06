<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusToTasksTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('tasks')
            ->addColumn('status', 'string', ['default' => 'pendiente', 'limit' => 20])
            ->addColumn('completed_at', 'timestamp', ['null' => true, 'default' => null])
            ->update();
    }
}
