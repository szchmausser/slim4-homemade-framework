<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users')
            ->addColumn('email', 'string', ['limit' => 160])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addIndex(['email'], ['unique' => true])
            ->addTimestamps()
            ->create();
    }
}
