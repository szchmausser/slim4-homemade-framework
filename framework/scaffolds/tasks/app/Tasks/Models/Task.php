<?php

declare(strict_types=1);

namespace App\Tasks\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la entidad `tasks`: qué columnas se escriben y con qué tipo se leen.
 *
 * Capa modelo. Describe la fila y nada más: la tabla la crea la migración
 * `900_create_tasks_table.php`, el controlador escribe a través de este modelo y
 * las vistas reciben sus instancias.
 *
 * Para tu entidad, copia el archivo y ajusta `$fillable`, `$casts` y las
 * anotaciones: PHPStan las lee para tipar cada fila.
 *
 * @property int $id
 * @property string $title
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static Task|null find(int|string $id)
 * @method static Task create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Task> orderByDesc(string $column)
 */
class Task extends Model
{
    // Asignación en masa: `create()` y `update()` sólo aceptan estas columnas.
    protected $fillable = ['title', 'status', 'completed_at'];

    protected $casts = [
        // Carbon y no string: la vista formatea la fecha con `|date()` y el toggle
        // le asigna `Carbon::now()`.
        'completed_at' => 'datetime',
    ];
}
