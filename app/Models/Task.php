<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'done', 'status', 'completed_at'];

    protected $casts = [
        'done'         => 'boolean',
        'completed_at' => 'datetime',
    ];
}
