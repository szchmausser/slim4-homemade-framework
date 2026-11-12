<?php

declare(strict_types=1);

// Lazo de CI desde la terminal: mirar la corrida del push, leer el fallo y
// bajar los artefactos. Pensado para Termux, donde el navegador no existe y el
// resultado de la capa de navegador solo puede venir de CI.
//
// Uso:
//   php scripts/ci.php watch      # espera la corrida del push y dice si paso
//   php scripts/ci.php log        # solo el fallo, filtrado: que, donde, por que
//   php scripts/ci.php artifacts  # baja PNG y traces a storage/ci/<run>
//
// `--branch=<rama>` para mirar otra rama. `CI_WAIT=0` hace que `watch` salga
// sin esperar (util en `composer push` cuando no se quiere bloquear).

const PREFIJO_RUN = 'storage/ci/';

$root = dirname(__DIR__);
chdir($root);

// `$argv` no está tipado en CLI y PHPStan lo marca como indefinido; por
// `$_SERVER['argv']` pasa el gate sin suprimir nada.
$args = $_SERVER['argv'] ?? [];

$command = $args[1] ?? 'watch';
$branch = argumento($args, '--branch') ?? ramaActual();

if ($command === 'watch' && getenv('CI_WAIT') === '0') {
    salir(0, "CI_WAIT=0: no se espera la corrida de '{$branch}'.\n");
}

if (!ghDisponible()) {
    salir(0, "gh no esta instalado o no hay red: no se puede consultar CI.\n"
        . "Instalalo con 'pkg install gh' y autentica con 'gh auth login'.\n");
}

$run = corridaDeLaRama($branch);

if ($run === null) {
    salir(0, "No hay corridas para '{$branch}'. O el push todavia no disparo el workflow.\n");
}

$id = (string) $run['databaseId'];

switch ($command) {
    case 'watch':
        $codigo = esperarCorrida($id);
        if ($codigo === 0) {
            salir(0, "CI OK en '{$branch}' (run {$id}).\n");
        }

        fwrite(STDERR, "CI EN ROJO en '{$branch}' (run {$id}).\n\n");
        fwrite(STDERR, falloFiltrado($id));
        fwrite(STDERR, "\nScreenshots y trace: php scripts/ci.php artifacts\n");
        salir(1);

    case 'log':
        $texto = falloFiltrado($id);
        echo $texto === '' ? "La corrida {$id} no tiene pasos fallidos.\n" : $texto;
        salir(0);

    case 'artifacts':
        $destino = PREFIJO_RUN . $id;

        // Sin limpiar, un segundo intento falla con "file exists" del zip.
        if (is_dir($destino)) {
            ejecutar('rm -rf ' . escapeshellarg($destino));
        }

        [$salida, $codigo] = ejecutar("gh run download {$id} -n playwright-failure -D {$destino}");

        if ($codigo !== 0) {
            salir(1, "No se pudo bajar el artefacto: {$salida}\n");
        }

        $pngs = glob($destino . '/**/test-failed-*.png') ?: [];
        $pngs = array_merge($pngs, glob($destino . '/**/*.png') ?: []);

        echo "Bajado a {$destino}\n";
        foreach (array_unique($pngs) as $png) {
            echo "  {$png}\n";
        }
        salir(0);

    default:
        salir(1, "Comando desconocido: '{$command}'. Usa watch, log o artifacts.\n");
}

/**
 * Ultima corrida del workflow CI para una rama. Espera hasta 45 s a que el
 * workflow aparezca: justo despues de `git push`, GitHub tarda unos segundos
 * en registrar la corrida y consultar de inmediato devuelve una vieja.
 *
 * @return array{databaseId:int,status:string,conclusion:?string}|null
 */
function corridaDeLaRama(string $branch): ?array
{
    $limite = time() + 45;

    do {
        [$salida, $codigo] = ejecutar(
            "gh run list --workflow=ci.yml --branch={$branch} --limit=1 "
            . "--json databaseId,status,conclusion"
        );

        if ($codigo === 0) {
            /** @var list<array{databaseId:int,status:string,conclusion:?string}> $runs */
            $runs = json_decode($salida, true) ?: [];

            if ($runs !== [] && $runs[0]['status'] !== 'queued') {
                return $runs[0];
            }
        }

        sleep(5);
    } while (time() < $limite);

    return null;
}

function esperarCorrida(string $id): int
{
    return ejecutar("gh run watch {$id} --exit-status")[1];
}

/**
 * Log del fallo sin el ruido que lo vuelve ilegible en una pantalla de
 * telefono: el prefijo `job/step` que agrega `gh`, el timestamp de cada linea,
 * los colores y el access log del servidor PHP.
 */
function falloFiltrado(string $id): string
{
    [$salida] = ejecutar("gh run view {$id} --log-failed");
    $lineas = [];

    foreach (explode("\n", $salida) as $linea) {
        // "<job>\t<step>\t<timestamp> <texto>"
        $partes = explode("\t", $linea, 3);

        if (count($partes) === 3) {
            $linea = $partes[2];
        }

        $linea = preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $linea) ?? $linea;
        // `gh` a veces entrega las secuencias escapadas como texto: "^[[36;1m".
        $linea = preg_replace('/\^\[\[[0-9;]*[A-Za-z]/', '', $linea) ?? $linea;
        $linea = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/', '', $linea) ?? $linea;
        $linea = preg_replace('/^\x{FEFF}/u', '', $linea) ?? $linea;
        $linea = preg_replace('/^\d{4}-\d{2}-\d{2}T[\d:.]+Z\s?/', '', $linea) ?? $linea;
        $linea = rtrim($linea);

        if ($linea === '') {
            continue;
        }

        // Ruido del andamiaje de Actions, del access log del servidor de
        // desarrollo y del preambulo del step.
        $ruido = [
            '/^\[WebServer\]/',
            '/^##\[group\]/',
            '/^##\[endgroup\]/',
            '/^Run npx /',
            '/^npx playwright test$/',
            // Progreso por test: en un telefono solo agrega ruido antes del fallo.
            '/^\[\d+\/\d+\]/',
            '/^shell: /',
            '/^env:$/',
            '/^  [A-Z_]+[:=]/',
        ];

        foreach ($ruido as $patron) {
            if (preg_match($patron, $linea) === 1) {
                continue 2;
            }
        }

        $lineas[] = $linea;
    }

    return implode("\n", $lineas) . "\n";
}

function argumento(array $args, string $nombre): ?string
{
    foreach ($args as $arg) {
        if (str_starts_with($arg, $nombre . '=')) {
            return substr($arg, strlen($nombre) + 1);
        }
    }

    return null;
}

function ramaActual(): string
{
    [$salida, $codigo] = ejecutar('git rev-parse --abbrev-ref HEAD');

    return $codigo === 0 ? trim($salida) : 'main';
}

function ghDisponible(): bool
{
    return ejecutar('gh --version')[1] === 0;
}

/**
 * @return array{0:string,1:int}
 */
function ejecutar(string $comando): array
{
    $salida = [];
    $codigo = 0;

    exec($comando . ' 2>&1', $salida, $codigo);

    return [implode("\n", $salida), $codigo];
}

function salir(int $codigo, string $mensaje = ''): never
{
    $codigo === 0 ? fwrite(STDOUT, $mensaje) : fwrite(STDERR, $mensaje);

    exit($codigo);
}
