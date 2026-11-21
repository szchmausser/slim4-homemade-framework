<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * El espacio de versiones de Phinx es único y global: las migraciones de la app
 * y las de cada scaffold comparten contador aunque el demo no esté instalado.
 * Phinx sólo lo detecta tarde y con un mensaje opaco («Duplicate migration» al
 * migrar, «Colisión de migraciones» al instalar), así que la regla vive acá:
 *
 *   - la app ocupa el rango bajo contiguo desde 001, sin huecos;
 *   - los demos ocupan bandas reservadas desde 900, para que el crecimiento de
 *     la app no abra un salto en `ls database/migrations/`;
 *   - ninguna versión se repite entre app y scaffolds.
 *
 * El espacio de DECLARACIÓN son los `*.php` de primer nivel de
 * `database/migrations/` más todo lo que cuelga de
 * `framework/scaffolds/<demo>/database/migrations/`.
 * Los subdirectorios de la app (`database/migrations/<demo>/`) quedan afuera a
 * propósito: son la copia de un scaffold instalado, es decir la misma
 * migración copiada y no una segunda declaración. Contarlas no agrega ninguna
 * versión nueva y rompe el flujo que AGENTS.md exige (instalar un demo y correr
 * `composer test`): el guardián vería 900 dos veces, una por el scaffold y otra
 * por su copia.
 */
final class MigrationNumberingTest extends TestCase
{
    private const DEMO_BAND_START = 900;

    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_los_nombres_son_tres_digitos_mas_snake_case(): void
    {
        $files = $this->migrationFiles();

        self::assertNotSame([], $files, 'No se encontró ninguna migración.');

        foreach ($files as $relative => $path) {
            self::assertMatchesRegularExpression(
                '/^\d{3}_[a-z0-9_]+\.php$/',
                basename($path),
                "{$relative}: el nombre tiene que ser NNN_snake_case.php"
            );
        }
    }

    public function test_la_app_numera_sin_huecos_desde_cero_uno(): void
    {
        $versions = $this->appVersions();
        sort($versions);

        self::assertSame(
            range(1, count($versions)),
            $versions,
            'Falta un número en el rango de la app: una migración se movió de lugar o se borró'
        );
    }

    public function test_ninguna_version_se_repite_entre_app_y_scaffolds(): void
    {
        $porVersion = [];
        foreach ($this->migrationFiles() as $relative => $path) {
            $porVersion[$this->versionOf($path)][] = $relative;
        }

        $repetidas = [];
        foreach ($porVersion as $version => $files) {
            if (count($files) > 1) {
                $repetidas[] = $version . ': ' . implode(', ', $files);
            }
        }

        self::assertSame(
            [],
            $repetidas,
            'Dos migraciones con la misma versión: Phinx aborta con «Duplicate migration»'
        );
    }

    public function test_la_app_no_entra_en_la_banda_reservada_para_demos(): void
    {
        $versions = $this->appVersions();

        self::assertLessThan(
            self::DEMO_BAND_START,
            max($versions),
            'La app alcanzó la banda reservada para demos: hay que mudar la banda antes de seguir'
        );
    }

    public function test_las_copias_instaladas_de_un_demo_viven_en_la_banda(): void
    {
        // Una copia instalada con la numeración vieja no la ve el guardián de
        // arriba (la excluye a propósito). En la suite por defecto el que
        // aborta primero es `tests/bootstrap.php`, que migra todo y muere con
        // «Duplicate migration»; este test es el diagnóstico legible para
        // cuando se corre sin ese bootstrap. La recuperación está en la guía, 11.
        $instaladas = [];

        foreach (glob($this->root . '/database/migrations/*', GLOB_ONLYDIR) ?: [] as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $path) {
                if ($this->versionOf($path) < self::DEMO_BAND_START) {
                    $instaladas[] = substr($path, strlen($this->root) + 1);
                }
            }
        }

        self::assertSame(
            [],
            $instaladas,
            'Copia instalada de demo con numeración vieja: borra database/migrations/<demo>/ y corre la recuperación de la guía, cap. 11'
        );
    }

    /**
     * @return array<string, string> ruta relativa al proyecto => ruta absoluta
     */
    private function migrationFiles(): array
    {
        $files = [];

        foreach ($this->appMigrationFiles() as $path) {
            $files[substr($path, strlen($this->root) + 1)] = $path;
        }

        foreach ($this->scaffoldMigrationRoots() as $root) {
            foreach ($this->phpFilesUnder($root) as $path) {
                $files[substr($path, strlen($this->root) + 1)] = $path;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Migraciones de la app: sólo los archivos sueltos. Los subdirectorios son
     * copias instaladas de un scaffold y pertenecen al espacio del scaffold.
     *
     * @return list<string>
     */
    private function appMigrationFiles(): array
    {
        $files = glob($this->root . '/database/migrations/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Los scaffolds declaran dentro de `database/migrations/<demo>/`.
     *
     * @return list<string>
     */
    private function scaffoldMigrationRoots(): array
    {
        $roots = [];

        foreach (glob($this->root . '/framework/scaffolds/*', GLOB_ONLYDIR) ?: [] as $scaffold) {
            $path = $scaffold . '/database/migrations';
            if (is_dir($path)) {
                $roots[] = $path;
            }
        }

        return $roots;
    }

    /**
     * Sólo los archivos sueltos de la app: los subdirectorios son demos
     * instalados, que viven en la banda alta y no corren el contador.
     *
     * @return list<int>
     */
    private function appVersions(): array
    {
        $versions = [];

        foreach ($this->appMigrationFiles() as $path) {
            $versions[] = $this->versionOf($path);
        }

        self::assertNotSame([], $versions, 'No hay migraciones de la app.');

        return $versions;
    }

    /**
     * @return list<string>
     */
    private function phpFilesUnder(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function versionOf(string $path): int
    {
        preg_match('/^(\d+)/', basename($path), $matches);

        return (int) ($matches[1] ?? 0);
    }
}
