<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Capsule\Manager as Capsule;
use Slim\App;

/**
 * La app se construye una vez y se comparte entre todos los tests.
 */
final class AppFactory
{
    private static ?App $app = null;

    public static function make(): App
    {
        if (self::$app === null) {
            self::$app = require __DIR__ . '/../../bootstrap/app.php';
            self::defineSchema();
        }

        return self::$app;
    }

    /**
     * El error N.º 1 de este setup: crear un `new Capsule()` propio con
     * `sqlite::memory:`. Cada conexión nueva a `:memory:` es una base vacía
     * INDEPENDIENTE — el schema quedaría en una base que la app nunca toca y
     * todo test fallaría con "no such table: tasks". El schema siempre va
     * sobre el MISMO Capsule que usa la app.
     */
    private static function defineSchema(): void
    {
        // Con SQL plano, no con Phinx: los tests tienen que correr en un
        // segundo, no levantar el migrador.
        // Trade-off real: si cambiás la migración y olvidás el CREATE TABLE
        // de acá, los tests siguen verdes contra un esquema viejo. Si eso te
        // molesta, corré Phinx contra un archivo SQLite temporal en vez de
        // usar :memory:.
        Capsule::schema()->create('tasks', function ($table) {
            $table->increments('id');
            $table->string('title', 120);
            $table->boolean('done')->default(false);
            $table->timestamps();
        });
    }
}
