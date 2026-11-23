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

    // Etiquetas de navegación (sidebar y migas). Son CLAVES, no texto: el menú
    // se arma una vez por proceso y el locale es por request, así que la clave
    // se traduce al render (`sidebar.twig`, `routes/web.php`).
    'nav.contacts' => 'Contactos',
    'nav.home'     => 'Inicio',
    'nav.notes'    => 'Notas',
    'nav.tasks'    => 'Tareas',

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

    // Portada (`pages/home.twig`). El texto de cada tarjeta de demo NO está
    // acá: sale de los metadatos del scaffold (`label`, `summary`, `why`), que
    // se traducen en la unidad de los demos; lo que sí es de la portada es el
    // armazón que los muestra.
    'home.intro'                   => 'Esqueleto optimizado para desarrollo ágil y asistido por IA en entornos acotados (Windows, Linux o Termux 32 bits offline). Arquitectura limpia con renderizado en el servidor, reactividad ligera sin sobrecarga de SPA, y recursos frontend servidos de forma local sin Node, sin bundlers ni dependencias de CDNs externas.',
    'home.open_demo'               => 'Abrir %name%',
    'home.sign_in'                 => 'Ingresar',
    'home.tagline'                 => 'Framework PHP Fullstack con renderizado en el servidor',
    'home.features.title'          => 'Características operativas',
    'home.feature.ssr.title'       => 'Renderizado en servidor',
    'home.feature.ssr.body'        => 'Plantillas Twig resueltas en el backend sin sobrecarga de SPA.',
    'home.feature.node.title'      => 'Sin toolchain Node',
    'home.feature.node.body'       => 'Cero dependencias npm, sin package.json ni compiladores JS.',
    'home.feature.assets.title'    => 'Recursos locales sin CDN',
    'home.feature.assets.body'     => 'Tailwind y daisyUI 5 vendorizados y servidos con SRI.',
    'home.feature.offline.title'   => 'Operativo 100% offline',
    'home.feature.offline.body'    => 'Funciona sin red, ideal para Termux 32 bits y entornos aislados.',
    'home.feature.nobuild.title'   => 'Cero pasos de compilación',
    'home.feature.nobuild.body'    => 'Edita plantillas Twig o PHP y recarga de inmediato.',
    'home.feature.scaffolds.title' => 'Scaffolds desmontables',
    'home.feature.scaffolds.body'  => 'Demos opcionales instalables o removibles sin dejar residuos.',
    'home.stack.title'             => 'Arquitectura y stack',
    'home.stack.subtitle'          => 'Tecnologías desacopladas, estándar PSR y listas para producción sin pasos de compilación.',
    'home.stack.backend'           => 'Núcleo & Backend',
    'home.stack.frontend'          => 'Frontend & Reactividad',
    'home.stack.data'              => 'Datos & Persistencia',
    'home.stack.quality'           => 'Calidad & Herramientas',
    // Los nombres de las tecnologías (`PHP 8.4+`, `Slim 4`, `Twig`…) son
    // nombres propios y quedan en la plantilla; lo que se traduce es la línea
    // que dice para qué está cada una.
    'home.tech.php'      => 'Lenguaje base y runtime moderno',
    'home.tech.slim'     => 'Router HTTP, middlewares y pipeline PSR-7/15',
    'home.tech.phpdi'    => 'Contenedor de inyección de dependencias PSR-11',
    'home.tech.csrf'     => 'Protección contra falsificación de peticiones',
    'home.tech.dotenv'   => 'Configuración centralizada por variables de entorno',
    'home.tech.respect'  => 'Reglas declarativas para validación de entradas',
    'home.tech.monolog'  => 'Registro estructurado de eventos del sistema',
    'home.tech.twig'     => 'Plantillas server-rendered vía slim/twig-view',
    'home.tech.tailwind' => 'Estilos utilitarios ejecutados localmente sin Node',
    'home.tech.daisyui'  => 'Componentes de interfaz vendorizados y temables',
    'home.tech.htmx'     => 'Navegación reactiva y swaps HTML sin SPA',
    'home.tech.alpine'   => 'Control de estado interactivo en el cliente',
    'home.tech.eloquent' => 'Mapeo objeto-relacional (illuminate/database)',
    'home.tech.sqlite'   => 'Base de datos embebida de cero mantenimiento',
    'home.tech.phinx'    => 'Migraciones idempotentes y versionado de esquema',
    'home.tech.phpunit'  => 'Pruebas unitarias, funcionales y de seguridad',
    'home.tech.phpstan'  => 'Análisis estático de tipos nivel 5 (PHAR)',
    'home.tech.whoops'   => 'Gestor de errores detallado para desarrollo',
    'home.tech.composer' => 'Gestor de paquetes y scripts de verificación',
    'home.modules.title'    => 'Módulos y demos',
    'home.modules.subtitle' => 'Scaffolds opcionales para explorar el stack o usar como base.',
    'home.demo.title'       => 'Demo de %name%',
    'home.demo.installed'   => 'Instalado',
    'home.demo.optional'    => 'Opcional',
    'home.demo.uninstall'   => 'Desinstalar módulo:',
    'home.demo.install'     => 'Comando de instalación:',
    'home.requirements.title'      => 'Requisitos y compatibilidad',
    'home.requirements.envs'       => 'Entornos',
    'home.requirements.envs_value' => 'Termux (ARM 32/64b), Windows, Linux, Mac',
    'home.requirements.php'        => 'PHP',
    'home.requirements.extensions' => 'Extensiones',
    'home.requirements.composer'   => 'Composer',
    'home.requirements.browsers'   => 'Navegadores',
    'home.requirements.note'       => 'Sin Node.js, sin paso de build ni CDN en runtime.',

    // Login (`pages/auth/login.twig`). Las credenciales inválidas y el resto de
    // los avisos del controlador están en `auth.*`.
    'login.email'      => 'Email',
    'login.email_hint' => 'tu@ejemplo.com',
    'login.password'   => 'Clave',
    'login.remember'   => 'Recordarme 30 días',
    'login.submit'     => 'Entrar',
    'login.subtitle'   => 'Ingresa con tu email y clave para continuar.',
    'login.title'      => 'Acceder',

    // Páginas de error (`pages/errors/error.twig` y `_htmx.twig`). El mensaje
    // sale del código de estado y nunca del texto de la excepción: el del
    // vendor viene en inglés y puede arrastrar detalles internos.
    'error.back_home'          => 'Volver al inicio',
    'error.badge'              => 'Error %code%',
    'error.bad_request'        => 'La petición no es válida.',
    'error.forbidden'          => 'No tienes permiso para ver esto.',
    'error.method_not_allowed' => 'Ese método no está permitido.',
    'error.not_found'          => 'No encontramos esa página.',
    'error.server'             => 'Algo salió mal de nuestro lado.',
    'error.title'              => '%code% · %app%',

    // Validador (`framework/Validation/Validator.php`): un mensaje por campo.
    // `max`/`min` usan parámetros con nombre para que la frase se pueda armar
    // en cada idioma (un `%d` fijo obliga a traducir la posición).
    'validation.email'    => 'Este campo debe ser un email válido.',
    'validation.max'      => 'No puede superar los %max% caracteres.',
    'validation.min'      => 'Debe tener al menos %min% caracteres.',
    'validation.numeric'  => 'Este campo debe ser un número.',
    'validation.required' => 'Este campo es obligatorio.',
    'validation.string'   => 'Este campo debe ser texto.',

    // Demos (`framework/scaffolds/**`). Las claves van por demo porque cada
    // scaffold se instala y se borra solo: sus textos no pueden compartir
    // namespace con el shell. Los datos de ejemplo (títulos de tareas, nombres
    // de contactos) NO se traducen: son datos, no interfaz.
    //
    // El demo `notes` también se traduce, nombre del módulo incluido (L10 de
    // `odd/tasks/i18n.md`): nació en inglés completo (L16 de
    // `odd/tasks/demo-conventions.md`) y era el único demo que no cambiaba de
    // idioma, así que sus claves decían lo mismo acá y en `en.php`. Sus textos
    // siguen el vocabulario del tutorial («Agregar nota», «Borrar nota», «Nota
    // creada.»), que es quien va a reusarlas.

    // Tareas.
    'tasks.title'            => 'Tareas',
    'tasks.heading'          => 'Mis tareas',
    'tasks.subtitle'         => 'CRUD de tareas con HTMX, edición inline y paginación sobre SQLite.',
    'tasks.new'              => 'Nueva tarea',
    'tasks.add'              => 'Agregar',
    'tasks.empty'            => 'No hay tareas todavía.',
    'tasks.field_title'      => 'Título de la tarea',
    'tasks.save'             => 'Guardar',
    'tasks.cancel'           => 'Cancelar',
    'tasks.status_done'      => 'Completada',
    'tasks.status_done_at'   => 'Completada el %date%',
    'tasks.status_pending'   => 'Pendiente',
    'tasks.mark_done'        => 'Completar',
    'tasks.mark_pending'     => 'Desmarcar',
    'tasks.edit'             => 'Editar',
    'tasks.delete'           => 'Eliminar',
    'tasks.delete_title'     => '¿Borrar esta tarea?',
    'tasks.delete_confirm'   => 'Borrar',
    'tasks.pagination_label' => 'tareas',
    'tasks.summary'          => 'Lista con edición en línea y cambio de estado: cada acción se intercambia como región HTMX.',
    'tasks.why'              => 'El demo que exprime el shell: región HTMX, paginación del servidor, foco tras el swap y avisos dentro del swap.',
    'tasks.created'          => 'Tarea creada.',
    'tasks.updated'          => 'Tarea actualizada.',
    'tasks.deleted'          => 'Tarea eliminada.',
    'tasks.not_found'        => 'La tarea no existe o ya fue eliminada.',
    'tasks.marked_pending'   => 'Tarea marcada como pendiente.',
    'tasks.completed'        => 'Tarea completada.',
    'tasks.invalid_data'     => 'Datos inválidos.',

    // Contactos.
    'contacts.title'           => 'Contactos',
    'contacts.subtitle'        => 'CRUD de contactos con listado en Tabulator: numeración, columnas y acciones sobre SQLite.',
    'contacts.add'             => 'Agregar contacto',
    'contacts.list_heading'    => 'Listado',
    'contacts.table_label'     => 'Listado de contactos',
    'contacts.needs_js'        => 'Activa JavaScript para ver la tabla interactiva.',
    'contacts.create_title'    => 'Nuevo contacto',
    'contacts.create_subtitle' => 'Completa los datos para agregarlo al listado.',
    'contacts.create_submit'   => 'Crear contacto',
    'contacts.delete_title'    => 'Eliminar contacto',
    'contacts.delete_question' => '¿Eliminar a %name%?',
    'contacts.delete_confirm'  => 'Eliminar',
    'contacts.edit'            => 'Editar',
    'contacts.delete'          => 'Eliminar',
    'contacts.edit_title'      => 'Editar contacto',
    'contacts.cancel'          => 'Cancelar',
    'contacts.save'            => 'Guardar',
    'contacts.save_changes'    => 'Guardar cambios',
    'contacts.back'            => 'Volver a contactos',
    'contacts.edit_subtitle'   => 'Actualiza los datos y guarda los cambios.',
    'contacts.field_name'      => 'Nombre',
    'contacts.field_email'     => 'Email',
    'contacts.field_phone'     => 'Teléfono',
    'contacts.empty'           => 'No hay contactos todavía.',
    'contacts.loading'         => 'Cargando...',
    'contacts.load_error'      => 'No se pudo cargar el formulario.',
    'contacts.collapse_toggle' => 'Mostrar u ocultar los datos de la fila',
    'contacts.column_number'   => '#',
    'contacts.column_name'     => 'Nombre',
    'contacts.column_email'    => 'Email',
    'contacts.column_phone'    => 'Teléfono',
    'contacts.column_actions'  => 'Acciones',
    'contacts.rows_per_page'   => 'Filas',
    'contacts.page'            => 'Página',
    'contacts.all'             => 'Todas',
    'contacts.counter_showing' => 'Mostrando',
    'contacts.counter_of'      => 'de',
    'contacts.counter_rows'    => 'filas',
    'contacts.counter_pages'   => 'páginas',
    'contacts.summary'         => 'Grilla Tabulator en modo remoto: pagina y ordena el servidor, no el cliente.',
    'contacts.why'             => 'Para una tabla avanzada y no una lista: endpoint de datos, whitelist de orden, alta y borrado en modales y edición en página propia.',
    'contacts.created'         => 'Contacto creado.',
    'contacts.updated'         => 'Contacto actualizado.',
    'contacts.deleted'         => 'Contacto eliminado.',
    'contacts.not_found'       => 'El contacto no existe.',
    'contacts.edit_crumb'      => 'Editar',

    // Notas.
    'notes.title'           => 'Notas',
    'notes.subtitle'        => 'Tus notas, con alta y acciones por fila.',
    'notes.add'             => 'Agregar nota',
    'notes.list_heading'    => 'Todas las notas',
    'notes.create_title'    => 'Nueva nota',
    'notes.create_subtitle' => 'Ponle un título; el cuerpo es opcional.',
    'notes.create_submit'   => 'Crear nota',
    'notes.edit_title'      => 'Editar nota',
    'notes.save_changes'    => 'Guardar cambios',
    'notes.empty'           => 'No hay notas todavía.',
    'notes.edit_aria'       => 'Editar %title%',
    'notes.delete_aria'     => 'Borrar %title%',
    'notes.delete_title'    => 'Borrar nota',
    'notes.delete_question' => '¿Borrar «%title%»? No se puede deshacer.',
    'notes.cancel'          => 'Cancelar',
    'notes.delete_confirm'  => 'Borrar',
    'notes.save'            => 'Guardar',
    'notes.field_title'     => 'Título',
    'notes.field_body'      => 'Cuerpo (opcional)',
    'notes.summary'         => 'CRUD mínimo y completo: alta y edición en modales, borrado confirmado y paginación del servidor.',
    'notes.why'             => 'Es el patrón del tutorial con el dominio en inglés (columnas title y body) y el layout plano en app/Models y app/Http/Controllers: compara comportamiento, no ubicación.',
    'notes.created'         => 'Nota creada.',
    'notes.updated'         => 'Nota actualizada.',
    'notes.deleted'         => 'Nota eliminada.',

    // Auth: avisos del controlador, del Guard cuando falla el CSRF y de la
    // puerta de login.
    'auth.csrf_login'          => 'Tu sesión expiró. Vuelve a iniciar sesión.',
    'auth.csrf_reload'         => 'Tu sesión expiró. Recarga la página.',
    'auth.invalid_credentials' => 'Credenciales inválidas.',
    'auth.login_required'      => 'Inicia sesión para continuar.',
    'auth.logged_out'          => 'Sesión cerrada.',
];
