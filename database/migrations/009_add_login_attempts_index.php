<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * `Throttle::prune()` borra por attempted_at; el indice compuesto de 006
 * empieza por email, asi que ese DELETE hacia un scan completo. Este indice
 * cubre la poda y el limite por IP.
 */
final class AddLoginAttemptsIndex extends AbstractMigration
{
    public function change(): void
    {
        $this->table('login_attempts')
            ->addIndex(['attempted_at'], ['name' => 'login_attempts_attempted_at_index'])
            ->update();
    }
}
