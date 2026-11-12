<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Regresión del removedor del scaffold: no debe confiar solo en el número de
 * versión de phinxlog. Una migración propia con la versión 004 no es la
 * migración 004 del demo y debe sobrevivir a `demo:tasks:remove`.
 */
final class DemoInstallerTest extends TestCase
{
    private string $root;
    private string $db;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $this->db = sys_get_temp_dir() . '/demo-installer-' . bin2hex(random_bytes(6)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        foreach ([$this->db, $this->db . '-wal', $this->db . '-shm'] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    public function test_remove_no_borra_una_migracion_del_usuario_con_la_misma_version(): void
    {
        $this->createLog([
            [4, 'CreateRecetasTable'],
        ]);

        [$exit, $stdout, $stderr] = $this->runRemove();

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('no hay migraciones del demo aplicadas', $stdout);
        self::assertSame(['4:CreateRecetasTable'], $this->logRows());
    }

    public function test_remove_limpia_las_migraciones_y_la_tabla_del_demo(): void
    {
        $this->createLog([
            [1, 'CreateTasksTable'],
            [2, 'CreateUsersTable'],
            [3, 'CreateRememberTokensTable'],
            [4, 'AddStatusToTasksTable'],
            [5, 'DropDoneFromTasks'],
            [6, 'LoginAttempts'],
        ], withTasks: true);

        [$exit, $stdout, $stderr] = $this->runRemove();

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('tabla tasks eliminada', $stdout);
        self::assertSame([
            '2:CreateUsersTable',
            '3:CreateRememberTokensTable',
            '6:LoginAttempts',
        ], $this->logRows());
        self::assertFalse($this->hasTasksTable());
    }

    public function test_remove_limpia_el_demo_contacts_sin_tocar_las_migraciones_base(): void
    {
        $this->createLog([
            [2, 'CreateUsersTable'],
            [3, 'CreateRememberTokensTable'],
            [6, 'LoginAttempts'],
            [8, 'CreateContactsTable'],
        ], withContacts: true);

        [$exit, $stdout, $stderr] = $this->runRemove('contacts');

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('tabla contacts eliminada', $stdout);
        self::assertSame([
            '2:CreateUsersTable',
            '3:CreateRememberTokensTable',
            '6:LoginAttempts',
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
            self::assertStringContainsString('table `notas` already exists', $stderr);
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

        $process = proc_open(
            [PHP_BINARY, $this->root . '/scripts/demo.php', 'remove', $demo, '--no-files'],
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

        $process = proc_open(
            array_merge([PHP_BINARY, $this->root . '/scripts/demo.php'], $args),
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
}
