<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Orquestador estilo DatabaseSeeder de Laravel. Phinx no trae $this->call():
 * `seed:run` pelado ejecuta todo el directorio en orden de glob, así que el
 * orden explícito vive acá. Cada seeder DEBE ser idempotente (updateOrCreate
 * o guardas), porque nada impide correrlos sueltos con -s.
 *
 * Uso: vendor/bin/phinx seed:run -s DatabaseSeeder
 */
final class DatabaseSeeder extends AbstractSeed
{
    /** @var class-string[] en orden explícito de ejecución. */
    private const SEEDS = [
        AdminSeeder::class,
        TaskSeeder::class,
    ];

    public function run(): void
    {
        foreach (self::SEEDS as $class) {
            (new $class())->setAdapter($this->getAdapter())->run();
        }
    }
}
