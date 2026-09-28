<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Flash;
use PHPUnit\Framework\TestCase;

final class FlashTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function test_get_consume_el_valor(): void
    {
        Flash::set('error', 'Datos inválidos.');

        self::assertSame('Datos inválidos.', Flash::get('error'));
        self::assertNull(Flash::get('error'), 'el segundo get tiene que devolver null');
    }

    public function test_get_de_una_clave_inexistente_devuelve_null(): void
    {
        self::assertNull(Flash::get('nunca-setada'));
    }

    public function test_claves_distintas_no_se_pisan(): void
    {
        Flash::set('error', 'a');
        Flash::set('info', 'b');

        self::assertSame('a', Flash::get('error'));
        self::assertSame('b', Flash::get('info'));
    }
}
