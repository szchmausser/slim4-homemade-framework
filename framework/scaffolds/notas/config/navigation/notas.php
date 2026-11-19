<?php

declare(strict_types=1);

/**
 * Entrada del demo Notas en el menú lateral.
 *
 * Vive en el scaffold a propósito: `composer demo:notas:install` copia este archivo
 * y `composer demo:notas:remove` lo borra, así que su presencia es el interruptor
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
    'label'    => 'Notas',
    'href'     => '/notas',
    'icon'     => 'list',
    'key'      => 'notas',
    // Ficha que el menú muestra para orientar a quien recién llega.
    'summary'  => 'CRUD mínimo y completo: alta y edición en modales, borrado confirmado y paginación del servidor.',
    'why'      => 'Es la misma entidad que construye el tutorial, con el código agrupado en app/Notas: compara comportamiento, no ubicación.',
    // Orden dentro del menú; los demos instalados se intercalan por este número.
    'position' => 30,
];
