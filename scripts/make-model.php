<?php

declare(strict_types=1);

use Illuminate\Support\Str;

// Crea el esqueleto de un modelo Eloquent. Uso:
//   composer make:model Nota
//   composer make:model Ciudad ciudades
//   php scripts/make-model.php Nota
//
// Escribe app/Models/<Clase>.php con namespace y `$fillable` vacío. La tabla se
// deriva con la MISMA función que usa Eloquent (`Str::snake(Str::pluralStudly())`,
// ver `Model::getTable()`), así que el generador nunca puede divergir de la
// convención del framework: si el plural que sale es el correcto, el modelo ni
// siquiera lleva `$table`.
//
// Ojo con el alcance de esa convención: el pluralizador de Doctrine es inglés.
// Acierta `Category` => `categories` y `Lunes` => `lunes`, pero produce
// `ciudads` de `Ciudad` y `lapizs` de `Lapiz`, porque la regla inglesa no sabe de
// plurales españoles. Para esos casos el segundo argumento es la tabla.
//
// NO escribe los docblocks: `@property` y `@method` tienen que coincidir columna
// por columna con la migración, porque PHPStan los lee para tipar cada fila, y
// eso sólo lo sabe quien escribe la entidad.
//
// La tabla es un argumento posicional y no una opción porque Composer se queda
// con los `--flags` y no los reenvía al script.
//
// MAKE_MODEL_ROOT permite correrlo contra un árbol temporal (tests). En uso
// normal queda vacío y el root es el del proyecto.

$autoload = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Falta 'vendor/': ejecuta composer install antes de generar un modelo.\n");
    exit(1);
}

require $autoload;

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
    fwrite(STDERR, "  La tabla se deriva con la convención de Eloquent; pasala como segundo\n");
    fwrite(STDERR, "  argumento sólo si tu plural español no es el que Eloquent infiere.\n");
    fwrite(STDERR, "  Ejemplo: composer make:model Ciudad ciudades\n");
    exit(1);
}

$class = modelClassName($name);

if ($class === '') {
    fwrite(STDERR, "Nombre inválido: '{$name}'. Se esperan letras o números, por ejemplo Nota.\n");
    exit(1);
}

// La misma derivación que Eloquent: Model::getTable() usa
// Str::snake(Str::pluralStudly(class_basename($this))).
$inferida = Str::snake(Str::pluralStudly($class));
$asumida = $table === '';
$table = $asumida ? $inferida : $table;

if (preg_match('/^[a-z][a-z0-9_]*$/', $table) !== 1) {
    fwrite(STDERR, "Tabla inválida: '{$table}'. Se espera snake_case, por ejemplo ciudades.\n");
    exit(1);
}

// `$table` sólo se escribe cuando la tabla se aparta de la convención. Repetir
// el plural que Eloquent ya deriva sería ruido.
$llevaTable = $table !== $inferida;

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

if (file_put_contents($target, modelSkeleton($class, $table, $llevaTable)) === false) {
    fwrite(STDERR, "No se pudo escribir {$relativePath}.\n");
    exit(1);
}

echo "Creado: {$relativePath}\n";
echo $llevaTable
    ? "Tabla: {$table}. Eloquent inferiría '{$inferida}' del nombre, así que el modelo lleva \$table.\n"
    : "Tabla: {$table}, derivada por Eloquent del nombre: el modelo no lleva \$table.\n";

if ($asumida) {
    echo "Si tu migración usa otra tabla, pasala como segundo argumento: composer make:model {$class} <tabla>.\n";
}

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

function modelSkeleton(string $class, string $table, bool $llevaTable): string
{
    $tabla = $llevaTable ? "    protected \$table = '{$table}';\n\n" : '';

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
        . $tabla
        . "    protected \$fillable = [];\n}\n";
}
