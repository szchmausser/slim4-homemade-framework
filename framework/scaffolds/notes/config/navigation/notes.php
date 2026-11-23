<?php

declare(strict_types=1);

/**
 * Entrada del demo Notes en el menú lateral.
 *
 * Vive en el scaffold a propósito: `composer demo:notes:install` copia este archivo
 * y `composer demo:notes:remove` lo borra, así que su presencia es el interruptor
 * que muestra u oculta la entrada. No hay un registro central que editar; si el
 * archivo está, la entrada existe.
 *
 * La ficha tiene dos lectores: la copia instalada en `config/navigation/` alimenta
 * el menú lateral (`navigation_items`), y esta copia, leída desde el scaffold,
 * alimenta el catálogo de la portada (`demo_modules`), que lista el módulo aunque
 * no esté instalado. De ahí salen `summary` y `why`: el menú no los usa.
 *
 * Esa es la lección para tu entidad: el menú se arma leyendo el directorio, no
 * agregando una línea a una lista.
 */
return [
    // Las tres son CLAVES del catálogo, no texto: el menú y la portada se arman
    // una vez por proceso y el locale es por request, así que la traducción
    // ocurre al render (`sidebar.twig`, `pages/home.twig`). El idioma del demo
    // no cambia (`notes.*` dice lo mismo en los dos catálogos), pero sus
    // metadatos sí son texto de la portada y se traducen.
    'label'    => 'nav.notes',
    'href'     => '/notes',
    'icon'     => 'list',
    'key'      => 'notes',
    // Ficha que el menú muestra para orientar a quien recién llega.
    'summary'  => 'notes.summary',
    'why'      => 'notes.why',
    // Orden dentro del menú; los demos instalados se intercalan por este número.
    'position' => 30,
];
