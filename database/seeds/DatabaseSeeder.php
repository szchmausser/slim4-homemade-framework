<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Orquestador estilo DatabaseSeeder de Laravel. Phinx no trae $this->call():
 * `seed:run` pelado ejecuta todo el directorio en orden de glob, así que el
 * orden explícito vive aquí. Cada seeder DEBE ser idempotente (updateOrCreate
 * o guardas), porque nada impide correrlos sueltos con -s.
 *
 * Uso: vendor/bin/phinx seed:run -s DatabaseSeeder
 */
final class DatabaseSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/AdminSeeder.php';

        // El demo es opcional: solo se siembra si su scaffold está instalado.
        $seeds = [AdminSeeder::class];
        $taskSeeder = __DIR__ . '/tasks/TaskSeeder.php';
        if (is_file($taskSeeder)) {
            require_once $taskSeeder;
            $seeds[] = TaskSeeder::class;
        }

        $contactSeeder = __DIR__ . '/contacts/ContactSeeder.php';
        if (is_file($contactSeeder)) {
            require_once $contactSeeder;
            $seeds[] = ContactSeeder::class;
        }

        $notaSeeder = __DIR__ . '/notas/NotaSeeder.php';
        if (is_file($notaSeeder)) {
            require_once $notaSeeder;
            $seeds[] = NotaSeeder::class;
        }

        foreach ($seeds as $class) {
            (new $class())->setAdapter($this->getAdapter())->run();
        }
    }
}
