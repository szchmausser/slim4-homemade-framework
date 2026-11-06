<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Regresión del removedor del scaffold: no debe confiar solo en el número de
 * versión de phinxlog. Una migración propia con la versión 004 no es la
 * migración 004 del demo y debe sobrevivir a `demo:remove`.
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

    /**
     * @param list<array{0: int, 1: string}> $rows
     */
    private function createLog(array $rows, bool $withTasks = false): void
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

        $pdo = null;
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function runRemove(): array
    {
        $env = getenv();
        $env['DB_DATABASE'] = $this->db;

        $process = proc_open(
            [PHP_BINARY, $this->root . '/scripts/demo.php', 'remove', '--no-files'],
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
}
