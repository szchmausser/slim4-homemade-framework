<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $selector
 * @property int $user_id
 * @property string $hashed_validator
 * @property int $expires_at
 * @method static RememberToken create(array<string, mixed> $attributes = [])
 */
class RememberToken extends Model
{
    protected $table = 'remember_tokens';

    public $timestamps = false;

    protected $primaryKey = 'selector';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['selector', 'user_id', 'hashed_validator', 'expires_at'];
}
