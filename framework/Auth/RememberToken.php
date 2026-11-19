<?php

declare(strict_types=1);

namespace Framework\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila de `remember_tokens`: un par selector/validator vigente, o el tombstone de
 * uno ya rotado.
 *
 * Es la única tabla que el framework posee y no el dominio. El selector es la
 * clave primaria (string, sin autoincremento) porque es el índice con el que se
 * busca la fila; `rotated_at` marca la que se usó y espera poda. La escribe y la
 * lee solo RememberMe.
 *
 * @property string $selector
 * @property int $user_id
 * @property string $hashed_validator
 * @property int $expires_at
 * @property int|null $rotated_at
 * @method static RememberToken create(array<string, mixed> $attributes = [])
 */
class RememberToken extends Model
{
    protected $table = 'remember_tokens';

    /**
     * Sin `created_at`/`updated_at` de Eloquent: el vencimiento lo llevan
     * `expires_at` y `rotated_at`.
     */
    public $timestamps = false;

    protected $primaryKey = 'selector';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * `create()` de RememberMe asigna en bloque: `fillable` es lo que autoriza esos
     * cinco campos.
     */
    protected $fillable = ['selector', 'user_id', 'hashed_validator', 'expires_at', 'rotated_at'];
}
