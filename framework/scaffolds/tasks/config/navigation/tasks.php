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
    'position' => 10,
];
