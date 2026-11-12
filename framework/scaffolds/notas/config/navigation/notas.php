<?php

declare(strict_types=1);

// Entrada del demo en el menú. Vive en el scaffold: `demo:notas:install` la
// copia y `demo:notas:remove` la borra, así que la presencia del archivo es el
// interruptor.
return [
    'label'    => 'Notas',
    'href'     => '/notas',
    'icon'     => 'list',
    'key'      => 'notas',
    'summary'  => 'CRUD mínimo y completo: alta y edición en modales, borrado confirmado y paginación del servidor.',
    'why'      => 'Es la entidad del tutorial: la referencia para comparar cuando seguiste el recorrido paso a paso.',
    'position' => 30,
];
