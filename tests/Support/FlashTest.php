<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Flash;
use PHPUnit\Framework\TestCase;

final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_devuelve_el_mensaje_y_lo_consume(): void
    {
        Flash::set('error', 'algo salio mal');

        self::assertSame('algo salio mal', Flash::get('error'));
        self::assertNull(Flash::get('error'));
    }

    public function test_un_valor_no_string_no_rompe_el_render(): void
    {
        $_SESSION['flash']['error'] = ['no', 'es', 'string'];

        self::assertNull(Flash::get('error'));
        self::assertArrayNotHasKey('error', $_SESSION['flash']);
    }
}
