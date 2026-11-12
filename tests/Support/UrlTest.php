<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Url;
use PHPUnit\Framework\TestCase;

/**
 * S13: el prefijo de ruta (APP_BASE_PATH) es UNA cadena normalizada, y toda
 * URL de la app se arma con ella.
 *
 * Los tres casos que importan aquí y que no se pueden adivinar desde el código
 * de los llamadores:
 * 1. `app`, `/app` y `/app/` son el mismo prefijo. Si la normalización se
 *    escapa, `//app/tareas` o `/app//tareas` llegan al router y no matchean.
 * 2. Sin prefijo, todo tiene que salir byte a byte como antes de existir el
 *    helper: es la garantía de que el cambio no toca el despliegue en la raíz.
 * 3. La raíz (`path('/')`) conserva la barra final: `/app/` y no `/app`. Un
 *    href a `/app` funciona igual en un servidor real, pero deja de coincidir
 *    con la URL que el navegador ya tiene (y con la de `Location`), así que el
 *    contrato se fija aquí.
 */
final class UrlTest extends TestCase
{
    protected function tearDown(): void
    {
        self::prefijo('');
    }

    public function test_sin_prefijo_toda_url_sale_como_antes(): void
    {
        self::prefijo('');

        self::assertSame('', Url::base());
        self::assertSame('/tareas', Url::path('/tareas'));
        self::assertSame('/tareas', Url::path('tareas'));
        self::assertSame('/', Url::path('/'));
        self::assertSame('/assets/daisyui-5.7.46.css', Url::path('/assets/daisyui-5.7.46.css'));
    }

    public function test_un_prefijo_sin_barra_inicial_se_normaliza(): void
    {
        self::prefijo('app');

        self::assertSame('/app', Url::base());
        self::assertSame('/app/tareas', Url::path('/tareas'));
    }

    public function test_un_prefijo_con_barra_final_pierde_la_barra(): void
    {
        self::prefijo('/app/');

        self::assertSame('/app', Url::base());
        self::assertSame('/app/tareas', Url::path('/tareas'));
    }

    public function test_el_prefijo_se_recorta_en_los_bordes(): void
    {
        // Un .env con espacios alrededor (copiar y pegar) no puede producir
        // `/app /tareas`: se recorta, y las barras de más colapsan.
        self::prefijo('  /app/  ');

        self::assertSame('/app', Url::base());
        self::assertSame('/app/tareas', Url::path('tareas'));
    }

    public function test_la_raiz_con_prefijo_conserva_la_barra_final(): void
    {
        self::prefijo('/app');

        // Mismo criterio que un href a `/app/`: la barra final es la que dice
        // "esta es la raiz del prefijo", no una ruta hija.
        self::assertSame('/app/', Url::path('/'));
        self::assertSame('/app/', Url::path(''));
    }

    public function test_el_path_no_depende_de_si_el_llamador_pone_la_barra(): void
    {
        self::prefijo('/app');

        self::assertSame(Url::path('/tareas'), Url::path('tareas'));
        self::assertSame('/app/tareas', Url::path('tareas'));
        // La barra se agrega una sola vez: `//app/tareas` es otra ruta.
        self::assertSame('/app/tareas/5/toggle', Url::path('/tareas/5/toggle'));
    }

    public function test_la_barra_sola_es_no_tener_prefijo(): void
    {
        // Un despliegue en la raiz puede escribir APP_BASE_PATH=/ y tiene que
        // significar lo mismo que vacío, no un prefijo `//`.
        self::prefijo('/');

        self::assertSame('', Url::base());
        self::assertSame('/tareas', Url::path('/tareas'));
    }

    /**
     * Las tres fuentes que puede leer un despliegue van juntas: $_ENV (lo llena
     * Dotenv y lo que fija tests/bootstrap.php) y el entorno inline, para que
     * el test no dependa de cuál de las dos gana. `''` es "sin prefijo", así
     * que el reset no necesita desetear nada.
     */
    private static function prefijo(string $valor): void
    {
        $_ENV['APP_BASE_PATH'] = $valor;
        putenv('APP_BASE_PATH=' . $valor);
    }
}
