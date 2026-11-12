<?php

declare(strict_types=1);

// Entrada del demo en el menú. Vive en el scaffold: `demo:tasks:install` la
// copia y `demo:tasks:remove` la borra, así que la presencia del archivo es el
// interruptor.
return [
    'label'    => 'Tareas',
    'href'     => '/tareas',
    'icon'     => 'tasks',
    'key'      => 'tareas',
    'summary'  => 'Lista con edición en línea y cambio de estado: cada acción se intercambia como región HTMX.',
    'why'      => 'El demo que exprime el shell: región HTMX, paginación del servidor, foco tras el swap y avisos dentro del swap.',
    'position' => 10,
];
