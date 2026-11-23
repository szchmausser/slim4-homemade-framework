<?php

declare(strict_types=1);

/**
 * Entrada del demo Tareas en el menú lateral.
 *
 * Vive en el scaffold a propósito: `composer demo:tasks:install` copia el archivo y
 * `composer demo:tasks:remove` lo borra, así que su presencia es el interruptor
 * que muestra u oculta la entrada. No hay un registro central que editar.
 *
 * Para tu entidad, la lección es la misma: el menú se arma leyendo el directorio.
 */
return [
    // Las tres son CLAVES del catálogo, no texto: el menú y la portada se arman
    // una vez por proceso y el locale es por request, así que la traducción
    // ocurre al render (`sidebar.twig`, `pages/home.twig`).
    'label'    => 'nav.tasks',
    'href'     => '/tasks',
    'icon'     => 'tasks',
    // El sidebar compara esta clave con el `active` que pasa el controlador.
    'key'      => 'tasks',
    // Ficha que la portada muestra para orientar a quien recién llega.
    'summary'  => 'tasks.summary',
    'why'      => 'tasks.why',
    // Orden en el menú y en la portada; los módulos se intercalan por este número.
    'position' => 10,
];
