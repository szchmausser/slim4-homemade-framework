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
    'summary'  => 'Grilla Tabulator en modo remoto: pagina y ordena el servidor, no el cliente.',
    'why'      => 'Para una tabla avanzada y no una lista: endpoint de datos, whitelist de orden, alta y borrado en modales y edición en página propia.',
    'position' => 20,
];
