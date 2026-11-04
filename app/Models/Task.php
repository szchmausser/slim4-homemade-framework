<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'status', 'completed_at'];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}
