<?php

declare(strict_types=1);

use App\Models\Note;
use Phinx\Seed\AbstractSeed;

/**
 * Sembrador del demo Notes: doce filas de ejemplo para que el listado tenga algo
 * que paginar.
 *
 * Phinx lo corre al instalar el demo y escribe por el modelo `Note`. Es
 * idempotente: si ya hay notas no escribe nada, así que reinstalar no duplica
 * filas.
 *
 * Para tu entidad: copia el archivo, cambia el modelo y los títulos.
 */
final class NoteSeeder extends AbstractSeed
{
    public function run(): void
    {
        // El seeder corre por CLI, fuera de la app: este require arranca el
        // bootstrap, que es lo que deja a Eloquent conectado antes de usar el
        // modelo. La ruta sube tres niveles porque el archivo vive en
        // database/seeds/notes/.
        require_once __DIR__ . '/../../../bootstrap/app.php';

        if (Note::query()->exists()) {
            echo 'NoteSeeder: notes already present, nothing to do.' . PHP_EOL;
            return;
        }

        // Doce títulos y 10 filas por página: el listado ya nace con dos
        // páginas para probar el paginador.
        $titles = [
            'Buy groceries',
            'Call the plumber',
            'Review the deploy',
            'Pay the electric bill',
            'Read the RFC',
            'Prepare the meeting',
            'Update the changelog',
            'Water the plants',
            'Log the hours',
            'Check the inbox',
            'Tidy the desk',
            'Close the sprint',
        ];

        // Una de cada tres notas lleva `body`, así el listado muestra las dos
        // variantes de la fila. Los índices 0, 3, 6 y 9 son los que lo llevan.
        $bodies = [
            0 => 'Milk, eggs, bread and coffee for the week.',
            3 => 'Due on the 15th; pay it from the shared account.',
            6 => 'Add the note:format change and link the release notes.',
            9 => 'Clear the support queue before the standup.',
        ];

        foreach ($titles as $index => $title) {
            Note::create([
                'title' => $title,
                'body'  => $bodies[$index] ?? null,
            ]);
        }

        echo 'NoteSeeder: ' . count($titles) . ' demo notes.' . PHP_EOL;
    }
}
