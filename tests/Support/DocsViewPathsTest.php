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

                // Una frase de historia sólo vale si el contexto está pegado a
                // la ruta: «`partials/` ya no existe» o «`header.twig` era …».
                // Mirar la línea entera dejaba pasar instrucciones reales que
                // mencionaban «antes» en otra parte de la frase.
                if (self::esHistoria($linea)) {
                    continue;
                }

                self::fail(basename($doc) . ':' . ($i + 1) . ' nombra una ruta retirada: ' . trim($linea));
            }

            $revisadas++;
        }

        self::assertGreaterThan(0, $revisadas, 'No se revisó ningún documento: el test estaría pasando en vacío.');
    }

    /**
     * ¿La línea habla de una ruta retirada como historia y no como instrucción?
     * Se mira el contexto pegado al token, no la línea entera.
     */
    public static function esHistoria(string $linea): bool
    {
        $n = preg_match_all(self::RETIRADAS, $linea, $m, PREG_OFFSET_CAPTURE);
        if ($n === false || $n === 0) {
            return false;
        }

        foreach ($m[0] as [$token, $offset]) {
            $antes = substr($linea, max(0, $offset - 24), min(24, $offset));
            $despues = substr($linea, $offset, 40);

            if (preg_match('/(ya no|era|desaparece|antes)\s*$/u', $antes) === 1) {
                continue;
            }
            if (preg_match('/(ya no|desaparece)/u', $despues) === 1) {
                continue;
            }

            return false;
        }

        return true;
    }

    public function test_la_frase_de_historia_no_tapa_una_ruta_viva(): void
    {
        self::assertFalse(
            self::esHistoria('- Cada formulario incluye `partials/_csrf.twig` antes de los campos ocultos.'),
            '«antes» en otra parte de la frase no convierte una instrucción en historia.'
        );
        self::assertTrue(
            self::esHistoria('`partials/` ya no existe: usá layouts/parts o components/.'),
            'Una ruta seguida de «ya no» sí es historia.'
        );
    }

    public function test_cada_copia_de_la_guia_apunta_a_un_archivo_existente(): void
    {
        $raiz = dirname(__DIR__, 2);

        // El tutorial declara copias de una entidad que el lector crea (pages/notes/…):
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
