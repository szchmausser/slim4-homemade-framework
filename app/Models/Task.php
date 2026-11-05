<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
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
    protected $fillable = ['title', 'status', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
