<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRememberTokensTable extends AbstractMigration
{
    public function change(): void
    {
        // Sin id autoincrement: el selector ES la clave (lookup directo).
        // expires_at como entero unix: sin dramas de timezone al comparar.
        $this->table('remember_tokens', ['id' => false, 'primary_key' => ['selector']])
            ->addColumn('selector', 'string', ['limit' => 48])
            ->addColumn('user_id', 'integer')
            ->addColumn('hashed_validator', 'string', ['limit' => 255])
            ->addColumn('expires_at', 'integer')
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
