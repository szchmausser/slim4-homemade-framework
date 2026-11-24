<?php

declare(strict_types=1);

// Catálogo del demo Tareas en español. Viaja con el scaffold y lo instala
// y lo quita el propio demo: sin `composer demo:tasks:install`, estas
// claves no existen en el proyecto. `nav.tasks` está acá porque el nombre
// del módulo es texto del demo y no del shell.
//
// Mismas claves que `lang/en/tasks.php`. Parámetros: `%date%` y `%nombre%`, que
// `t('clave', {'%nombre%': valor})` sustituye.

return [
    // Etiqueta del módulo en el sidebar y en la portada.
    'nav.tasks' => 'Tareas',

    // Pantalla, fila, estado, modal y flashes.
    'tasks.title'            => 'Tareas',
    'tasks.heading'          => 'Mis tareas',
    'tasks.subtitle'         => 'CRUD de tareas con HTMX, edición inline y paginación sobre SQLite.',
    'tasks.new'              => 'Nueva tarea',
    'tasks.add'              => 'Agregar',
    'tasks.empty'            => 'No hay tareas todavía.',
    'tasks.field_title'      => 'Título de la tarea',
    'tasks.save'             => 'Guardar',
    'tasks.cancel'           => 'Cancelar',
    'tasks.status_done'      => 'Completada',
    'tasks.status_done_at'   => 'Completada el %date%',
    'tasks.status_pending'   => 'Pendiente',
    'tasks.mark_done'        => 'Completar',
    'tasks.mark_pending'     => 'Desmarcar',
    'tasks.edit'             => 'Editar',
    'tasks.delete'           => 'Eliminar',
    'tasks.delete_title'     => '¿Borrar esta tarea?',
    'tasks.delete_confirm'   => 'Borrar',
    'tasks.pagination_label' => 'tareas',
    'tasks.summary'          => 'Lista con edición en línea y cambio de estado: cada acción se intercambia como región HTMX.',
    'tasks.why'              => 'El demo que exprime el shell: región HTMX, paginación del servidor, foco tras el swap y avisos dentro del swap.',
    'tasks.created'          => 'Tarea creada.',
    'tasks.updated'          => 'Tarea actualizada.',
    'tasks.deleted'          => 'Tarea eliminada.',
    'tasks.not_found'        => 'La tarea no existe o ya fue eliminada.',
    'tasks.marked_pending'   => 'Tarea marcada como pendiente.',
    'tasks.completed'        => 'Tarea completada.',
    'tasks.invalid_data'     => 'Datos inválidos.',

    // Listado: búsqueda, filtro y orden (ListQuery). El filtro sólo acepta los
    // valores declarados en el controlador y el orden, sólo sus dos columnas.
    'tasks.search'             => 'Buscar',
    'tasks.search_placeholder' => 'Buscar por título',
    'tasks.filter_status'      => 'Estado',
    'tasks.filter_all'         => 'Todos los estados',
    'tasks.sort_label'         => 'Ordenar por',
    'tasks.sort_title'         => 'Título',
    'tasks.sort_created'       => 'Creada',
];
