<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la entidad Note: qué tabla lee, qué columnas se pueden escribir y qué
 * tipos devuelve cada lectura.
 *
 * Eloquent no sabe de HTTP ni de vistas: describe la fila y nada más. La tabla la
 * crea la migración (`database/migrations/notes/910_create_notes_table.php`), el
 * controlador escribe a través de este modelo y las vistas reciben instancias.
 *
 * La tabla no se declara: Eloquent la deriva del nombre de la clase (`Note` →
 * `notes`). `$table` se escribe sólo cuando la tabla se aparta de esa
 * convención; el tutorial lo muestra con la suya en español.
 *
 * Para crear tu propia entidad, copia este archivo y cambia dos cosas:
 * `$fillable` y las anotaciones `@property`. Las anotaciones no son
 * decorativas: PHPStan las lee para tipar cada fila y detectar un campo que no
 * existe.
 *
 * @property int $id
 * @property string $titulo
 * @property string|null $cuerpo
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static Note|null find(int|string $id)
 * @method static Note create(array<string, mixed> $attributes = [])
 * @method static \Illuminate\Database\Eloquent\Builder<Note> orderByDesc(string $column)
 */
class Note extends Model
{
    protected $fillable = ['titulo', 'cuerpo'];
}
