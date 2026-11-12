<?php

declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * El backup es una promesa operativa: si la copia sale a medias, el respaldo
 * no sirve justo cuando se lo necesita. El script corre en un proyecto
 * temporal (scripts + vendor simulados) para no escribir ni podar los backups
 * reales de storage/backups.
 */
final class BackupTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/slim4-backup-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/scripts', 0777, true);
        copy(
            dirname(__DIR__, 2) . '/scripts/db-backup.php',
            $this->root . '/scripts/db-backup.php'
        );
        mkdir($this->root . '/vendor', 0777, true);
        file_put_contents(
            $this->root . '/vendor/autoload.php',
            '<?php return require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
        );
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_backup_produce_una_copia_integra(): void
    {
        $source = $this->root . '/source.sqlite';
        $this->seedDatabase($source);

        [$exit, $stdout, $stderr] = $this->runBackup($source);

        self::assertSame(0, $exit, "el backup falló: {$stderr}");

        $files = glob($this->root . '/storage/backups/*.sqlite') ?: [];
        self::assertCount(1, $files, 'tiene que quedar un único archivo .sqlite');

        // El aviso imprime la ruta tal como la resolvio el sistema: en Windows
        // mezcla \ y /. Se compara normalizado porque lo que importa es que
        // nombre el archivo que escribio, no con que separador lo escribio.
        // `realpath` ademas expande el nombre corto 8.3 de %TEMP% que puede
        // devolver sys_get_temp_dir(), y que el script ya resolvio al crear
        // storage/backups.
        $expected = realpath($files[0]) ?: $files[0];
        self::assertStringContainsString(
            str_replace('\\', '/', $expected),
            str_replace('\\', '/', $stdout),
            'el aviso tiene que nombrar el archivo de backup creado'
        );
        self::assertMatchesRegularExpression('/\(\d+ bytes\)/', $stdout);

        $copy = new PDO('sqlite:' . $files[0]);
        self::assertSame('ok', $copy->query('PRAGMA integrity_check')->fetchColumn());
        self::assertSame('sembrado', $copy->query('SELECT name FROM items')->fetchColumn());
    }

    public function test_retencion_conserva_los_ultimos_keep_y_no_toca_otros_archivos(): void
    {
        $source = $this->root . '/source.sqlite';
        $this->seedDatabase($source);

        $backups = $this->root . '/storage/backups';
        mkdir($backups, 0777, true);
        foreach (['20200101-000000', '20200102-000000', '20200103-000000', '20200104-000000', '20200105-000000'] as $old) {
            file_put_contents("{$backups}/{$old}.sqlite", 'backup viejo');
        }
        // Legado: backups en directorio y archivos que no siguen el patrón.
        mkdir($backups . '/20200101-000000');
        file_put_contents($backups . '/manual.sqlite', 'no lo toca la retención');

        [$exit, , $stderr] = $this->runBackup($source, keep: 3);

        self::assertSame(0, $exit, "el backup falló: {$stderr}");

        $names = array_map('basename', glob($backups . '/*.sqlite') ?: []);
        sort($names);
        $managed = array_values(array_filter(
            $names,
            static fn (string $name): bool => (bool) preg_match('/^\d{8}-\d{6}(?:-\d+)?\.sqlite$/', $name)
        ));

        self::assertCount(3, $managed);
        self::assertNotContains('20200101-000000.sqlite', $names);
        self::assertNotContains('20200102-000000.sqlite', $names);
        self::assertNotContains('20200103-000000.sqlite', $names);
        self::assertContains('20200104-000000.sqlite', $names);
        self::assertContains('20200105-000000.sqlite', $names);
        self::assertContains('manual.sqlite', $names);
        self::assertDirectoryExists($backups . '/20200101-000000');
    }

    public function test_db_inexistente_falla_con_mensaje_claro(): void
    {
        $missing = $this->root . '/no-existe.sqlite';

        [$exit, , $stderr] = $this->runBackup($missing);

        self::assertSame(1, $exit);
        self::assertStringContainsString('No hay base para respaldar', $stderr);
        self::assertStringContainsString($missing, $stderr);
    }

    private function seedDatabase(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $pdo->exec("INSERT INTO items (name) VALUES ('sembrado')");
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function runBackup(string $database, ?int $keep = null): array
    {
        $env = getenv();
        $env['DB_DATABASE'] = $database;
        if ($keep !== null) {
            $env['KEEP'] = (string) $keep;
        }

        $process = proc_open(
            // GitHub Actions usa variables_order=GPCS: forzarlo acá hace que
            // el test cubra el fallback getenv() y no solo el camino local.
            [PHP_BINARY, '-d', 'variables_order=GPCS', $this->root . '/scripts/db-backup.php'],
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
        $exit = proc_close($process);

        return [$exit, (string) $stdout, (string) $stderr];
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }
}
