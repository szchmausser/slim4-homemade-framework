<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fallos de login para throttling. Se guarda unix time (como expires_at de
 * remember_tokens): comparar la ventana no depende de la timezone.
 * El indice compuesto es el que usa el chequeo real: email + IP + ventana.
 */
final class LoginAttempts extends AbstractMigration
{
    public function change(): void
    {
        $this->table('login_attempts')
            ->addColumn('email', 'string', ['limit' => 160])
            ->addColumn('ip', 'string', ['limit' => 45])
            ->addColumn('attempted_at', 'integer')
            ->addIndex(['email', 'ip', 'attempted_at'])
            ->create();
    }
}
