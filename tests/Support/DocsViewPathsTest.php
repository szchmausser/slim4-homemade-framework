<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Docs\CodeBlocks;
use PHPUnit\Framework\TestCase;

/**
 * Contrato de documentación: ninguna copia declarada apunta a un archivo que se
 * movió o se borró.
 *
 * Antes, `CodeBlocks::resolvePath()` descartaba las rutas inexistentes, así que
 * una reorganización de vistas dejaba la guía de stack con ocho copias muertas y
 * `docs:check` seguía en verde. El resolvedor ya no las descarta; este test fija
 * el mismo criterio desde el lado de los documentos vivos.
 */
final class DocsViewPathsTest extends TestCase
{
    /** Rutas que ya no existen en el árbol de vistas. */
    private const RETIRADAS = '#(partials/|(?<!pages/)errors/(?:error|_htmx)\.twig|(?<!pages/)auth/login\.twig|resources/views/(?:home|tasks|contacts|notas)/)#';

    public function test_ningun_documento_vivo_nombra_rutas_de_vistas_retiradas(): void
    {
        $raiz = dirname(__DIR__, 2);
        $docs = [$raiz . '/README.md', $raiz . '/DESIGN.md'];

        foreach (glob($raiz . '/docs/*.md') ?: [] as $archivo) {
            $docs[] = $archivo;
        }

        $revisadas = 0;

        foreach ($docs as $doc) {
            foreach (preg_split('/\R/', (string) file_get_contents($doc)) ?: [] as $i => $linea) {
                if (preg_match(self::RETIRADAS, $linea) !== 1) {
                    continue;
                }

                // Las frases de historia («ya no existe», «era …») documentan el
                // cambio y no son una ruta viva.
                if (preg_match('/(ya no|era |desaparece|antes )/u', $linea) === 1) {
                    continue;
                }

                self::fail(basename($doc) . ':' . ($i + 1) . ' nombra una ruta retirada: ' . trim($linea));
            }

            $revisadas++;
        }

        self::assertGreaterThan(0, $revisadas, 'No se revisó ningún documento: el test estaría pasando en vacío.');
    }

    public function test_cada_copia_de_la_guia_apunta_a_un_archivo_existente(): void
    {
        $raiz = dirname(__DIR__, 2);

        // El tutorial declara copias de una entidad que el lector crea (pages/notas/…):
        // no existen en el repo hasta instalar el demo, así que se valida sólo la guía.
        foreach (['docs/guia-stack-php-termux.md'] as $rel) {
            foreach (CodeBlocks::parse((string) file_get_contents($raiz . '/' . $rel)) as $bloque) {
                if (preg_match('#`((?:resources/views|framework/scaffolds)/[A-Za-z0-9_./-]+\.twig)`#', $bloque['intro'], $m) !== 1) {
                    continue;
                }

                self::assertFileExists(
                    $raiz . '/' . $m[1],
                    $rel . ':' . $bloque['line'] . ' copia un archivo que se movió.'
                );
            }
        }
    }
}
