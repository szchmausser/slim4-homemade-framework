<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RememberToken extends Model
{
    protected $table = 'remember_tokens';

    public $timestamps = false;

    protected $primaryKey = 'selector';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['selector', 'user_id', 'hashed_validator', 'expires_at'];
}
