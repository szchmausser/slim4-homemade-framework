<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Prefijo de ruta, para servir la app desde una carpeta y no desde la raíz del
 * dominio (S13).
 *
 * El caso real: el despliegue vive en `https://dominio.com/app/`. Todas las
 * URLs eran raíz-absolutas (`/tareas`, `/assets/...`), así que el navegador las
 * resolvía contra el dominio y cada una daba 404: la app se veía sin estilos y
 * sin poder navegar.
 *
 * Un solo lugar decide el prefijo. `base()` lo normaliza y `path()` arma cada
 * URL; las plantillas lo consumen por la función Twig `url()` y el resto del
 * PHP por `Url::path()`. Con `APP_BASE_PATH` vacío no agrega nada, así que un
 * despliegue en la raíz sale byte a byte igual que antes de existir este
 * helper.
 */
final class Url
{
    /** Prefijo normalizado: '' o '/app'. Nunca termina en '/'. */
    public static function base(): string
    {
        // $_ENV primero (es de donde lee todo el proyecto: Dotenv y
        // tests/bootstrap.php) y getenv como red para el entorno inline de un
        // despliegue sin .env (`APP_BASE_PATH=/app php -S ...`). Sin caché a
        // propósito: es una lectura de array por URL, y cachear obligaría a
        // invalidar el valor en cada test que cambia el entorno.
        $value = $_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH');

        return self::normalize(is_string($value) ? $value : '');
    }

    /**
     * URL de una ruta de la app. Acepta '/tareas' y 'tareas' por igual: el
     * llamador no tiene que acordarse de dónde va la barra.
     *
     * La raíz conserva la barra final: con prefijo `/app`, `path('/')` es
     * `/app/` y no `/app`. Es la URL que el router resuelve a la ruta `/` y la
     * que el navegador ya tiene en la barra; sin ella, la raíz del despliegue
     * queda como otra URL distinta.
     */
    public static function path(string $path): string
    {
        // '/' + ltrim(): '' y '/' caen los dos en '/', y 'tareas' también.
        // El resultado nunca duplica la barra aunque el llamador ya la ponga.
        return self::base() . '/' . ltrim($path, '/');
    }

    /**
     * `app`, `/app`, `/app/`, `  app ` y `app/` son el MISMO prefijo. Con
     * barras de más la URL sale `//app/tareas`, que es otra ruta y no matchea
     * ninguna. La barra sola ('/') significa "sin prefijo", igual que vacío:
     * un despliegue en la raíz puede escribir cualquiera de las dos.
     */
    private static function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return rtrim('/' . ltrim($value, '/'), '/');
    }
}
