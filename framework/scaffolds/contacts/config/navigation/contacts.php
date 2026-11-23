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
    // Las tres son CLAVES del catálogo, no texto: el menú y la portada se arman
    // una vez por proceso y el locale es por request, así que la traducción
    // ocurre al render (`sidebar.twig`, `pages/home.twig`).
    'label'    => 'nav.contacts',
    'href'     => '/contacts',
    'icon'     => 'contacts',
    'key'      => 'contacts',
    // Ficha que el menú muestra para orientar a quien recién llega.
    'summary'  => 'contacts.summary',
    'why'      => 'contacts.why',
    // Orden dentro del menú; los demos instalados se intercalan por este número.
    'position' => 20,
];
