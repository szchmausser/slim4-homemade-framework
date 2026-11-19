<?php

declare(strict_types=1);

namespace Framework\Http;

/**
 * Construye las URLs de la aplicación a partir del prefijo de despliegue.
 *
 * Es el punto único de armado de rutas: las plantillas entran por la función Twig
 * `url()` y el PHP por `Url::path()`, así que ninguna vista conoce la carpeta en la
 * que está montada la aplicación. Sin `APP_BASE_PATH` el prefijo es vacío y cada
 * URL sale idéntica a la de un despliegue en la raíz del dominio.
 */
final class Url
{
    /**
     * Prefijo de despliegue normalizado: `''` en la raíz del dominio, `/app` cuando
     * la aplicación vive en una subcarpeta.
     *
     * Lee `$_ENV` primero porque es la fuente que cargan Dotenv y el bootstrap de
     * tests, y cae a `getenv()` para el despliegue que define la variable inline,
     * sin `.env`. No memoriza el valor: son dos lecturas de array por URL y una
     * caché habría que invalidarla en cada test que cambia el entorno.
     */
    public static function base(): string
    {
        $value = $_ENV['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH');

        return self::normalize(is_string($value) ? $value : '');
    }

    /**
     * URL de una ruta interna, con el prefijo ya aplicado.
     *
     * Acepta `/tareas` y `tareas` por igual: quien llama no necesita acordarse de
     * la barra inicial y el resultado nunca la duplica. La raíz conserva su barra
     * final (`/app/`), que es la URL que el router resuelve y la que el navegador
     * muestra; sin ella, la raíz del despliegue quedaría como una ruta más.
     */
    public static function path(string $path): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }

    /**
     * Reduce cualquier forma de escribir el prefijo a `''` o `/algo`.
     *
     * `app`, `/app`, `/app/` y `  app  ` son el mismo prefijo. Una barra de más
     * produce `//app/tareas`, una ruta distinta que no coincide con ninguna
     * definición. `/` significa lo mismo que vacío: aplicación en la raíz.
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
