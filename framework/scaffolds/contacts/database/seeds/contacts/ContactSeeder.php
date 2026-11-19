<?php

declare(strict_types=1);

use App\Contacts\Models\Contact;
use Phinx\Seed\AbstractSeed;

/**
 * Cien contactos de ejemplo: suficientes para ver paginar y ordenar la tabla.
 *
 * Escribe a través del modelo `Contact`, no con SQL suelto, y lo ejecuta el
 * instalador del demo después de migrar.
 *
 * Es idempotente: si ya hay contactos no toca nada, así que puedes correrlo dos
 * veces sin duplicar filas. Lo mismo conviene en el seeder de tu entidad.
 */
final class ContactSeeder extends AbstractSeed
{
    public function run(): void
    {
        // El seeder corre fuera del bootstrap HTTP: lo carga para que Eloquent
        // quede conectado antes de la primera consulta.
        require_once __DIR__ . '/../../../bootstrap/app.php';

        if (Contact::query()->exists()) {
            echo "ContactSeeder: ya hay contactos, no se toca nada." . PHP_EOL;
            return;
        }

        $firstNames = [
            'Ana', 'Bruno', 'Carla', 'Diego', 'Elena',
            'Federico', 'Gabriela', 'Hugo', 'Inés', 'Javier',
        ];
        $lastNames = [
            'Torres', 'Díaz', 'Méndez', 'Ríos', 'Sosa',
            'Paz', 'Luna', 'Castro', 'Vega', 'Molina',
        ];

        foreach (range(0, 99) as $index) {
            // Diez nombres por diez apellidos, combinados sin repetir: cien
            // filas con nombre distinto y un email que las identifica.
            Contact::create([
                'name' => $firstNames[$index % 10] . ' ' . $lastNames[intdiv($index, 10)],
                'email' => sprintf('contacto%03d@ejemplo.com', $index + 1),
                'phone' => sprintf('+54 11 5555-%04d', 1000 + $index),
            ]);
        }

        echo "ContactSeeder: 100 contactos demo." . PHP_EOL;
    }
}
