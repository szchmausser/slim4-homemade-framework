<?php

declare(strict_types=1);

namespace App\Notas\Models;

use Illuminate\Database\Eloquent\Model;

/**
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
