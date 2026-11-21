<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Regresión del removedor del scaffold: no debe confiar solo en el número de
 * versión de phinxlog. Una migración propia con la versión 900 no es la
 * migración 900 del demo y debe sobrevivir a `demo:tasks:remove`.
 */
final class DemoInstallerTest extends TestCase
{
    private string $projectRoot;

    private string $root;

    private string $db;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->root = sys_get_temp_dir() . '/slim4-demo-installer-'
            . getmypid() . '-' . bin2hex(random_bytes(4));
        $this->db = $this->root . '/database/test.sqlite';
        $this->createFixtureTree();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    /**
     * Arbol minimo para que `scripts/demo.php` corra contra una copia:
     * scaffold, migraciones base, phinx.php y un autoload que apunta al
     * vendor real. Nada de esto toca el proyecto.
     */
    private function createFixtureTree(): void
    {
        foreach ([
            'framework/scaffolds',
            'database/migrations',
            'database/seeds',
            'config/navigation',
            'storage/cache/twig',
            'app',
            'routes',
            'resources/views',
            'tests',
            'public/assets',
            'vendor',
        ] as $dir) {
            $path = $this->root . '/' . $dir;
            if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \RuntimeException("No se pudo crear {$path}");
            }
        }

        $this->copyTree(
            $this->projectRoot . '/framework/scaffolds',
            $this->root . '/framework/scaffolds'
        );

        foreach (glob($this->projectRoot . '/database/migrations/*.php') ?: [] as $migration) {
            copy($migration, $this->root . '/database/migrations/' . basename($migration));
        }

        copy($this->projectRoot . '/phinx.php', $this->root . '/phinx.php');

        $autoload = var_export($this->projectRoot . '/vendor/autoload.php', true);
        $prefix = var_export('App' . chr(92), true);
        $tempApp = var_export($this->root . '/app/', true);

        // El autoloader real resuelve Framework y vendor; el PSR-4 de App
        // se antepone al del proyecto para que el demo instalado en el arbol
        // temporal gane sobre una copia real que pueda existir.
        file_put_contents(
            $this->root . '/vendor/autoload.php',
            '<?php' . PHP_EOL
            . '$loader = require ' . $autoload . ';' . PHP_EOL
            . '$loader->addPsr4(' . $prefix . ', ' . $tempApp . ', true);' . PHP_EOL
        );
    }

    private function copyTree(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException("No existe {$source}");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new \RuntimeException("No se pudo crear {$target}");
                }
            } elseif (!copy($item->getPathname(), $target)) {
                throw new \RuntimeException("No se pudo copiar {$item->getPathname()}");
            }
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }

    public function test_remove_no_borra_una_migracion_del_usuario_con_la_misma_version(): void
    {
        $this->createLog([
            [900, 'CreateRecetasTable'],
        ]);

        [$exit, $stdout, $stderr] = $this->runRemove();

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('no hay migraciones del demo aplicadas', $stdout);
        self::assertSame(['900:CreateRecetasTable'], $this->logRows());
    }

    public function test_remove_limpia_las_migraciones_y_la_tabla_del_demo(): void
    {
        $this->createLog([
            [900, 'CreateTasksTable'],
            [1, 'CreateUsersTable'],
            [2, 'CreateRememberTokensTable'],
            [901, 'AddStatusToTasksTable'],
            [902, 'DropDoneFromTasks'],
            [3, 'LoginAttempts'],
        ], withTasks: true);

        [$exit, $stdout, $stderr] = $this->runRemove();

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('tabla tasks eliminada', $stdout);
        self::assertSame([
            '1:CreateUsersTable',
            '2:CreateRememberTokensTable',
            '3:LoginAttempts',
        ], $this->logRows());
        self::assertFalse($this->hasTasksTable());
    }

    public function test_remove_limpia_el_demo_contacts_sin_tocar_las_migraciones_base(): void
    {
        $this->createLog([
            [1, 'CreateUsersTable'],
            [2, 'CreateRememberTokensTable'],
            [3, 'LoginAttempts'],
            [920, 'CreateContactsTable'],
        ], withContacts: true);

        [$exit, $stdout, $stderr] = $this->runRemove('contacts');

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('tabla contacts eliminada', $stdout);
        self::assertSame([
            '1:CreateUsersTable',
            '2:CreateRememberTokensTable',
            '3:LoginAttempts',
        ], $this->logRows());
        self::assertFalse($this->hasContactsTable());
    }

    public function test_install_revierte_los_archivos_si_la_db_falla(): void
    {
        // Una tabla `notas` preexistente hace fallar la migración del demo: el
        // install tiene que revertir los archivos, no dejarlos a medias.
        $this->createLog([], withNotas: true);
        $this->removeDemo('notas');

        try {
            [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notas']);

            self::assertNotSame(0, $exit);
            self::assertStringContainsString('Install revertido', $stderr);
            // No se fija el texto de Phinx: según qué otra migración de `notas`
            // exista, el error es la tabla duplicada o el nombre de clase
            // duplicado. Lo que importa es que la DB falló y los archivos se
            // revirtieron.
            self::assertStringContainsString('La DB fallo:', $stderr);
            foreach ($this->installedPaths('notas') as $relative) {
                self::assertFileDoesNotExist($this->root . '/' . $relative, 'Quedó residuo: ' . $relative);
            }
        } finally {
            $this->removeDemo('notas');
        }
    }

    public function test_un_install_a_medias_no_bloquea_el_reintento(): void
    {
        // Simula un Ctrl+C después de copiar los archivos: el reintento tiene
        // que avisar de los archivos ya instalados, no chocar contra la
        // migración del propio demo.
        $this->createLog([]);
        $this->removeDemo('notas');

        try {
            [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
            self::assertSame(0, $exit);

            [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notas']);

            self::assertNotSame(0, $exit);
            self::assertStringNotContainsString('Colisión de migraciones', $stderr);
            self::assertStringContainsString('ya tiene archivos instalados', $stderr);
        } finally {
            $this->removeDemo('notas');
        }
    }

    public function test_install_force_restaura_el_archivo_preexistente_si_la_db_falla(): void
    {
        $relative = 'app/Notas/Models/Nota.php';
        $path = $this->root . '/' . $relative;
        $original = "<?php // editado por el usuario
";

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $original);

        // La tabla notas preexistente hace fallar la migracion del demo.
        $this->createLog([], withNotas: true);

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notas', '--force']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('Install revertido', $stderr);
        self::assertSame($original, file_get_contents($path));
    }

    public function test_remove_se_niega_a_borrar_archivos_editados_sin_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $relative = 'app/Notas/Models/Nota.php';
        $path = $this->root . '/' . $relative;
        file_put_contents($path, "<?php // editado
");

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notas', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('archivos editados', $stderr);
        self::assertFileExists($path);
        self::assertSame("<?php // editado
", file_get_contents($path));
    }

    public function test_remove_permite_el_scaffold_actualizado_sin_marcarlo_como_editado(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        // El scaffold avanza después de instalar: el archivo instalado queda
        // viejo, pero nadie lo editó. El manifiesto lo prueba, así que ni el
        // remove ni el install deben pedir --force por eso.
        $relative = 'routes/notas.php';
        $this->advanceScaffold($relative);

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notas', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('desactualizado', $stdout);
        self::assertFileDoesNotExist($this->root . '/' . $relative);
    }

    public function test_install_actualiza_un_archivo_desactualizado_sin_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $relative = 'routes/notas.php';
        $this->advanceScaffold($relative);

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notas', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('Actualizados desde el scaffold', $stdout);
        self::assertSame(
            file_get_contents($this->root . '/framework/scaffolds/notas/' . $relative),
            file_get_contents($this->root . '/' . $relative)
        );
    }

    public function test_remove_sin_manifiesto_sigue_pidiendo_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        // Un demo instalado antes de este cambio no tiene manifiesto: sin él no
        // se puede distinguir «quedó viejo» de «lo editaron», así que remove
        // mantiene el comportamiento anterior.
        $manifest = $this->root . '/storage/demo-state/notas.json';
        self::assertFileExists($manifest);
        self::assertTrue(unlink($manifest));

        $this->advanceScaffold('routes/notas.php');

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notas', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('archivos editados', $stderr);
        self::assertFileExists($this->root . '/routes/notas.php');
    }

    public function test_remove_borra_el_manifiesto(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $manifest = $this->root . '/storage/demo-state/notas.json';
        self::assertFileExists($manifest);

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notas', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertFileDoesNotExist($manifest);
    }

    /**
     * Simula que el scaffold avanzó después de la instalación: el archivo
     * instalado queda desactualizado sin que el usuario lo haya tocado.
     */
    private function advanceScaffold(string $relative): void
    {
        $path = $this->root . '/framework/scaffolds/notas/' . $relative;
        $content = (string) file_get_contents($path);
        file_put_contents($path, $content . "\n// linea nueva del scaffold\n");
    }

    /**
     * @param list<array{0: int, 1: string}> $rows
     */
    private function createLog(array $rows, bool $withTasks = false, bool $withContacts = false, bool $withNotas = false): void
    {
        $pdo = new PDO('sqlite:' . $this->db, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec(
            'CREATE TABLE phinxlog ('
            . 'version INTEGER, migration_name TEXT, '
            . 'start_time TEXT, end_time TEXT, breakpoint INTEGER DEFAULT 0)'
        );

        $insert = $pdo->prepare('INSERT INTO phinxlog (version, migration_name) VALUES (?, ?)');
        foreach ($rows as [$version, $name]) {
            $insert->execute([$version, $name]);
        }

        if ($withTasks) {
            $pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, title TEXT)');
            $pdo->exec("INSERT INTO tasks (title) VALUES ('demo')");
        }

        if ($withContacts) {
            $pdo->exec('CREATE TABLE contacts (id INTEGER PRIMARY KEY, name TEXT)');
            $pdo->exec("INSERT INTO contacts (name) VALUES ('demo')");
        }

        if ($withNotas) {
            // Sin la columna `cuerpo`: la migración del demo no puede crearla y
            // falla, que es lo que el test de rollback necesita.
            $pdo->exec('CREATE TABLE notas (id INTEGER PRIMARY KEY, titulo TEXT)');
        }

        $pdo = null;
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function runRemove(string $demo = 'tasks'): array
    {
        $env = getenv();
        $env['DB_DATABASE'] = $this->db;
        $env['DEMO_ROOT'] = $this->root;

        $process = proc_open(
            [PHP_BINARY, $this->projectRoot . '/scripts/demo.php', 'remove', $demo, '--no-files'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
            $env
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: int, 1: string, 2: string}
     */
    private function runDemo(array $args): array
    {
        $env = getenv();
        $env['DB_DATABASE'] = $this->db;
        $env['DEMO_ROOT'] = $this->root;

        $process = proc_open(
            array_merge([PHP_BINARY, $this->projectRoot . '/scripts/demo.php'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
            $env
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    private function removeDemo(string $demo): void
    {
        $this->runDemo(['remove', $demo, '--no-db']);
    }

    /**
     * Rutas que instala un demo, derivadas del scaffold para no mantener una
     * copia a mano.
     *
     * @return list<string>
     */
    private function installedPaths(string $demo): array
    {
        $scaffold = $this->root . '/framework/scaffolds/' . $demo;
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scaffold, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $paths[] = str_replace(
                str_replace('\\', '/', $scaffold) . '/',
                '',
                str_replace('\\', '/', $file->getPathname())
            );
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private function logRows(): array
    {
        $pdo = new PDO('sqlite:' . $this->db);
        $rows = $pdo->query(
            "SELECT version || ':' || migration_name FROM phinxlog ORDER BY version"
        )->fetchAll(PDO::FETCH_COLUMN);
        $pdo = null;

        return array_map('strval', $rows);
    }

    private function hasTasksTable(): bool
    {
        $pdo = new PDO('sqlite:' . $this->db);
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'tasks'"
        )->fetchColumn();
        $pdo = null;

        return $count > 0;
    }

    private function hasContactsTable(): bool
    {
        $pdo = new PDO('sqlite:' . $this->db);
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'contacts'"
        )->fetchColumn();
        $pdo = null;

        return $count > 0;
    }

    public function test_install_con_un_editado_y_otro_desactualizado_sigue_pidiendo_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $stale = 'routes/notas.php';
        $edited = 'app/Notas/Models/Nota.php';
        $this->advanceScaffold($stale);

        $editedPath = $this->root . '/' . $edited;
        file_put_contents($editedPath, "<?php // editado por el usuario\n");

        $manifest = $this->root . '/storage/demo-state/notas.json';
        $manifestBefore = (string) file_get_contents($manifest);

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notas', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString($edited, $stderr);
        self::assertStringNotContainsString($stale, $stderr);
        self::assertSame("<?php // editado por el usuario\n", file_get_contents($editedPath));
        self::assertSame($manifestBefore, (string) file_get_contents($manifest));
    }

    public function test_remove_con_un_editado_y_otro_desactualizado_no_borra_nada(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $this->advanceScaffold('routes/notas.php');
        $edited = 'app/Notas/Models/Nota.php';
        file_put_contents($this->root . '/' . $edited, "<?php // editado\n");
        $before = $this->installedHashes();

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notas', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString($edited, $stderr);
        self::assertSame($before, $this->installedHashes());
        self::assertStringNotContainsString('removido', $stdout);
    }

    public function test_remove_no_files_conserva_el_manifiesto(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $manifest = $this->root . '/storage/demo-state/notas.json';
        $before = (string) file_get_contents($manifest);

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notas', '--no-files', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertSame($before, (string) file_get_contents($manifest));
        self::assertFileExists($this->root . '/routes/notas.php');
    }

    public function test_install_revierte_el_manifiesto_si_la_db_falla(): void
    {
        [$exit] = $this->runDemo(['install', 'notas', '--no-db']);
        self::assertSame(0, $exit);

        $manifest = $this->root . '/storage/demo-state/notas.json';
        $before = (string) file_get_contents($manifest);
        $installed = $this->installedHashes();

        $this->createLog([], withNotas: true);
        $this->advanceScaffold('routes/notas.php');

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notas']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('Install revertido', $stderr);
        self::assertSame($before, (string) file_get_contents($manifest));
        self::assertSame($installed, $this->installedHashes());
    }

    /** @return array<string, string> relativo => sha256 (o '-' si falta) */
    private function installedHashes(): array
    {
        $hashes = [];
        foreach ($this->installedPaths('notas') as $relative) {
            $path = $this->root . '/' . $relative;
            $hashes[$relative] = is_file($path) ? (string) hash_file('sha256', $path) : '-';
        }

        return $hashes;
    }
}
