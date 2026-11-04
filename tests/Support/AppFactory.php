<?php

declare(strict_types=1);

namespace Tests\Support;

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
        return self::$app ??= require __DIR__ . '/../../bootstrap/app.php';
    }
}
