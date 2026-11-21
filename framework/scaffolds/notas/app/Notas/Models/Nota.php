<?php

declare(strict_types=1);

namespace App\Notas\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la entidad Nota: qué tabla lee, qué columnas se pueden escribir y qué
 * tipos devuelve cada lectura.
 *
 * Eloquent no sabe de HTTP ni de vistas: describe la fila y nada más. La tabla la
 * crea la migración (`database/migrations/notas/910_create_notas_table.php`), el
 * controlador escribe a través de este modelo y las vistas reciben instancias.
 *
 * Para crear tu propia entidad, copia este archivo y cambia tres cosas: `$table`,
 * `$fillable` y las anotaciones `@property`. Las anotaciones no son decorativas:
 * PHPStan las lee para tipar cada fila y detectar un campo que no existe.
 *
 * @property int $id
 * @property string $titulo
 * @property string|null $cuerpo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static Nota|null find(int|string $id)
 * @method static Nota create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Nota> orderByDesc(string $column)
 */
class Nota extends Model
{
    protected $table = 'notas';

    protected $fillable = ['titulo', 'cuerpo'];
}
