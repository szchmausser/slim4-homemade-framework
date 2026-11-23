<?php

declare(strict_types=1);

// Catálogo del demo Notas en español. Viaja con el scaffold y lo instala y
// lo quita el propio demo: sin `composer demo:notes:install`, estas claves
// no existen en el proyecto. `nav.notes` está acá porque el nombre del
// módulo es texto del demo y no del shell.
//
// El vocabulario sigue al tutorial («Agregar nota», «Borrar nota», «Nota
// creada.»), que es quien va a reusar estas claves.
//
// Mismas claves que `lang/en/notes.php`. Parámetros: `%title%`, que
// `t('clave', {'%nombre%': valor})` sustituye.

return [
    // Etiqueta del módulo en el sidebar y en la portada.
    'nav.notes' => 'Notas',

    // Pantalla, modales y flashes.
    'notes.title'           => 'Notas',
    'notes.subtitle'        => 'Tus notas, con alta y acciones por fila.',
    'notes.add'             => 'Agregar nota',
    'notes.list_heading'    => 'Todas las notas',
    'notes.create_title'    => 'Nueva nota',
    'notes.create_subtitle' => 'Ponle un título; el cuerpo es opcional.',
    'notes.create_submit'   => 'Crear nota',
    'notes.edit_title'      => 'Editar nota',
    'notes.save_changes'    => 'Guardar cambios',
    'notes.empty'           => 'No hay notas todavía.',
    'notes.edit_aria'       => 'Editar %title%',
    'notes.delete_aria'     => 'Borrar %title%',
    'notes.delete_title'    => 'Borrar nota',
    'notes.delete_question' => '¿Borrar «%title%»? No se puede deshacer.',
    'notes.cancel'          => 'Cancelar',
    'notes.delete_confirm'  => 'Borrar',
    'notes.save'            => 'Guardar',
    'notes.field_title'     => 'Título',
    'notes.field_body'      => 'Cuerpo (opcional)',
    'notes.summary'         => 'CRUD mínimo y completo: alta y edición en modales, borrado confirmado y paginación del servidor.',
    'notes.why'             => 'Es el patrón del tutorial con el dominio en inglés (columnas title y body) y el layout plano en app/Models y app/Http/Controllers: compara comportamiento, no ubicación.',
    'notes.created'         => 'Nota creada.',
    'notes.updated'         => 'Nota actualizada.',
    'notes.deleted'         => 'Nota eliminada.',
];
