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
    'position' => 30,
];
