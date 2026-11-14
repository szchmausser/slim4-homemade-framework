<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Support\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private const KEY = 'GSD_ENV_TEST';

    private bool $envExists = false;

    private bool $serverExists = false;

    private mixed $envValue = null;

    private mixed $serverValue = null;

    private string|false $processValue = false;

    protected function setUp(): void
    {
        $this->envExists = array_key_exists(self::KEY, $_ENV);
        $this->serverExists = array_key_exists(self::KEY, $_SERVER);
        $this->envValue = $_ENV[self::KEY] ?? null;
        $this->serverValue = $_SERVER[self::KEY] ?? null;
        $this->processValue = getenv(self::KEY);

        unset($_ENV[self::KEY], $_SERVER[self::KEY]);
        putenv(self::KEY);
    }

    protected function tearDown(): void
    {
        if ($this->envExists) {
            $_ENV[self::KEY] = $this->envValue;
        } else {
            unset($_ENV[self::KEY]);
        }

        if ($this->serverExists) {
            $_SERVER[self::KEY] = $this->serverValue;
        } else {
            unset($_SERVER[self::KEY]);
        }

        $this->processValue === false
            ? putenv(self::KEY)
            : putenv(self::KEY . '=' . $this->processValue);
    }

    public function test_prioriza_env_sobre_server_y_entorno_real(): void
    {
        $_ENV[self::KEY] = 'desde-env';
        $_SERVER[self::KEY] = 'desde-server';
        putenv(self::KEY . '=desde-getenv');

        self::assertSame('desde-env', Env::get(self::KEY));
    }

    public function test_usa_server_si_env_no_existe(): void
    {
        $_SERVER[self::KEY] = 'desde-server';
        putenv(self::KEY . '=desde-getenv');

        self::assertSame('desde-server', Env::get(self::KEY));
    }

    public function test_usa_el_entorno_real_si_no_hay_superglobales(): void
    {
        putenv(self::KEY . '=desde-getenv');

        self::assertSame('desde-getenv', Env::get(self::KEY));
    }

    public function test_bool_e_int_interpretan_los_valores(): void
    {
        $_ENV[self::KEY] = 'true';
        self::assertTrue(Env::bool(self::KEY));

        $_ENV[self::KEY] = '0';
        self::assertFalse(Env::bool(self::KEY, true));

        $_ENV[self::KEY] = '25';
        self::assertSame(25, Env::int(self::KEY, 10));
    }

    public function test_valor_vacio_y_ausente_caen_al_default(): void
    {
        self::assertSame('default', Env::get(self::KEY, 'default'));

        $_ENV[self::KEY] = '';
        self::assertSame('default', Env::get(self::KEY, 'default'));
    }
}
