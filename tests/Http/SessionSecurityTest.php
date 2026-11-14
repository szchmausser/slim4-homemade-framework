<?php

declare(strict_types=1);

namespace Tests\Http;

use Framework\Http\Middleware\SessionMiddleware;
use PHPUnit\Framework\TestCase;

final class SessionSecurityTest extends TestCase
{
    private bool $exists = false;

    private mixed $value = null;

    protected function setUp(): void
    {
        $this->exists = array_key_exists('SESSION_NAME', $_ENV);
        $this->value = $_ENV['SESSION_NAME'] ?? null;
        unset($_ENV['SESSION_NAME'], $_SERVER['SESSION_NAME']);
    }

    protected function tearDown(): void
    {
        if ($this->exists) {
            $_ENV['SESSION_NAME'] = $this->value;
        } else {
            unset($_ENV['SESSION_NAME']);
        }
    }

    public function test_session_name_elimina_caracteres_peligrosos(): void
    {
        $_ENV['SESSION_NAME'] = 'mi; app name';

        self::assertSame('miappname', SessionMiddleware::sessionName());
    }

    public function test_session_name_vacio_cae_al_default(): void
    {
        $_ENV['SESSION_NAME'] = '; ;';

        self::assertSame('mi_app_session', SessionMiddleware::sessionName());
    }

    public function test_los_timeouts_por_defecto_son_positivos(): void
    {
        self::assertSame(SessionMiddleware::DEFAULT_IDLE_TIMEOUT, SessionMiddleware::idleTimeout());
        self::assertSame(SessionMiddleware::DEFAULT_ABSOLUTE_TIMEOUT, SessionMiddleware::absoluteTimeout());
    }
}
