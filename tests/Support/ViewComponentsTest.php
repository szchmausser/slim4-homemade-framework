<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Contrato de composición: las vistas usan los componentes que ya existen en
 * vez de reescribir su markup.
 *
 * El caso que lo motivó: `pages/auth/login.twig` repetía label + input a mano
 * teniendo `components/form/field.twig`, y cinco CTA de página armaban
 * `class="btn …"` con el icono a la derecha. Lo segundo era un hueco del
 * componente (`button.twig` sólo ponía el icono al inicio), no un capricho de
 * las vistas: ahora acepta `icon_position: 'end'`.
 *
 * Excepciones deliberadas, con su razón:
 * - El chrome del shell (`layouts/parts/{topbar,sidebar,toasts}.twig`) usa
 *   botones con directivas de Alpine (`x-bind`, `@click`, `swap`). `button.twig`
 *   no las acepta a propósito: si las aceptara, dejaría de ser un átomo de
 *   presentación y cualquier vista podría meterle comportamiento.
 * - `components/ui/modal.twig` dibuja su propio botón de cierre: está dentro de
 *   la capa de componentes.
 * - Los `<svg>` con `x-show` y el par `swap` sol/luna son estado de la interfaz
 *   y estilo `fill`, no glifos del registro (que es `stroke`).
 */
final class ViewComponentsTest extends TestCase
{
    private const RAIZ = __DIR__ . '/../..';

    private const CHROME = [
        'resources/views/layouts/parts/topbar.twig',
        'resources/views/layouts/parts/sidebar.twig',
        'resources/views/layouts/parts/toasts.twig',
    ];

    private const BOTON_PROPIO = 'resources/views/components/ui/button.twig';

    private const MODAL = 'resources/views/components/ui/modal.twig';

    private const ICONOS = [
        'resources/views/components/ui/icon.twig',
        'resources/views/components/ui/logo.twig',
    ];

    public function test_las_vistas_usan_el_boton_del_proyecto(): void
    {
        $fallas = [];

        foreach (self::vistas() as $rel => $lineas) {
            if ($rel === self::BOTON_PROPIO || in_array($rel, self::CHROME, true) || $rel === self::MODAL) {
                continue;
            }

            foreach ($lineas as $n => $linea) {
                if (preg_match('/class="[^"]*\bbtn\b/', $linea) === 1) {
                    $fallas[] = $rel . ':' . ($n + 1) . ' escribe un botón a mano; usá components/ui/button.twig.';
                }
            }
        }

        self::assertSame([], $fallas, implode("\n", $fallas));
    }

    public function test_los_campos_usan_el_componente_de_campo(): void
    {
        $fallas = [];

        foreach (self::vistas() as $rel => $lineas) {
            if (str_starts_with($rel, 'resources/views/components/form/')) {
                continue;
            }
            // El theme toggle del topbar es un checkbox con `swap`: no es un campo.
            if ($rel === 'resources/views/layouts/parts/topbar.twig') {
                continue;
            }

            foreach ($lineas as $n => $linea) {
                if (preg_match('/<input\b/', $linea) === 1 && preg_match('/type="(checkbox|radio|hidden)"/', $linea) !== 1) {
                    $fallas[] = $rel . ':' . ($n + 1) . ' escribe un input a mano; usá components/form/field.twig.';
                }
            }
        }

        self::assertSame([], $fallas, implode("\n", $fallas));
    }

    public function test_los_dialogos_usan_el_modal_del_proyecto(): void
    {
        $fallas = [];

        foreach (self::vistas() as $rel => $lineas) {
            if ($rel === self::MODAL) {
                continue;
            }

            foreach ($lineas as $n => $linea) {
                if (preg_match('/<dialog\b/', $linea) === 1) {
                    $fallas[] = $rel . ':' . ($n + 1) . ' escribe un <dialog> a mano; usá components/ui/modal.twig.';
                }
            }
        }

        self::assertSame([], $fallas, implode("\n", $fallas));
    }

    public function test_los_iconos_salen_del_registro(): void
    {
        $fallas = [];

        foreach (self::vistas() as $rel => $lineas) {
            if (in_array($rel, self::ICONOS, true) || in_array($rel, self::CHROME, true)) {
                continue;
            }

            foreach ($lineas as $n => $linea) {
                if (preg_match('/<svg\b/', $linea) === 1) {
                    $fallas[] = $rel . ':' . ($n + 1) . ' dibuja un svg a mano; usá components/ui/icon.twig.';
                }
            }
        }

        self::assertSame([], $fallas, implode("\n", $fallas));
    }

    /**
     * Vistas con sus líneas, sin los comentarios de Twig: un `<dialog>` dentro
     * de un `{# … #}` es documentación, no markup.
     *
     * @return array<string, list<string>>
     */
    private static function vistas(): array
    {
        $dirs = [self::RAIZ . '/resources/views'];
        foreach (glob(self::RAIZ . '/framework/scaffolds/*/resources/views') ?: [] as $dir) {
            $dirs[] = $dir;
        }

        $vistas = [];
        foreach ($dirs as $dir) {
            $ri = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($ri as $archivo) {
                if ($archivo->getExtension() !== 'twig') {
                    continue;
                }

                $rel = str_replace('\\', '/', str_replace(self::RAIZ . '/', '', $archivo->getPathname()));
                $sinComentarios = (string) preg_replace_callback(
                    '/\{#.*?#\}/s',
                    static fn (array $m): string => (string) preg_replace('/[^\n]/', '', $m[0]),
                    (string) file_get_contents($archivo->getPathname())
                );

                $vistas[$rel] = explode("\n", $sinComentarios);
            }
        }

        self::assertNotSame([], $vistas, 'No encontré vistas: el test estaría pasando en vacío.');

        return $vistas;
    }
}
