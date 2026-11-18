<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Contrato de estructura de los scaffolds.
 *
 * `ViewStructureTest` mira `resources/views`, pero los demos se instalan desde
 * la carpeta de vistas de cada scaffold. Si un scaffold se queda con el árbol
 * viejo, la instalación produce una vista que el contrato de la app rechaza y
 * nadie se entera hasta que alguien instala el demo.
 */
final class ScaffoldViewStructureTest extends TestCase
{
    public function test_los_scaffolds_instalan_bajo_pages(): void
    {
        $encontrados = glob(__DIR__ . '/../../framework/scaffolds/*/resources/views/*', GLOB_ONLYDIR) ?: [];

        self::assertNotSame([], $encontrados, 'No encontré vistas de scaffold: el test estaría pasando en vacío.');

        foreach ($encontrados as $dir) {
            self::assertSame(
                'pages',
                basename($dir),
                $dir . ' no está bajo resources/views/pages/.'
            );
        }
    }
}
