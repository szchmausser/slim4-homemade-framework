<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\Http\Middleware\RequireAuthMiddleware;
use Slim\App;

/**
 * La app se construye una vez y se comparte entre todos los tests.
 * El esquema NO se define aca: lo aplican las migraciones de Phinx desde
 * tests/bootstrap.php. Duplicar el CREATE TABLE a mano dejaba a la suite
 * verde contra un esquema que la app nunca usa (y sin la FK de remember_tokens).
 */
final class AppFactory
{
    private static ?App $app = null;

    public static function make(): App
    {
        if (self::$app !== null) {
            return self::$app;
        }

        $app = require __DIR__ . '/../../bootstrap/app.php';

        // Ruta protegida exclusiva de la suite: los tests de auth y de base
        // path no dependen de que el scaffold demo esté instalado.
        $app->get('/_test/protected', function ($request, $response) {
            $response->getBody()->write('protected');
            return $response;
        })->add(RequireAuthMiddleware::class);

        return self::$app = $app;
    }

    /**
     * Descarta la app cacheada para que el proximo make() vuelva a bootstrapear.
     * Solo para tests que cambian el entorno ANTES del arranque: el prefijo de
     * ruta (APP_BASE_PATH) se fija una vez, en `$app->setBasePath()`, y Twig
     * resuelve la funcion `url()` contra ese mismo prefijo. Sin este reset el
     * test correria contra una app armada con el entorno anterior y el verde no
     * diria nada. Ningun test de produccion deberia llamarlo: la app es una.
     */
    public static function reset(): void
    {
        self::$app = null;
    }
}
