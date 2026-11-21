<?php

declare(strict_types=1);

// Crea el archivo de una migración con el siguiente número libre del rango de la
// app. Uso:
//   php scripts/make-migration.php CreateNotesTable
//   composer make:migration CreateNotesTable
//
// Por qué existe: `phinx create` nombra con timestamp YYYYMMDDHHMMSS, y en PHP
// de 32 bits ese prefijo satura a PHP_INT_MAX (todas las migraciones colapsan a
// la misma versión). La convención del proyecto es NNN_snake_case.php; este
// script es el único que asigna el número, para que no dependa de la memoria de
// quien escribe la migración.
//
// El espacio de versiones de Phinx es único y global: app y scaffolds comparten
// contador aunque el demo no esté instalado. La app ocupa el rango bajo
// contiguo desde 001 y los demos viven en bandas reservadas desde
// DEMO_BAND_START, así el contador de la app nunca los toca y
// `ls database/migrations/` no abre huecos.
//
// El nombre de clase se chequea sólo contra el espacio real, que es lo que Phinx
// carga al migrar. Si la clase la declara un scaffold, se avisa y se sigue: ese
// demo no puede instalarse mientras la migración propia exista.
//
// MAKE_MIGRATION_ROOT permite correrlo contra un árbol temporal (tests). En uso
// normal queda vacío y el root es el del proyecto.

const DEMO_BAND_START = 900;

$root = getenv('MAKE_MIGRATION_ROOT') ?: dirname(__DIR__);
$root = rtrim((string) $root, '/');

if (!is_dir($root)) {
    fwrite(STDERR, "MAKE_MIGRATION_ROOT no existe: {$root}\n");
    exit(1);
}

// `$argv` sólo existe en CLI y PHPStan lo marca como indefinido en el ámbito
// global; se lee de `$_SERVER`, que el SAPI siempre puebla.
$argv = $_SERVER['argv'] ?? [];
$name = '';
foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--')) {
        $name = $argument;
        break;
    }
}

if ($name === '') {
    fwrite(STDERR, "Uso: composer make:migration <Nombre>\n");
    fwrite(STDERR, "  El nombre puede ir en CamelCase o snake_case: CreateNotesTable o create_notes_table.\n");
    fwrite(STDERR, "  El número lo asigna el script: nunca lo elijas a mano.\n");
    exit(1);
}

$class = className($name);

if ($class === '') {
    fwrite(STDERR, "Nombre inválido: '{$name}'. Se esperan letras o números, por ejemplo CreateNotesTable.\n");
    exit(1);
}

$files = migrationFiles($root);

foreach (spaceFiles($root) as $relative => $path) {
    if (declaredClass($path) === $class) {
        fwrite(STDERR, "La clase {$class} ya existe en {$relative}.\n");
        exit(1);
    }
}

$enScaffold = '';

foreach (scaffoldFiles($root) as $relative => $path) {
    if (declaredClass($path) === $class) {
        $enScaffold = $relative;
        break;
    }
}

$next = 1;
foreach (glob($root . '/database/migrations/*.php') ?: [] as $path) {
    $next = max($next, versionOf($path) + 1);
}

if ($next >= DEMO_BAND_START) {
    fwrite(STDERR, "El siguiente número libre es {$next}, que entra en la banda reservada para demos ("
        . DEMO_BAND_START . "-999). La app tiene que quedarse por debajo de " . DEMO_BAND_START . ".\n");
    exit(1);
}

foreach ($files as $relative => $path) {
    if (versionOf($path) === $next) {
        fwrite(STDERR, 'La versión ' . sprintf('%03d', $next) . " ya la usa {$relative}.\n");
        exit(1);
    }
}

if ($enScaffold !== '') {
    echo "Aviso: la clase {$class} también la declara el scaffold {$enScaffold}.\n";
    echo "Phinx deriva la clase del nombre del archivo, así que ese demo y tu migración\n";
    echo "no pueden convivir: instalarlo abortaría con «has the same name as».\n";
    echo "Tu migración queda igual; sólo evita `composer demo:<demo>:install`.\n";
}

$relativePath = 'database/migrations/' . sprintf('%03d', $next) . '_' . snakeCase($class) . '.php';
$target = $root . '/' . $relativePath;

if (file_put_contents($target, skeleton($class)) === false) {
    fwrite(STDERR, "No se pudo escribir {$relativePath}.\n");
    exit(1);
}

echo "Creado: {$relativePath}\n";
echo "Siguiente paso: escribir el change() y correr `composer migrate`.\n";
exit(0);

/**
 * `create_notes_table` y `CreateNotesTable` producen la misma clase.
 */
function className(string $raw): string
{
    $words = preg_split('/[^a-zA-Z0-9]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $class = '';

    foreach ($words as $word) {
        $class .= ucfirst($word);
    }

    return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $class) === 1 ? $class : '';
}

/**
 * `CreateNotesTable` => `create_notes_table`.
 */
function snakeCase(string $class): string
{
    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
}

/**
 * Esqueleto con el estilo del proyecto: `change()` vacío para que lo llene quien
 * escribe la migración.
 */
function skeleton(string $class): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nuse Phinx\\Migration\\AbstractMigration;\n\n"
        . "final class {$class} extends AbstractMigration\n{\n"
        . "    public function change(): void\n    {\n    }\n}\n";
}

/**
 * El espacio real de Phinx: los `*.php` de primer nivel más las copias de demos
 * instalados. Es lo único que Phinx carga al migrar.
 *
 * @return array<string, string> ruta relativa al root => ruta absoluta
 */
function spaceFiles(string $root): array
{
    $files = [];

    foreach (glob($root . '/database/migrations/*.php') ?: [] as $path) {
        $files[substr($path, strlen($root) + 1)] = $path;
    }

    foreach (glob($root . '/database/migrations/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $files += phpFilesUnder($dir, $root);
    }

    ksort($files);

    return $files;
}

/**
 * Los scaffolds: reservan versiones aunque el demo no esté instalado.
 *
 * @return array<string, string> ruta relativa al root => ruta absoluta
 */
function scaffoldFiles(string $root): array
{
    $files = [];

    foreach (glob($root . '/framework/scaffolds/*', GLOB_ONLYDIR) ?: [] as $scaffold) {
        $dir = $scaffold . '/database/migrations';

        if (is_dir($dir)) {
            $files += phpFilesUnder($dir, $root);
        }
    }

    ksort($files);

    return $files;
}

/**
 * Unión de las dos: es el contador de versiones que hay que respetar.
 *
 * @return array<string, string> ruta relativa al root => ruta absoluta
 */
function migrationFiles(string $root): array
{
    $files = spaceFiles($root) + scaffoldFiles($root);
    ksort($files);

    return $files;
}

/**
 * @return array<string, string> ruta relativa al root => ruta absoluta
 */
function phpFilesUnder(string $dir, string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $absolute = $file->getPathname();
            $files[substr($absolute, strlen($root) + 1)] = $absolute;
        }
    }

    return $files;
}

function declaredClass(string $path): string
{
    $content = (string) file_get_contents($path);

    return preg_match('/final class (\w+)/', $content, $matches) === 1 ? $matches[1] : '';
}

/**
 * Phinx saca la versión con `(int)` del prefijo numérico del archivo.
 */
function versionOf(string $path): int
{
    preg_match('/^(\d+)/', basename($path), $matches);

    return (int) ($matches[1] ?? 0);
}
