<?php

declare(strict_types=1);

namespace App\Contacts\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la entidad Contacto: qué tabla lee, qué columnas acepta y qué tipo
 * devuelve cada lectura.
 *
 * Eloquent no conoce HTTP ni Twig: describe la fila. La tabla la crea la
 * migración `920_create_contacts_table.php`, el seeder la llena y el controlador
 * escribe a través de este modelo.
 *
 * Para tu entidad copia el archivo y cambia `$table`, `$fillable` y las
 * anotaciones `@property`: PHPStan las lee para tipar cada fila.
 *
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

    /**
     * Límite de asignación masiva: `create()` y `fill()` aceptan solo estas
     * claves, así que un campo del request que no esté acá se descarta.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'phone'];
}
