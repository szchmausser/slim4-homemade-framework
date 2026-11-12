<?php

declare(strict_types=1);

use App\Notas\Models\Nota;
use Phinx\Seed\AbstractSeed;

/**
 * Datos demo para desarrollo. Idempotente: si ya hay notas no toca nada.
 * Doce filas: con 10 por página el demo ya muestra paginación.
 */
final class NotaSeeder extends AbstractSeed
{
    public function run(): void
    {
        require_once __DIR__ . '/../../../bootstrap/app.php';

        if (Nota::query()->exists()) {
            echo 'NotaSeeder: ya hay notas, no se toca nada.' . PHP_EOL;
            return;
        }

        $titulos = [
            'Comprar pan',
            'Llamar al gasista',
            'Revisar el deploy',
            'Pagar la luz',
            'Leer el RFC',
            'Preparar la reunión',
            'Actualizar el changelog',
            'Regar las plantas',
            'Cargar horas',
            'Revisar el correo',
            'Ordenar el escritorio',
            'Cerrar el sprint',
        ];

        foreach ($titulos as $indice => $titulo) {
            Nota::create([
                'titulo' => $titulo,
                'cuerpo' => $indice % 3 === 0 ? 'Cuerpo de ejemplo para la nota ' . ($indice + 1) . '.' : null,
            ]);
        }

        echo 'NotaSeeder: ' . count($titulos) . ' notas demo.' . PHP_EOL;
    }
}
