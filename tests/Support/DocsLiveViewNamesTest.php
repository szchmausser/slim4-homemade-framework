<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Contrato de nombres en la documentación viva.
 *
 * `DocsViewPathsTest` mira rutas retiradas; este mira el otro agujero: un
 * documento puede nombrar un archivo que ya no existe con su nombre suelto
 * (`_icon.twig`) o listar un `mkdir` con carpetas que el árbol ya no admite.
 * Ninguno de los dos rompe `docs:check`, porque no son copias declaradas.
 *
 * Se revisan README, DESIGN, AGENTS y THIRD-PARTY-NOTICES, que describen el
 * proyecto para quien lo lee, más la guía de stack.
 */
final class DocsLiveViewNamesTest extends TestCase
{
    /** Nombres de relleno que los documentos usan como plantilla. */
    private const PLACEHOLDERS = ['_nombre.twig'];

    public function test_cada_nombre_de_twig_en_la_documentacion_resuelve(): void
    {
        $raiz = dirname(__DIR__, 2);
        $revisadas = 0;
        $fallas = [];

        foreach (self::documentos($raiz) as $doc) {
            foreach (preg_split('/\R/', (string) file_get_contents($doc)) ?: [] as $i => $linea) {
                if (preg_match_all('/`([A-Za-z0-9_.\/-]+\.twig)`/', $linea, $m) === 0) {
                    continue;
                }

                foreach ($m[1] as $nombre) {
                    if (in_array($nombre, self::PLACEHOLDERS, true)) {
                        continue;
                    }

                    $revisadas++;

                    if (self::resuelve($raiz, $nombre)) {
                        continue;
                    }

                    $fallas[] = basename($doc) . ':' . ($i + 1) . ' nombra `' . $nombre . '`, que no existe.';
                }
            }
        }

        self::assertSame([], $fallas, "Nombres de vistas que no resuelven:\n" . implode("\n", $fallas));
        self::assertGreaterThan(0, $revisadas, 'No se revisó ningún nombre: el test estaría pasando en vacío.');
    }

    public function test_ningun_documento_recrea_carpetas_retiradas(): void
    {
        $raiz = dirname(__DIR__, 2);
        $fallas = [];

        foreach (self::documentos($raiz) as $doc) {
            foreach (preg_split('/\R/', (string) file_get_contents($doc)) ?: [] as $i => $linea) {
                if (preg_match('#resources/views/(partials|auth|errors)\b#', $linea) === 1) {
                    $fallas[] = basename($doc) . ':' . ($i + 1) . ' menciona una carpeta retirada: ' . trim($linea);
                }
            }
        }

        self::assertSame([], $fallas, "Carpetas que el árbol ya no admite:\n" . implode("\n", $fallas));
    }

    /** @return list<string> */
    private static function documentos(string $raiz): array
    {
        $docs = [
            $raiz . '/README.md',
            $raiz . '/DESIGN.md',
            $raiz . '/AGENTS.md',
            $raiz . '/THIRD-PARTY-NOTICES.md',
        ];

        foreach (glob($raiz . '/docs/*.md') ?: [] as $archivo) {
            $docs[] = $archivo;
        }

        return $docs;
    }

    private static function resuelve(string $raiz, string $nombre): bool
    {
        if (str_contains($nombre, '/')) {
            return is_file($raiz . '/' . $nombre)
                || is_file($raiz . '/resources/views/' . $nombre)
                || is_file($raiz . '/resources/views/pages/' . $nombre)
                || glob($raiz . '/framework/scaffolds/*/resources/views/' . $nombre) !== []
                || glob($raiz . '/framework/scaffolds/*/resources/views/pages/' . $nombre) !== [];
        }

        return glob($raiz . '/resources/views/*/' . $nombre) !== []
            || glob($raiz . '/resources/views/*/*/' . $nombre) !== []
            || glob($raiz . '/framework/scaffolds/*/resources/views/*/' . $nombre) !== []
            || glob($raiz . '/framework/scaffolds/*/resources/views/*/*/' . $nombre) !== [];
    }
}
