<?php

declare(strict_types=1);

// Catálogo en español: es la referencia de lo que el usuario ya veía antes de
// la migración (S7). Los textos NO se "mejoran" acá: si cambia una cadena,
// cambia lo que ve el usuario, y I18nTest lo frena.
//
// Claves en inglés y agrupadas por superficie (`pagination.*`, `modal.*`), como
// en cualquier catálogo: la clave nombra el concepto y el valor es lo que se
// traduce. Al agregar una clave, agregala también en `en.php`: los dos
// catálogos se mantienen con las mismas claves.
//
// Parámetros: `%nombre%`, que `t('clave', {'%nombre%': valor})` sustituye.

return [
    // Marca del shell. No se traduce el producto, pero el nombre del layout
    // vive acá para que un cambio de marca no se persiga por tres templates.
    'app.name' => 'Mi App',

    // Campo de formulario.
    'field.optional' => '(opcional)',

    // Pie del shell (datos de ejecución, no configuración de producto).
    'footer.environment' => 'entorno %env%',

    // Selector de idioma del pie: nombre accesible del <select> y del botón.
    'footer.apply'       => 'Cambiar',
    'footer.language'    => 'Idioma',

    // Nombre del idioma en el propio idioma. El selector lo lee del catálogo de
    // CADA locale (no del activo) para que cada uno se reconozca en el suyo.
    'locale.name' => 'Español',

    // Cromo de los layouts: navegación, controles y nombres accesibles.
    'layout.brand_home'      => '%app%, ir al inicio',
    'layout.breadcrumb'      => 'Miga de pan',
    'layout.close_menu'      => 'Cerrar menú',
    'layout.collapse_menu'   => 'Plegar menú',
    'layout.expand_menu'     => 'Desplegar menú',
    'layout.logout'          => 'Cerrar sesión',
    'layout.main_navigation' => 'Navegación principal',
    'layout.open_menu'       => 'Abrir menú',
    'layout.skip_to_content' => 'Saltar al contenido',
    'layout.theme_toggle'    => 'Cambiar entre tema claro y oscuro',

    // Modal nativo: el ✕ del encabezado y el botón del backdrop.
    'modal.close' => 'Cerrar',

    // Paginador reutilizable.
    'pagination.apply'       => 'Aplicar',
    'pagination.first'       => 'Primera',
    'pagination.go_first'    => 'Primera página',
    'pagination.go_last'     => 'Última página',
    'pagination.go_next'     => 'Página siguiente',
    'pagination.go_page'     => 'Página %page%',
    'pagination.go_previous' => 'Página anterior',
    'pagination.label'       => 'registros',
    'pagination.last'        => 'Última',
    'pagination.nav'         => 'Paginación',
    'pagination.next'        => 'Siguiente',
    'pagination.per_page'    => '%label% por página',
    'pagination.previous'    => 'Anterior',
    'pagination.showing'     => 'Mostrando %from% al %to% de %total% %label%',

    // Aviso flotante.
    'toast.close' => 'Cerrar aviso',
];
