<?php

declare(strict_types=1);

namespace Tests\Tasks\Support;

use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Una migración destructiva (`DROP COLUMN`) solo es segura si los datos viejos se
 * migran antes.
 *
 * Capa tests. Corre las migraciones hasta la 004, inserta una tarea con `done = 1`
 * como la dejaría la versión anterior, aplica la 005 y verifica el backfill y la
 * desaparición de la columna. Para tu entidad, copia el archivo y apunta a tu
 * migración destructiva.
 */
final class MigrationUpgradeTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        // Cada caso usa su propia base, con sufijo aleatorio, y la borra al terminar.
        $dir = dirname(__DIR__, 3) . '/storage/cache/tests';
        @mkdir($dir, 0777, true);
        $this->db = $dir . '/upgrade-' . bin2hex(random_bytes(4)) . '.sqlite';
    }

    protected function tearDown(): void
    {
        // SQLite deja los archivos WAL y SHM al lado de la base.
        foreach ([$this->db, $this->db . '-wal', $this->db . '-shm'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testDoneIsBackfilledBeforeTheColumnIsDropped(): void
    {
        $manager = new Manager($this->config(), new ArrayInput([]), new NullOutput());
        $manager->migrate('development', 4);

        $pdo = new PDO('sqlite:' . $this->db);
        $pdo->exec(
            "INSERT INTO tasks (title, done, status, created_at, updated_at) "
            . "VALUES ('vieja', 1, 'pendiente', '2026-01-01 00:00:00', '2026-02-02 03:04:05')"
        );

        $manager->migrate('development');

        $row = $pdo->query("SELECT status, completed_at FROM tasks WHERE title = 'vieja'")->fetch(PDO::FETCH_ASSOC);

        self::assertSame('completado', $row['status'], 'done=1 deberia haberse traducido a status=completado.');
        self::assertSame('2026-02-02 03:04:05', $row['completed_at'], 'completed_at deberia heredar updated_at cuando era null.');

        $columns = $pdo->query("PRAGMA table_info('tasks')")->fetchAll(PDO::FETCH_COLUMN, 1);
        self::assertNotContains('done', $columns);
    }

    /**
     * Carga `phinx.php` y lo apunta a la base temporal del caso.
     */
    private function config(): Config
    {
        $root = dirname(__DIR__, 3);
        $array = require $root . '/phinx.php';
        $array['environments']['development']['name'] = $this->db;
        $array['environments']['development']['suffix'] = '';

        return new Config($array, $root . '/phinx.php');
    }
}
