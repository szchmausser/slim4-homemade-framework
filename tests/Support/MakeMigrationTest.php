<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * `composer make:migration` es el único que asigna versión: la calcula sobre el
 * rango bajo de la app y nunca pisa la banda reservada de los demos. Estos
 * tests fijan las dos mitades: el archivo que escribe y lo que NO escribe
 * cuando se niega.
 */
final class MakeMigrationTest extends TestCase
{
    private string $projectRoot;

    private string $root;

    /** @var array<string, string> ruta relativa => clase declarada */
    private array $band = [
        'framework/scaffolds/tasks/database/migrations/tasks/900_create_tasks_table.php' => 'ScaffoldTasksTable',
        'framework/scaffolds/notas/database/migrations/notas/910_create_notas_table.php' => 'ScaffoldNotasTable',
        'framework/scaffolds/contacts/database/migrations/contacts/920_create_contacts_table.php' => 'ScaffoldContactsTable',
    ];

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->root = sys_get_temp_dir() . '/slim4-make-migration-'
            . getmypid() . '-' . bin2hex(random_bytes(4));

        @mkdir($this->root . '/database/migrations', 0755, true);

        // Fixture hermético: el rango de la app se escribe acá y no se copia del
        // repo. Copiarlo ataba el test a cuántas migraciones reales existan, y
        // la primera migración que agregue quien usa el esqueleto lo rompía.
        foreach ([
            '001_create_users_table.php' => 'CreateUsersTable',
            '002_create_remember_tokens_table.php' => 'CreateRememberTokensTable',
            '003_login_attempts.php' => 'LoginAttempts',
            '004_add_login_attempts_index.php' => 'AddLoginAttemptsIndex',
            '005_add_rotated_at_to_remember_tokens.php' => 'AddRotatedAtToRememberTokens',
        ] as $relative => $class) {
            $this->writeMigration('database/migrations/' . $relative, $class);
        }

        foreach ($this->band as $relative => $class) {
            $this->writeMigration($relative, $class);
        }
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_escribe_el_siguiente_numero_libre_con_el_esqueleto_del_proyecto(): void
    {
        [$exit, $stdout, $stderr] = $this->runCommand(['CreateNotasTable']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('database/migrations/006_create_notas_table.php', $stdout);
        self::assertSame(
            $this->skeleton('CreateNotasTable'),
            (string) file_get_contents($this->root . '/database/migrations/006_create_notas_table.php')
        );
    }

    public function test_acepta_snake_case_y_normaliza_clase_y_archivo(): void
    {
        [$exit, $stdout, $stderr] = $this->runCommand(['create_notas_table']);

        self::assertSame(0, $exit, $stderr);
        self::assertFileExists($this->root . '/database/migrations/006_create_notas_table.php');
        self::assertStringContainsString(
            'final class CreateNotasTable extends AbstractMigration',
            (string) file_get_contents($this->root . '/database/migrations/006_create_notas_table.php')
        );
    }

    public function test_sin_nombre_explica_el_uso_y_no_escribe_nada(): void
    {
        $antes = $this->migrationFiles();

        [$exit, $stdout, $stderr] = $this->runCommand([]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Uso:', $stderr);
        self::assertSame($antes, $this->migrationFiles());
    }

    public function test_no_repite_una_clase_que_ya_existe(): void
    {
        $this->writeMigration(
            'database/migrations/006_otra_cosa.php',
            'CreateNotasTable'
        );
        $antes = $this->migrationFiles();

        [$exit, $stdout, $stderr] = $this->runCommand(['CreateNotasTable']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('CreateNotasTable', $stderr);
        self::assertSame($antes, $this->migrationFiles());
    }

    public function test_permite_una_clase_que_solo_existe_en_un_scaffold(): void
    {
        // El scaffold de notas declara `CreateNotasTable`, pero no está en el
        // espacio de Phinx hasta instalar el demo, y ese demo no puede convivir
        // con tu propia `notas`. Bloquear el nombre dejaría el paso 2 del
        // tutorial sin poder crear su migración.
        $this->writeMigration(
            'framework/scaffolds/notas/database/migrations/notas/910_create_notas_table.php',
            'CreateNotasTable'
        );

        [$exit, $stdout, $stderr] = $this->runCommand(['CreateNotasTable']);

        self::assertSame(0, $exit, $stderr);
        self::assertFileExists($this->root . '/database/migrations/006_create_notas_table.php');
        self::assertStringContainsString('910_create_notas_table.php', $stdout);
    }

    public function test_no_entra_en_la_banda_reservada_para_demos(): void
    {
        $this->writeMigration('database/migrations/899_relleno.php', 'Relleno');
        $antes = $this->migrationFiles();

        [$exit, $stdout, $stderr] = $this->runCommand(['CreateAlgoTable']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('900', $stderr);
        self::assertSame($antes, $this->migrationFiles());
    }

    public function test_no_reutiliza_una_version_que_ya_existe_fuera_del_rango_de_la_app(): void
    {
        $this->writeMigration(
            'framework/scaffolds/otro/database/migrations/otro/006_create_otro_table.php',
            'CreateOtroTable'
        );
        $antes = $this->migrationFiles();

        [$exit, $stdout, $stderr] = $this->runCommand(['CreateAlgoTable']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('006', $stderr);
        self::assertSame($antes, $this->migrationFiles());
    }

    /**
     * @param list<string> $arguments
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(array $arguments): array
    {
        $env = getenv();
        $env['MAKE_MIGRATION_ROOT'] = $this->root;

        $process = proc_open(
            array_merge([PHP_BINARY, $this->projectRoot . '/scripts/make-migration.php'], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->projectRoot,
            $env
        );

        if (!is_resource($process)) {
            self::fail('No se pudo ejecutar scripts/make-migration.php');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    private function skeleton(string $class): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\n"
            . "final class {$class} extends AbstractMigration\n{\n"
            . "    public function change(): void\n    {\n    }\n}\n";
    }

    private function writeMigration(string $relative, string $class): void
    {
        $path = $this->root . '/' . $relative;
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            self::fail("No se pudo crear {$dir}");
        }

        file_put_contents($path, $this->skeleton($class));
    }

    /**
     * @return list<string>
     */
    private function migrationFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = substr($file->getPathname(), strlen($this->root) + 1);
            }
        }

        sort($files);

        return $files;
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
}
