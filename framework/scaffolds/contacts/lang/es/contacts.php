<?php

declare(strict_types=1);

// Catálogo del demo Contactos en español. Viaja con el scaffold y lo
// instala y lo quita el propio demo: sin `composer demo:contacts:install`,
// estas claves no existen en el proyecto. `nav.contacts` está acá porque el
// nombre del módulo es texto del demo y no del shell.
//
// Mismas claves que `lang/en/contacts.php`. Parámetros: `%name%` y `%nombre%`, que
// `t('clave', {'%nombre%': valor})` sustituye.

return [
    // Etiqueta del módulo en el sidebar y en la portada.
    'nav.contacts' => 'Contactos',

    // Pantalla, formulario, modales, tabla y flashes.
    'contacts.title'           => 'Contactos',
    'contacts.subtitle'        => 'CRUD de contactos con listado en Tabulator: numeración, columnas y acciones sobre SQLite.',
    'contacts.add'             => 'Agregar contacto',
    'contacts.list_heading'    => 'Listado',
    'contacts.table_label'     => 'Listado de contactos',
    'contacts.needs_js'        => 'Activa JavaScript para ver la tabla interactiva.',
    'contacts.create_title'    => 'Nuevo contacto',
    'contacts.create_subtitle' => 'Completa los datos para agregarlo al listado.',
    'contacts.create_submit'   => 'Crear contacto',
    'contacts.delete_title'    => 'Eliminar contacto',
    'contacts.delete_question' => '¿Eliminar a %name%?',
    'contacts.delete_confirm'  => 'Eliminar',
    'contacts.edit'            => 'Editar',
    'contacts.delete'          => 'Eliminar',
    'contacts.edit_title'      => 'Editar contacto',
    'contacts.cancel'          => 'Cancelar',
    'contacts.save'            => 'Guardar',
    'contacts.save_changes'    => 'Guardar cambios',
    'contacts.back'            => 'Volver a contactos',
    'contacts.edit_subtitle'   => 'Actualiza los datos y guarda los cambios.',
    'contacts.field_name'      => 'Nombre',
    'contacts.field_email'     => 'Email',
    'contacts.field_phone'     => 'Teléfono',
    'contacts.empty'           => 'No hay contactos todavía.',
    'contacts.loading'         => 'Cargando...',
    'contacts.load_error'      => 'No se pudo cargar el formulario.',
    'contacts.collapse_toggle' => 'Mostrar u ocultar los datos de la fila',
    'contacts.column_number'   => '#',
    'contacts.column_name'     => 'Nombre',
    'contacts.column_email'    => 'Email',
    'contacts.column_phone'    => 'Teléfono',
    'contacts.column_actions'  => 'Acciones',
    'contacts.rows_per_page'   => 'Filas',
    'contacts.page'            => 'Página',
    'contacts.all'             => 'Todas',
    'contacts.counter_showing' => 'Mostrando',
    'contacts.counter_of'      => 'de',
    'contacts.counter_rows'    => 'filas',
    'contacts.counter_pages'   => 'páginas',
    'contacts.summary'         => 'Grilla Tabulator en modo remoto: pagina y ordena el servidor, no el cliente.',
    'contacts.why'             => 'Para una tabla avanzada y no una lista: endpoint de datos, whitelist de orden, alta y borrado en modales y edición en página propia.',
    'contacts.created'         => 'Contacto creado.',
    'contacts.updated'         => 'Contacto actualizado.',
    'contacts.deleted'         => 'Contacto eliminado.',
    'contacts.not_found'       => 'El contacto no existe.',
    'contacts.edit_crumb'      => 'Editar',
];
