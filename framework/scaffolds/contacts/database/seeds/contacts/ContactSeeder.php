<?php

declare(strict_types=1);

use App\Contacts\Models\Contact;
use Phinx\Seed\AbstractSeed;

/**
 * Datos demo para desarrollo. Idempotente: si ya hay contactos no toca nada.
 */
final class ContactSeeder extends AbstractSeed
{
    public function run(): void
    {
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
            Contact::create([
                'name' => $firstNames[$index % 10] . ' ' . $lastNames[intdiv($index, 10)],
                'email' => sprintf('contacto%03d@ejemplo.com', $index + 1),
                'phone' => sprintf('+54 11 5555-%04d', 1000 + $index),
            ]);
        }

        echo "ContactSeeder: 100 contactos demo." . PHP_EOL;
    }
}
