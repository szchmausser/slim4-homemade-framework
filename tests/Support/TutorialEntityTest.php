<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Contrato del tutorial: el recorrido declara los identificadores de la entidad
 * en inglés (`notes`), no los de la versión anterior en castellano.
 *
 * El tutorial no es una copia declarada, así que `docs:check` no lo mira: un
 * identificador viejo pegado en la prosa no rompe ningún gate, y el lector que
 * lo copia termina con una clase o una ruta que no coincide con el resto del
 * recorrido. Este test cubre ese agujero.
 *
 * Los patrones son literales y sensibles a mayúsculas a propósito. La prosa
 * castellana dice «notas» y «Nota creada.» todo el tiempo, y las capturas se
 * llaman `04-2-menu-*-notas.png`: ninguna de esas es un identificador. Por eso
 * lo que se busca es la ruta (`/notas`), el nombre de clase (`NotasTable`) o la
 * ruta de archivo (`pages/notas/`), nunca la palabra suelta.
 */
final class TutorialEntityTest extends TestCase
{
    private const TUTORIAL = __DIR__ . '/../../docs/tutorial-crud.md';

    /**
     * Identificadores anteriores al renombrado, tal como aparecerían si alguien
     * revirtiera el paso: clase, migración, carpeta, tests, archivo de rutas y
     * URL. `Nota` y `notas` sueltos no entran: son prosa y texto visible.
     */
    private const RETIRADOS = [
        'NotaController',
        'CreateNotasTable',
        '006_create_notas_table',
        'pages/notas/',
        'tests/Notas',
        'routes/notas',
        '/notas',
    ];

    /**
     * Identificadores que el recorrido tiene que declarar (S4).
     *
     * `title` y `body` van entre comillas porque así se declaran como columnas y
     * campos: la palabra suelta también aparece en `<title>` del HTML estático,
     * que no dice nada sobre el esquema.
     */
    private const VIGENTES = [
        'NoteController',
        'CreateNotesTable',
        '006_create_notes_table.php',
        'pages/notes/',
        'Tests\\Notes\\Http',
        '/notes',
        "'title'",
        "'body'",
    ];

    public function test_el_tutorial_no_arrastra_identificadores_en_castellano(): void
    {
        $contenido = self::tutorial();
        $fallas = [];

        foreach (preg_split('/\R/', $contenido) ?: [] as $i => $linea) {
            foreach (self::RETIRADOS as $token) {
                if (!str_contains($linea, $token)) {
                    continue;
                }

                $fallas[] = basename(self::TUTORIAL) . ':' . ($i + 1) . ' nombra `' . $token . '`.';
            }
        }

        self::assertSame(
            [],
            $fallas,
            "El tutorial volvió a los identificadores en castellano:\n" . implode("\n", $fallas)
        );
    }

    public function test_el_tutorial_declara_los_identificadores_en_ingles(): void
    {
        $contenido = self::tutorial();
        $faltan = [];

        foreach (self::VIGENTES as $token) {
            if (!str_contains($contenido, $token)) {
                $faltan[] = $token;
            }
        }

        self::assertSame(
            [],
            $faltan,
            'El tutorial dejó de declarar identificadores del recorrido: ' . implode(', ', $faltan)
        );
    }

    /** El guardián sólo sirve si además revisó el documento del recorrido. */
    public function test_el_archivo_revisado_es_el_tutorial(): void
    {
        $contenido = self::tutorial();

        self::assertGreaterThan(1000, strlen($contenido), 'docs/tutorial-crud.md no tiene el recorrido.');
        self::assertStringContainsString('composer demo:notes:install', $contenido);
    }

    private static function tutorial(): string
    {
        $contenido = @file_get_contents(self::TUTORIAL);

        self::assertIsString($contenido, 'No pude leer docs/tutorial-crud.md: el test pasaría por vacío.');

        return $contenido;
    }
}
