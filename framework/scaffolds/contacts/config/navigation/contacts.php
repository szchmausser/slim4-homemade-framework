<?php

declare(strict_types=1);

/**
 * Entrada del demo Contactos en el menú lateral.
 *
 * Vive en el scaffold: `composer demo:contacts:install` copia este archivo y
 * `composer demo:contacts:remove` lo borra, así que su presencia es el interruptor
 * que muestra u oculta la entrada. No hay un registro central que editar.
 *
 * Esa es la lección para tu entidad: el menú se arma leyendo el directorio.
 */
return [
    'label'    => 'Contactos',
    'href'     => '/contactos',
    'icon'     => 'contacts',
    'key'      => 'contacts',
    // Ficha que el menú muestra para orientar a quien recién llega.
    'summary'  => 'Grilla Tabulator en modo remoto: pagina y ordena el servidor, no el cliente.',
    'why'      => 'Para una tabla avanzada y no una lista: endpoint de datos, whitelist de orden, alta y borrado en modales y edición en página propia.',
    // Orden dentro del menú; los demos instalados se intercalan por este número.
    'position' => 20,
];
