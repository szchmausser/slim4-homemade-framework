<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migración del demo Notas: crea la tabla `notas`, la única fuente del esquema.
 *
 * La aplica Phinx con `composer migrate` o al instalar el demo; el modelo y el
 * controlador sólo asumen las columnas que declara este archivo.
 *
 * Para tu entidad: copia el archivo con un número libre —Phinx rechaza dos
 * migraciones con la misma versión—, renombra la clase y ajusta las columnas.
 */
final class CreateNotasTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('notas')
            // El límite replica el `max:120` del controlador y el `maxlength`
            // de la vista: los tres tienen que decir lo mismo.
            ->addColumn('titulo', 'string', ['limit' => 120])
            // Nullable porque el cuerpo es opcional; el controlador guarda
            // `null` cuando llega vacío.
            ->addColumn('cuerpo', 'text', ['null' => true])
            // created_at y updated_at son las columnas que Eloquent escribe
            // solo en cada `save()`, y las que declara el modelo.
            ->addTimestamps()
            ->create();
    }
}
