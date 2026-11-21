<?php

declare(strict_types=1);

/**
 * Entrada del demo Tareas en el menú lateral.
 *
 * Vive en el scaffold a propósito: `composer demo:tasks:install` copia el archivo y
 * `composer demo:tasks:remove` lo borra, así que su presencia es el interruptor
 * que muestra u oculta la entrada. No hay un registro central que editar.
 *
 * Para tu entidad, la lección es la misma: el menú se arma leyendo el directorio.
 */
return [
    'label'    => 'Tareas',
    'href'     => '/tasks',
    'icon'     => 'tasks',
    // El sidebar compara esta clave con el `active` que pasa el controlador.
    'key'      => 'tasks',
    // Ficha que la portada muestra para orientar a quien recién llega.
    'summary'  => 'Lista con edición en línea y cambio de estado: cada acción se intercambia como región HTMX.',
    'why'      => 'El demo que exprime el shell: región HTMX, paginación del servidor, foco tras el swap y avisos dentro del swap.',
    // Orden en el menú y en la portada; los módulos se intercalan por este número.
    'position' => 10,
];
