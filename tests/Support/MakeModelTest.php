<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * `composer make:model` sigue la convención de Eloquent: la tabla se deriva con
 * `Str::snake(Str::pluralStudly())`, la misma función que usa `Model::getTable()`,
 * y `$table` sólo se escribe cuando la tabla se aparta de esa derivación.
 *
 * Esa convención es inglesa: acierta `Category` y `Lunes`, no `Ciudad`. Por eso
 * el plural español se pasa como segundo argumento, y por eso el test de
 * `Category` importa: una regla propia tipo «+s» daría `categorys`.
 *
 * Los docblocks tampoco se generan: dependen de las columnas reales.
 */
final class MakeModelTest extends TestCase
{
    private string $projectRoot;

    private string $root;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 2);
        $this->root = sys_get_temp_dir() . '/slim4-make-model-'
            . getmypid() . '-' . bin2hex(random_bytes(4));

        @mkdir($this->root . '/app/Models', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_no_escribe_table_cuando_la_convencion_de_eloquent_alcanza(): void
    {
        [$exit, $stdout, $stderr] = $this->runCommand(['Nota']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('app/Models/Nota.php', $stdout);
        self::assertStringContainsString('Tabla: notas', $stdout);

        $modelo = (string) file_get_contents($this->root . '/app/Models/Nota.php');

        self::assertStringContainsString("namespace App\\Models;", $modelo);
        self::assertStringContainsString('class Nota extends Model', $modelo);
        self::assertStringContainsString('protected $fillable = [];', $modelo);
        self::assertStringNotContainsString('protected $table', $modelo);
    }

    public function test_acepta_snake_case_y_normaliza_el_nombre_de_clase(): void
    {
        [$exit, $stdout, $stderr] = $this->runCommand(['nota']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString(
            'class Nota extends Model',
            (string) file_get_contents($this->root . '/app/Models/Nota.php')
        );
    }

    public function test_escribe_table_cuando_la_tabla_se_aparta_de_la_convencion(): void
    {
        [$exit, $stdout, $stderr] = $this->runCommand(['Ciudad', 'ciudades']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString(
            "protected \$table = 'ciudades';",
            (string) file_get_contents($this->root . '/app/Models/Ciudad.php')
        );
    }

    public function test_no_reemplaza_la_convencion_de_eloquent_por_una_regla_propia(): void
    {
        // `Category` => `categories` y `Lunes` => `lunes` los resuelve Doctrine.
        // Una regla propia tipo «snake + s» daría `categorys` y `luness`.
        [$exit, $stdout, $stderr] = $this->runCommand(['Category']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('Tabla: categories', $stdout);
        self::assertStringNotContainsString(
            'protected $table',
            (string) file_get_contents($this->root . '/app/Models/Category.php')
        );

        [$exit, $stdout, $stderr] = $this->runCommand(['Lunes']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('Tabla: lunes', $stdout);
        self::assertStringNotContainsString(
            'protected $table',
            (string) file_get_contents($this->root . '/app/Models/Lunes.php')
        );
    }

    public function test_sin_nombre_explica_el_uso_y_no_escribe_nada(): void
    {
        $antes = $this->models();

        [$exit, $stdout, $stderr] = $this->runCommand([]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('Uso:', $stderr);
        self::assertSame($antes, $this->models());
    }

    public function test_no_pisa_un_modelo_que_ya_existe(): void
    {
        $path = $this->root . '/app/Models/Nota.php';
        file_put_contents($path, "<?php // editado a mano\n");

        [$exit, $stdout, $stderr] = $this->runCommand(['Nota']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('ya existe', $stderr);
        self::assertSame("<?php // editado a mano\n", (string) file_get_contents($path));
    }

    public function test_nombre_invalido_no_escribe_nada(): void
    {
        $antes = $this->models();

        [$exit, $stdout, $stderr] = $this->runCommand(['123']);

        self::assertSame(1, $exit);
        self::assertStringContainsString('inválido', $stderr);
        self::assertSame($antes, $this->models());
    }

    /**
     * @param list<string> $arguments
     * @return array{0: int, 1: string, 2: string}
     */
    private function runCommand(array $arguments): array
    {
        $env = getenv();
        $env['MAKE_MODEL_ROOT'] = $this->root;

        $process = proc_open(
            array_merge([PHP_BINARY, $this->projectRoot . '/scripts/make-model.php'], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->projectRoot,
            $env
        );

        if (!is_resource($process)) {
            self::fail('No se pudo ejecutar scripts/make-model.php');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /**
     * @return list<string>
     */
    private function models(): array
    {
        $files = [];

        foreach (glob($this->root . '/app/Models/*.php') ?: [] as $path) {
            $files[] = basename($path);
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
