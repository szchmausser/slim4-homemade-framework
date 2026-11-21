<?php

declare(strict_types=1);

// Crea el esqueleto de un modelo Eloquent. Uso:
//   composer make:model Nota
//   composer make:model Ciudad ciudades
//   php scripts/make-model.php Nota
//
// Escribe app/Models/<Clase>.php con namespace, tabla y `$fillable` vacío. El
// segundo argumento es la tabla; sin él se asume el nombre en snake + s (y se
// imprime, para que el error salte a la vista).
//
// NO escribe los docblocks: `@property` y `@method` tienen que coincidir columna
// por columna con la migración, porque PHPStan los lee para tipar cada fila, y
// eso sólo lo sabe quien escribe la entidad. El generador ahorra el boilerplate,
// no el trabajo que importa.
//
// La tabla no se deduce sin diccionario: `task` => `tasks` es inglés y `ciudad`
// => `ciudades` es español, misma terminación y reglas distintas. Por eso el
// plural explícito es un argumento y no una opción: Composer se queda con los
// `--flags` y no los reenvía al script.
//
// MAKE_MODEL_ROOT permite correrlo contra un árbol temporal (tests). En uso
// normal queda vacío y el root es el del proyecto.

$root = getenv('MAKE_MODEL_ROOT') ?: dirname(__DIR__);
$root = rtrim((string) $root, '/');

if (!is_dir($root)) {
    fwrite(STDERR, "MAKE_MODEL_ROOT no existe: {$root}\n");
    exit(1);
}

// `$argv` sólo existe en CLI y PHPStan lo marca como indefinido en el ámbito
// global; se lee de `$_SERVER`, que el SAPI siempre puebla.
$argv = $_SERVER['argv'] ?? [];
$posicionales = [];

foreach (array_slice($argv, 1) as $argument) {
    if (!str_starts_with($argument, '--')) {
        $posicionales[] = $argument;
    }
}

$name = $posicionales[0] ?? '';
$table = $posicionales[1] ?? '';

if ($name === '') {
    fwrite(STDERR, "Uso: composer make:model <Nombre> [<tabla>]\n");
    fwrite(STDERR, "  El nombre puede ir en CamelCase o snake_case: Nota o nota.\n");
    fwrite(STDERR, "  La tabla se asume como el nombre en snake + s; pasala como segundo argumento si no.\n");
    fwrite(STDERR, "  Ejemplo: composer make:model Ciudad ciudades\n");
    exit(1);
}

$class = modelClassName($name);

if ($class === '') {
    fwrite(STDERR, "Nombre inválido: '{$name}'. Se esperan letras o números, por ejemplo Nota.\n");
    exit(1);
}

$asumida = $table === '';
$table = $asumida ? modelSnakeCase($class) . 's' : $table;

if (preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
    fwrite(STDERR, "Tabla inválida: '{$table}'. Se espera snake_case, por ejemplo ciudades.\n");
    exit(1);
}

$dir = $root . '/app/Models';
$relativePath = 'app/Models/' . $class . '.php';
$target = $root . '/' . $relativePath;

if (is_file($target)) {
    fwrite(STDERR, "El modelo {$class} ya existe en {$relativePath}.\n");
    exit(1);
}

if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "No se pudo crear {$dir}.\n");
    exit(1);
}

if (file_put_contents($target, modelSkeleton($class, $table)) === false) {
    fwrite(STDERR, "No se pudo escribir {$relativePath}.\n");
    exit(1);
}

echo "Creado: {$relativePath}\n";
echo $asumida
    ? "Tabla asumida: {$table} (si tu migración usa otra, pasala como segundo argumento).\n"
    : "Tabla: {$table}\n";
echo "Siguiente paso: completar los @property y @method con las columnas de la migración.\n";
exit(0);

/**
 * `nota` y `Nota` producen la misma clase. El prefijo `model` evita chocar con
 * las funciones de scripts/make-migration.php: aunque cada script corre solo,
 * dos declaraciones con el mismo nombre en el mismo árbol confunden a PHPStan.
 */
function modelClassName(string $raw): string
{
    $words = preg_split('/[^a-zA-Z0-9]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $class = '';

    foreach ($words as $word) {
        $class .= ucfirst($word);
    }

    return preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $class) === 1 ? $class : '';
}

/**
 * `NotaCategoria` => `nota_categoria`.
 */
function modelSnakeCase(string $class): string
{
    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
}

function modelSkeleton(string $class, string $table): string
{
    return "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\n"
        . "use Illuminate\\Database\\Eloquent\\Model;\n\n"
        . "/**\n"
        . " * Modelo de la entidad `{$table}`.\n"
        . " *\n"
        . " * Completa las anotaciones con las columnas reales de la migración: PHPStan\n"
        . " * las lee para tipar cada fila, y una anotación que no coincide con el\n"
        . " * esquema aparece después como un null inesperado en la vista.\n"
        . " *\n"
        . " * @property int \$id\n"
        . " * @method static {$class}|null find(int|string \$id)\n"
        . " */\n"
        . "class {$class} extends Model\n{\n"
        . "    protected \$table = '{$table}';\n\n"
        . "    protected \$fillable = [];\n}\n";
}
