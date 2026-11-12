<?php

declare(strict_types=1);

// Entrada del demo en el menú. Vive en el scaffold: `demo:contacts:install` la
// copia y `demo:contacts:remove` la borra, así que la presencia del archivo es
// el interruptor.
return [
    'label'    => 'Contactos',
    'href'     => '/contactos',
    'icon'     => 'contacts',
    'key'      => 'contacts',
    'position' => 20,
];
