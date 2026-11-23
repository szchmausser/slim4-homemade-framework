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
            'lang',
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

        // Catálogos base: el fixture tiene el `lang/` del esqueleto, así que
        // instalar y quitar un demo se prueba contra el mismo layout del repo.
        foreach (glob($this->projectRoot . '/lang/*.php') ?: [] as $catalog) {
            copy($catalog, $this->root . '/lang/' . basename($catalog));
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

    public function test_install_deja_el_demo_tasks_en_el_layout_plano(): void
    {
        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'tasks', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertFileExists($this->root . '/app/Models/Task.php');
        self::assertFileExists($this->root . '/app/Http/Controllers/TaskController.php');
        self::assertDirectoryDoesNotExist($this->root . '/app/Tasks');
    }

    public function test_install_deja_el_demo_contacts_en_el_layout_plano(): void
    {
        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'contacts', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertFileExists($this->root . '/app/Models/Contact.php');
        self::assertFileExists($this->root . '/app/Http/Controllers/ContactController.php');
        self::assertDirectoryDoesNotExist($this->root . '/app/Contacts');
    }

    public function test_remove_conserva_los_directorios_compartidos_de_la_app(): void
    {
        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'tasks', '--no-db']);
        self::assertSame(0, $exit, $stderr);

        // Los tres los crea la instalación del demo y son de la app cuando el
        // demo se va: un remove que los borre deja el esqueleto sin destinos.
        $shared = ['app/Models', 'app/Http/Controllers', 'resources/views/pages'];
        foreach ($shared as $dir) {
            self::assertDirectoryExists($this->root . '/' . $dir, 'La instalación no creó ' . $dir);
        }

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'tasks', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('removido', $stdout);
        foreach ($shared as $dir) {
            self::assertDirectoryExists($this->root . '/' . $dir, 'El remove borró el directorio compartido ' . $dir);
        }
    }

    public function test_install_revierte_los_archivos_si_la_db_falla(): void
    {
        // Una tabla `notes` preexistente hace fallar la migración del demo: el
        // install tiene que revertir los archivos, no dejarlos a medias.
        $this->createLog([], withNotes: true);
        $this->removeDemo('notes');

        try {
            [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes']);

            self::assertNotSame(0, $exit);
            self::assertStringContainsString('Install revertido', $stderr);
            // No se fija el texto de Phinx: según qué otra migración de `notes`
            // exista, el error es la tabla duplicada o el nombre de clase
            // duplicado. Lo que importa es que la DB falló y los archivos se
            // revirtieron.
            self::assertStringContainsString('La DB fallo:', $stderr);
            foreach ($this->installedPaths('notes') as $relative) {
                self::assertFileDoesNotExist($this->root . '/' . $relative, 'Quedó residuo: ' . $relative);
            }
        } finally {
            $this->removeDemo('notes');
        }
    }

    public function test_un_install_a_medias_no_bloquea_el_reintento(): void
    {
        // Simula un Ctrl+C después de copiar los archivos: el reintento tiene
        // que avisar de los archivos ya instalados, no chocar contra la
        // migración del propio demo.
        $this->createLog([]);
        $this->removeDemo('notes');

        try {
            [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
            self::assertSame(0, $exit);

            [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes']);

            self::assertNotSame(0, $exit);
            self::assertStringNotContainsString('Colisión de migraciones', $stderr);
            self::assertStringContainsString('ya tiene archivos instalados', $stderr);
        } finally {
            $this->removeDemo('notes');
        }
    }

    public function test_install_force_restaura_el_archivo_preexistente_si_la_db_falla(): void
    {
        $relative = 'app/Models/Note.php';
        $path = $this->root . '/' . $relative;
        $original = "<?php // editado por el usuario
";

        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $original);

        // La tabla notes preexistente hace fallar la migracion del demo.
        $this->createLog([], withNotes: true);

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes', '--force']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('Install revertido', $stderr);
        self::assertSame($original, file_get_contents($path));
    }

    public function test_remove_se_niega_a_borrar_archivos_editados_sin_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $relative = 'app/Models/Note.php';
        $path = $this->root . '/' . $relative;
        file_put_contents($path, "<?php // editado
");

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('archivos editados', $stderr);
        self::assertFileExists($path);
        self::assertSame("<?php // editado
", file_get_contents($path));
    }

    public function test_remove_permite_el_scaffold_actualizado_sin_marcarlo_como_editado(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        // El scaffold avanza después de instalar: el archivo instalado queda
        // viejo, pero nadie lo editó. El manifiesto lo prueba, así que ni el
        // remove ni el install deben pedir --force por eso.
        $relative = 'routes/notes.php';
        $this->advanceScaffold($relative);

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('desactualizado', $stdout);
        self::assertFileDoesNotExist($this->root . '/' . $relative);
    }

    public function test_install_actualiza_un_archivo_desactualizado_sin_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $relative = 'routes/notes.php';
        $this->advanceScaffold($relative);

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('Actualizados desde el scaffold', $stdout);
        self::assertSame(
            file_get_contents($this->root . '/framework/scaffolds/notes/' . $relative),
            file_get_contents($this->root . '/' . $relative)
        );
    }

    public function test_remove_sin_manifiesto_sigue_pidiendo_force(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        // Un demo instalado antes de este cambio no tiene manifiesto: sin él no
        // se puede distinguir «quedó viejo» de «lo editaron», así que remove
        // mantiene el comportamiento anterior.
        $manifest = $this->root . '/storage/demo-state/notes.json';
        self::assertFileExists($manifest);
        self::assertTrue(unlink($manifest));

        $this->advanceScaffold('routes/notes.php');

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('archivos editados', $stderr);
        self::assertFileExists($this->root . '/routes/notes.php');
    }

    public function test_remove_borra_el_manifiesto(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $manifest = $this->root . '/storage/demo-state/notes.json';
        self::assertFileExists($manifest);

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertFileDoesNotExist($manifest);
    }

    /**
     * S2: el catálogo de cada demo viaja con sus archivos.
     *
     * Instalar copia `lang/es/<demo>.php` y `lang/en/<demo>.php`, y el
     * manifiesto los describe con su sha256 como a cualquier otro archivo del
     * scaffold: no hay lista paralela que mantener. Quitarlo borra los dos
     * archivos y el directorio del locale, pero `lang/` se queda, porque es el
     * destino de los catálogos base.
     */
    public function test_install_y_remove_mueven_los_catalogos_del_demo(): void
    {
        $files = ['lang/es/tasks.php', 'lang/en/tasks.php'];

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'tasks', '--no-db']);

        self::assertSame(0, $exit, $stderr);

        $manifest = json_decode((string) file_get_contents($this->root . '/storage/demo-state/tasks.json'), true);

        self::assertIsArray($manifest, 'El install no escribió el manifiesto.');
        self::assertIsArray($manifest['files'] ?? null, 'El manifiesto no describe archivos.');

        foreach ($files as $relative) {
            $path = $this->root . '/' . $relative;

            self::assertFileExists($path, 'La instalación no copió ' . $relative);
            self::assertSame(
                (string) hash_file('sha256', $this->root . '/framework/scaffolds/tasks/' . $relative),
                (string) hash_file('sha256', $path),
                'El catálogo instalado no coincide con el del scaffold: ' . $relative
            );
            self::assertSame(
                (string) hash_file('sha256', $path),
                $manifest['files'][$relative] ?? null,
                'El manifiesto no describe ' . $relative . ' con su sha256.'
            );
        }

        // Y el catálogo base sigue donde estaba.
        self::assertFileExists($this->root . '/lang/es.php');
        self::assertFileExists($this->root . '/lang/en.php');

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'tasks', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertStringContainsString('removido', $stdout);

        foreach ($files as $relative) {
            self::assertFileDoesNotExist($this->root . '/' . $relative, 'Quedó residuo: ' . $relative);
        }

        self::assertDirectoryDoesNotExist($this->root . '/lang/es', 'El remove dejó el directorio del locale vacío.');
        self::assertDirectoryDoesNotExist($this->root . '/lang/en');
        self::assertFileExists($this->root . '/lang/es.php', 'El remove se llevó lang/es.php.');
        self::assertFileExists($this->root . '/lang/en.php', 'El remove se llevó lang/en.php.');
    }

    /**
     * S6: instalar el demo hace resolver sus claves; quitarlo las vuelve crudas.
     *
     * El instalador corre contra un `DEMO_ROOT` temporal y `TranslatorFactory`
     * resuelve `lang/` con `dirname(__DIR__, 2)`, así que el sondeo carga
     * `Framework\` desde el árbol temporal y el `lang/` que lee el loader es el
     * que acaba de escribir el instalador. No se toca el repo.
     *
     * Lo que se mide es el contrato de S6 de punta a punta: el archivo que
     * copia el install es el que el loader encuentra, y el que borra el remove
     * es el que deja de existir.
     */
    public function test_las_claves_del_demo_resuelven_al_instalar_y_no_al_quitar(): void
    {
        $probe = $this->crearSondeoDeCatalogos();

        self::assertSame(
            "locales=en,es\nes=notes.title\nen=notes.title\n",
            $this->runProbe($probe),
            'Sin el demo instalado no puede resolver ninguna clave suya.'
        );

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertSame(
            "locales=en,es\nes=Notas\nen=Notes\n",
            $this->runProbe($probe),
            'El catálogo que instaló el demo no llega al loader.'
        );

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertSame(
            "locales=en,es\nes=notes.title\nen=notes.title\n",
            $this->runProbe($probe),
            'Quitar el demo tiene que quitar sus claves, no sólo sus archivos.'
        );
    }

    /**
     * Copia `Framework\I18n` y `Framework\Support` al árbol temporal y escribe
     * un sondeo que carga el loader desde ahí.
     *
     * `TranslatorFactory` deriva `lang/` de su propia ruta (`dirname(__DIR__, 2)`),
     * así que la copia es lo que hace que apunte al `DEMO_ROOT` del test. El
     * autoloader se registra antes del de `vendor/` y con `prepend`: gana para
     * las clases que el sondeo todavía no cargó.
     */
    private function crearSondeoDeCatalogos(): string
    {
        foreach (['I18n/TranslatorFactory.php', 'Support/Env.php'] as $class) {
            $target = $this->root . '/framework/' . $class;

            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0755, true) && !is_dir(dirname($target))) {
                throw new \RuntimeException('No se pudo crear ' . dirname($target));
            }

            if (!copy($this->projectRoot . '/framework/' . $class, $target)) {
                throw new \RuntimeException('No se pudo copiar ' . $class);
            }
        }

        $probe = $this->root . '/sondeo-catalogos.php';

        $script = <<<'PHP'
<?php

declare(strict_types=1);

// `vendor/autoload.php` registra el autoloader de Composer al frente, así que
// el de este árbol se registra DESPUÉS y con `prepend` para ganarle: lo que el
// sondeo no cargó todavía se resuelve desde la copia temporal.
require __DIR__ . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Framework\\')) {
        return;
    }

    $file = __DIR__ . '/framework/' . str_replace('\\', '/', substr($class, strlen('Framework\\'))) . '.php';

    if (is_file($file)) {
        require $file;
    }
}, true, true);

echo 'locales=' . implode(',', \Framework\I18n\TranslatorFactory::availableLocales()) . PHP_EOL;

foreach (['es', 'en'] as $locale) {
    echo $locale . '=' . \Framework\I18n\TranslatorFactory::create($locale)->trans('notes.title') . PHP_EOL;
}
PHP;

        file_put_contents($probe, $script);

        return $probe;
    }

    /**
     * @return string stdout del sondeo
     */
    private function runProbe(string $probe): string
    {
        $process = proc_open(
            [PHP_BINARY, $probe],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root
        );
        self::assertIsResource($process);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), 'El sondeo del loader falló: ' . $stderr);
        self::assertSame('', $stderr);

        // Los saltos se normalizan: `PHP_EOL` es `\r\n` en Windows y el test
        // compara el texto, no el fin de línea del sistema.
        return str_replace(["\r\n", "\r"], "\n", $stdout);
    }

    /**
     * Simula que el scaffold avanzó después de la instalación: el archivo
     * instalado queda desactualizado sin que el usuario lo haya tocado.
     */
    private function advanceScaffold(string $relative): void
    {
        $path = $this->root . '/framework/scaffolds/notes/' . $relative;
        $content = (string) file_get_contents($path);
        file_put_contents($path, $content . "\n// linea nueva del scaffold\n");
    }

    /**
     * @param list<array{0: int, 1: string}> $rows
     */
    private function createLog(array $rows, bool $withTasks = false, bool $withContacts = false, bool $withNotes = false): void
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

        if ($withNotes) {
            // Tabla `notes` preexistente e incompleta (le faltan `body` y los
            // timestamps): Phinx no puede crear la tabla del demo porque ya
            // existe, así que el install falla en la DB. Ese fallo es lo que
            // necesita el test de rollback, no un texto exacto.
            $pdo->exec('CREATE TABLE notes (id INTEGER PRIMARY KEY, title TEXT)');
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
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $stale = 'routes/notes.php';
        $edited = 'app/Models/Note.php';
        $this->advanceScaffold($stale);

        $editedPath = $this->root . '/' . $edited;
        file_put_contents($editedPath, "<?php // editado por el usuario\n");

        $manifest = $this->root . '/storage/demo-state/notes.json';
        $manifestBefore = (string) file_get_contents($manifest);

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString($edited, $stderr);
        self::assertStringNotContainsString($stale, $stderr);
        self::assertSame("<?php // editado por el usuario\n", file_get_contents($editedPath));
        self::assertSame($manifestBefore, (string) file_get_contents($manifest));
    }

    public function test_remove_con_un_editado_y_otro_desactualizado_no_borra_nada(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $this->advanceScaffold('routes/notes.php');
        $edited = 'app/Models/Note.php';
        file_put_contents($this->root . '/' . $edited, "<?php // editado\n");
        $before = $this->installedHashes();

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-db']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString($edited, $stderr);
        self::assertSame($before, $this->installedHashes());
        self::assertStringNotContainsString('removido', $stdout);
    }

    public function test_remove_no_files_conserva_el_manifiesto(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $manifest = $this->root . '/storage/demo-state/notes.json';
        $before = (string) file_get_contents($manifest);

        [$exit, $stdout, $stderr] = $this->runDemo(['remove', 'notes', '--no-files', '--no-db']);

        self::assertSame(0, $exit, $stderr);
        self::assertSame($before, (string) file_get_contents($manifest));
        self::assertFileExists($this->root . '/routes/notes.php');
    }

    public function test_install_revierte_el_manifiesto_si_la_db_falla(): void
    {
        [$exit] = $this->runDemo(['install', 'notes', '--no-db']);
        self::assertSame(0, $exit);

        $manifest = $this->root . '/storage/demo-state/notes.json';
        $before = (string) file_get_contents($manifest);
        $installed = $this->installedHashes();

        $this->createLog([], withNotes: true);
        $this->advanceScaffold('routes/notes.php');

        [$exit, $stdout, $stderr] = $this->runDemo(['install', 'notes']);

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('Install revertido', $stderr);
        self::assertSame($before, (string) file_get_contents($manifest));
        self::assertSame($installed, $this->installedHashes());
    }

    /** @return array<string, string> relativo => sha256 (o '-' si falta) */
    private function installedHashes(): array
    {
        $hashes = [];
        foreach ($this->installedPaths('notes') as $relative) {
            $path = $this->root . '/' . $relative;
            $hashes[$relative] = is_file($path) ? (string) hash_file('sha256', $path) : '-';
        }

        return $hashes;
    }
}
