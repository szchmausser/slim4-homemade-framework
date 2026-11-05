<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $email
 * @property string $password_hash
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static User|null find(int|string $id)
 */
class User extends Model
{
    protected $fillable = ['email', 'password_hash'];

    protected $hidden = ['password_hash'];
}
