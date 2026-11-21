<?php

declare(strict_types=1);

// Crea el archivo de una migración con el siguiente número libre del rango de la
// app. Uso:
//   php scripts/make-migration.php CreateNotasTable
//   composer make:migration CreateNotasTable
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
    fwrite(STDERR, "  El nombre puede ir en CamelCase o snake_case: CreateNotasTable o create_notas_table.\n");
    fwrite(STDERR, "  El número lo asigna el script: nunca lo elijas a mano.\n");
    exit(1);
}

$class = className($name);

if ($class === '') {
    fwrite(STDERR, "Nombre inválido: '{$name}'. Se esperan letras o números, por ejemplo CreateNotasTable.\n");
    exit(1);
}

$files = migrationFiles($root);

foreach ($files as $relative => $path) {
    $declared = declaredClass($path);

    if ($declared === $class) {
        fwrite(STDERR, "La clase {$class} ya existe en {$relative}.\n");
        exit(1);
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
 * `create_notas_table` y `CreateNotasTable` producen la misma clase.
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
 * `CreateNotasTable` => `create_notas_table`.
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
 * Todos los archivos de migración del espacio: la app y cada scaffold.
 *
 * @return array<string, string> ruta relativa al root => ruta absoluta
 */
function migrationFiles(string $root): array
{
    $roots = [$root . '/database/migrations'];

    foreach (glob($root . '/framework/scaffolds/*', GLOB_ONLYDIR) ?: [] as $scaffold) {
        $path = $scaffold . '/database/migrations';

        if (is_dir($path)) {
            $roots[] = $path;
        }
    }

    $files = [];

    foreach ($roots as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $absolute = $file->getPathname();
                $files[substr($absolute, strlen($root) + 1)] = $absolute;
            }
        }
    }

    ksort($files);

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
