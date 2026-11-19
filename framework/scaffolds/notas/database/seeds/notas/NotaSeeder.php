<?php

declare(strict_types=1);

use App\Notas\Models\Nota;
use Phinx\Seed\AbstractSeed;

/**
 * Sembrador del demo Notas: doce filas de ejemplo para que el listado tenga algo
 * que paginar.
 *
 * Phinx lo corre al instalar el demo y escribe por el modelo `Nota`. Es
 * idempotente: si ya hay notas no escribe nada, así que reinstalar no duplica
 * filas.
 *
 * Para tu entidad: copia el archivo, cambia el modelo y los títulos.
 */
final class NotaSeeder extends AbstractSeed
{
    public function run(): void
    {
        // El seeder corre por CLI, fuera de la app: este require arranca el
        // bootstrap, que es lo que deja a Eloquent conectado antes de usar el
        // modelo.
        require_once __DIR__ . '/../../../bootstrap/app.php';

        if (Nota::query()->exists()) {
            echo 'NotaSeeder: ya hay notas, no se toca nada.' . PHP_EOL;
            return;
        }

        // Doce títulos y 10 filas por página: el listado ya nace con dos
        // páginas para probar el paginador.
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
            // Una de cada tres notas lleva cuerpo, así el listado muestra las
            // dos variantes de la fila.
            Nota::create([
                'titulo' => $titulo,
                'cuerpo' => $indice % 3 === 0 ? 'Cuerpo de ejemplo para la nota ' . ($indice + 1) . '.' : null,
            ]);
        }

        echo 'NotaSeeder: ' . count($titulos) . ' notas demo.' . PHP_EOL;
    }
}
