<?php

declare(strict_types=1);

namespace App\Contacts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @method static Contact|null find(int|string $id)
 * @method static Contact create(array<string, mixed> $attributes = [])
 * @method static int destroy(int|string $id)
 * @method static \Illuminate\Database\Eloquent\Builder<Contact> query()
 */
final class Contact extends Model
{
    protected $table = 'contacts';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'phone'];
}
