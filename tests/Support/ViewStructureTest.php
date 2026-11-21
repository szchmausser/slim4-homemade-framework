<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Contrato de estructura de `resources/views`.
 *
 * Una carpeta por pregunta, y ninguna respuesta ambigua:
 *
 * - `layouts/` es el esqueleto del documento, con sus fragmentos en `parts/`.
 * - `components/<familia>/` es lo reutilizable con contrato propio.
 * - `pages/` son las pantallas, y cada feature puede tener sus fragmentos con
 *   guion bajo (`pages/notes/_panel.twig`).
 *
 * Antes convivían `partials/layout/*`, `partials/_icon.twig` y `layouts/*`: tres
 * lugares plausibles para el shell y otros tantos para un átomo, con la
 * consiguiente duplicación. Este test convierte esa convención en algo que
 * falla en CI.
 */
final class ViewStructureTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../../resources/views';

    /** Familias de componentes admitidas. Una nueva entra acá con su razón. */
    private const FAMILIAS = ['ui', 'form', 'feedback', 'list', 'table', 'pagination'];

    public function test_la_raiz_de_views_solo_tiene_carpetas_de_rol(): void
    {
        $entradas = scandir(self::RAIZ) ?: [];
        $sueltas = array_values(array_filter(
            $entradas,
            static fn (string $e): bool => !in_array($e, ['.', '..'], true)
                && !is_dir(self::RAIZ . '/' . $e)
        ));

        self::assertSame([], $sueltas, 'Una vista en la raíz no tiene rol: va en pages/ o en components/.');
    }

    public function test_solo_existen_layouts_components_y_pages(): void
    {
        $dirs = array_values(array_filter(
            scandir(self::RAIZ) ?: [],
            static fn (string $e): bool => !in_array($e, ['.', '..'], true) && is_dir(self::RAIZ . '/' . $e)
        ));
        sort($dirs);

        self::assertSame(
            ['components', 'layouts', 'pages'],
            $dirs,
            'La raíz de resources/views sólo admite layouts/, components/ y pages/.'
        );
    }

    public function test_los_fragmentos_del_layout_viven_en_parts(): void
    {
        $dirs = array_values(array_filter(
            scandir(self::RAIZ . '/layouts') ?: [],
            static fn (string $e): bool => !in_array($e, ['.', '..'], true) && is_dir(self::RAIZ . '/layouts/' . $e)
        ));

        self::assertSame(['parts'], $dirs, 'Los fragmentos del shell van en layouts/parts/.');
    }

    public function test_los_componentes_estan_en_una_familia_conocida(): void
    {
        foreach (scandir(self::RAIZ . '/components') ?: [] as $entrada) {
            if (in_array($entrada, ['.', '..'], true) || !is_dir(self::RAIZ . '/components/' . $entrada)) {
                continue;
            }

            self::assertContains(
                $entrada,
                self::FAMILIAS,
                "La familia `components/{$entrada}` no está declarada en ViewStructureTest."
            );
        }

        foreach (self::FAMILIAS as $familia) {
            self::assertDirectoryExists(
                self::RAIZ . '/components/' . $familia,
                "Falta la familia `components/{$familia}`."
            );
        }
    }

    public function test_los_componentes_no_usan_guion_bajo(): void
    {
        $archivos = glob(self::RAIZ . '/components/*/*.twig') ?: [];

        self::assertNotSame([], $archivos, 'No encontré componentes: el test estaría pasando en vacío.');

        foreach ($archivos as $archivo) {
            self::assertStringStartsNotWith(
                '_',
                basename($archivo),
                basename($archivo) . ' usa guion bajo: el guion bajo es para fragmentos internos de una feature.'
            );
        }
    }

    public function test_el_esqueleto_no_vive_en_parts(): void
    {
        $sueltos = glob(self::RAIZ . '/pages/*.twig') ?: [];

        self::assertContains(
            realpath(self::RAIZ . '/pages/home.twig'),
            array_map(static fn (string $f): string|false => realpath($f), $sueltos),
            'La portada tiene que existir en pages/ para que el test no pase por vacío.'
        );
        self::assertDirectoryDoesNotExist(self::RAIZ . '/partials', 'partials/ se retiró: usá layouts/parts o components/.');
    }
}
