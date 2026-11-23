<?php

declare(strict_types=1);

namespace Tests\Support;

use Slim\Views\Twig;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

/**
 * Cierre del multilingüe en el frontend: los tres demos, el JS de contacts y
 * las etiquetas de navegación.
 *
 * Qué fija acá, y por qué en la suite base:
 * - El catálogo español (S7): el texto de cada superficie nueva es el literal
 *   que ya se renderizaba. En `notes` ese literal está en inglés por diseño
 *   (L16 de `odd/tasks/demo-conventions.md`: «el demo deja de mezclar»), así
 *   que su «español» es el mismo inglés en los dos catálogos y el demo no
 *   cambia de idioma; lo que sí cambia es de dónde sale el texto.
 * - Los metadatos de los módulos son CLAVES y no texto: la portada y el sidebar
 *   los resuelven al render. Si alguien vuelve a escribir «Tareas» en
 *   `config/navigation/tasks.php`, la ficha de la portada deja de traducirse y
 *   este archivo lo frena.
 * - El sidebar traduce `navigation_items` en la plantilla, no en el array del
 *   contenedor: ese array se arma una vez por proceso (el Twig es singleton),
 *   así que una etiqueta resuelta ahí quedaría clavada en el idioma del primer
 *   request.
 * - El aviso de la puerta de login sale del catálogo. Se prueba con la ruta
 *   `/_test/protected` que arma `AppFactory`, así el caso corre sin demos.
 * - El JS de contacts no lleva texto visible: lo recibe del servidor. La
 *   comprobación estática vive acá (el archivo está siempre en el repo) y la
 *   del JSON que recibe Tabulator vive en el scaffold, que sólo corre instalado.
 */
final class I18nDemosTest extends HttpTestCase
{
    private const LANG = __DIR__ . '/../../lang';

    private const SCAFFOLDS = __DIR__ . '/../../framework/scaffolds';

    private const CONTACTS_JS = __DIR__ . '/../../framework/scaffolds/contacts/public/assets/js/contacts-tabulator.js';

    /**
     * Español de las superficies de esta unidad, copiado del render anterior al
     * cambio. Es la referencia de S7: si alguien «mejora» una cadena en
     * `lang/es.php`, el usuario ve otro texto y este test lo frena.
     *
     * Las claves de `notes.*` llevan el inglés del demo a propósito: nace en
     * inglés (L16) y los dos catálogos dicen lo mismo. `notes.summary` y
     * `notes.why` son la excepción porque los metadatos del módulo sí estaban en
     * español y se traducen como cualquier ficha.
     *
     * Tiene que cubrir TODO el namespace de `tasks.`, `contacts.`, `notes.` y
     * `nav.`: una clave nueva que no entre acá deja su español sin protección.
     */
    private const ESPANOL_DEMOS = [
        // Etiquetas de navegación: el ítem fijo del contenedor, los tres demos
        // instalables y la miga de la portada.
        'nav.home'     => 'Inicio',
        'nav.tasks'    => 'Tareas',
        'nav.contacts' => 'Contactos',
        'nav.notes'    => 'Notes',

        // Demo Tareas: pantalla, fila, estado, modal y flashes.
        'tasks.title'           => 'Tareas',
        'tasks.heading'         => 'Mis tareas',
        'tasks.subtitle'        => 'CRUD de tareas con HTMX, edición inline y paginación sobre SQLite.',
        'tasks.new'             => 'Nueva tarea',
        'tasks.add'             => 'Agregar',
        'tasks.empty'           => 'No hay tareas todavía.',
        'tasks.field_title'     => 'Título de la tarea',
        'tasks.save'            => 'Guardar',
        'tasks.cancel'          => 'Cancelar',
        'tasks.status_done'     => 'Completada',
        'tasks.status_done_at'  => 'Completada el %date%',
        'tasks.status_pending'  => 'Pendiente',
        'tasks.mark_done'       => 'Completar',
        'tasks.mark_pending'    => 'Desmarcar',
        'tasks.edit'            => 'Editar',
        'tasks.delete'          => 'Eliminar',
        'tasks.delete_title'    => '¿Borrar esta tarea?',
        'tasks.delete_confirm'  => 'Borrar',
        'tasks.pagination_label' => 'tareas',
        'tasks.summary'         => 'Lista con edición en línea y cambio de estado: cada acción se intercambia como región HTMX.',
        'tasks.why'             => 'El demo que exprime el shell: región HTMX, paginación del servidor, foco tras el swap y avisos dentro del swap.',
        'tasks.created'         => 'Tarea creada.',
        'tasks.updated'         => 'Tarea actualizada.',
        'tasks.deleted'         => 'Tarea eliminada.',
        'tasks.not_found'       => 'La tarea no existe o ya fue eliminada.',
        'tasks.marked_pending'  => 'Tarea marcada como pendiente.',
        'tasks.completed'       => 'Tarea completada.',
        'tasks.invalid_data'    => 'Datos inválidos.',

        // Demo Contactos: pantalla, formulario, modales, tabla y flashes.
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

        // Demo Notes: el texto del demo es inglés en los dos catálogos (L16);
        // los metadatos del módulo, en cambio, se traducen.
        'notes.title'             => 'Notes',
        'notes.subtitle'          => 'Your notes, with creation and row actions.',
        'notes.add'               => 'Add note',
        'notes.list_heading'      => 'All notes',
        'notes.create_title'      => 'New note',
        'notes.create_subtitle'   => 'Give it a title; the body is optional.',
        'notes.create_submit'     => 'Create note',
        'notes.edit_title'        => 'Edit note',
        'notes.save_changes'      => 'Save changes',
        'notes.empty'             => 'No notes yet.',
        'notes.edit_aria'         => 'Edit %title%',
        'notes.delete_aria'       => 'Delete %title%',
        'notes.delete_title'      => 'Delete note',
        'notes.delete_question'   => 'Delete "%title%"? This cannot be undone.',
        'notes.cancel'            => 'Cancel',
        'notes.delete_confirm'    => 'Delete',
        'notes.save'              => 'Save',
        'notes.field_title'       => 'Title',
        'notes.field_body'        => 'Body (optional)',
        'notes.summary'           => 'CRUD mínimo y completo: alta y edición en modales, borrado confirmado y paginación del servidor.',
        'notes.why'               => 'Es el patrón del tutorial con el dominio en inglés (columnas title y body) y el layout plano en app/Models y app/Http/Controllers: compara comportamiento, no ubicación.',
        'notes.created'           => 'Note created.',
        'notes.updated'           => 'Note updated.',
        'notes.deleted'           => 'Note deleted.',
    ];

    /**
     * Claves que TIENEN que decir algo distinto en cada idioma: si el valor se
     * copió de `es.php` a `en.php`, el cambio de idioma no se nota y el catálogo
     * inglés queda de adorno.
     */
    private const TRADUCIDAS = [
        'nav.home',
        'nav.tasks',
        'nav.contacts',
        'tasks.title',
        'tasks.heading',
        'tasks.empty',
        'tasks.add',
        'tasks.created',
        'tasks.not_found',
        'tasks.summary',
        'tasks.why',
        'contacts.title',
        'contacts.add',
        'contacts.empty',
        'contacts.created',
        'contacts.not_found',
        'contacts.field_phone',
        'contacts.counter_rows',
        'notes.summary',
        'notes.why',
    ];

    /**
     * Claves que dicen lo MISMO en los dos idiomas a propósito: el texto del
     * demo `notes` nace en inglés (L16) y el nombre del módulo es su propio
     * nombre.
     */
    private const MISMO_TEXTO = [
        'nav.notes',
        'notes.title',
        'notes.add',
        'notes.empty',
        'notes.cancel',
        'notes.field_body',
        'notes.created',
    ];

    private static function twig(): Environment
    {
        return self::app()->getContainer()->get(Twig::class)->getEnvironment();
    }

    /** Texto del render con las entidades HTML decodificadas: se compara lo que se lee. */
    private static function texto(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Metadatos de los tres módulos, leídos del scaffold (existan o no como
     * demo instalado): la portada los lista igual, así que la ficha se traduce
     * también sin instalar nada.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function modules(): array
    {
        $modules = [];

        foreach (glob(self::SCAFFOLDS . '/*/config/navigation/*.php') ?: [] as $file) {
            $meta = require $file;

            self::assertIsArray($meta, $file . ' tiene que devolver un array.');
            $modules[basename($file, '.php')] = $meta;
        }

        self::assertCount(3, $modules, 'Se esperaban los metadatos de tasks, contacts y notes.');

        return $modules;
    }

    /** S7: el catálogo en español de los demos conserva el texto que ya se renderizaba. */
    public function test_el_catalogo_es_de_los_demos_conserva_el_texto_de_head(): void
    {
        $es = require self::LANG . '/es.php';

        foreach (self::ESPANOL_DEMOS as $clave => $texto) {
            self::assertArrayHasKey($clave, $es, "Falta la clave `{$clave}` en lang/es.php.");
            self::assertSame($texto, $es[$clave], "El texto de `{$clave}` cambió para el usuario.");
        }
    }

    /** S7: la referencia no se queda corta cuando un demo suma una clave. */
    public function test_la_referencia_cubre_las_claves_de_los_demos_y_la_navegacion(): void
    {
        $es = require self::LANG . '/es.php';

        $deDemos = array_values(array_filter(
            array_keys($es),
            static fn (string $clave): bool => (bool) preg_match('/^(tasks|contacts|notes|nav)\./', $clave)
        ));

        self::assertNotSame([], $deDemos, 'lang/es.php no tiene claves de los demos: el test estaría pasando en vacío.');

        $faltan = array_values(array_diff($deDemos, array_keys(self::ESPANOL_DEMOS)));

        self::assertSame(
            [],
            $faltan,
            'Faltan en la referencia de S7, así que su español no lo protege nadie: ' . implode(', ', $faltan)
        );
    }

    /**
     * Los metadatos de cada módulo son claves, no texto.
     *
     * Es la mitad del contrato: `label`, `summary` y `why` tienen que existir en
     * los dos catálogos. Si alguien escribe el texto directamente en
     * `config/navigation/<demo>.php`, la portada vuelve a mostrar español en
     * inglés y la clave no aparece en ningún catálogo.
     */
    public function test_los_metadatos_de_los_modulos_son_claves_de_los_dos_catalogos(): void
    {
        $es = require self::LANG . '/es.php';
        $en = require self::LANG . '/en.php';

        foreach (self::modules() as $demo => $meta) {
            foreach (['label', 'summary', 'why'] as $campo) {
                self::assertArrayHasKey($campo, $meta, "El módulo `{$demo}` no declara `{$campo}`.");

                $clave = $meta[$campo];

                self::assertIsString($clave, "`{$campo}` de `{$demo}` tiene que ser una clave, no texto.");
                self::assertArrayHasKey($clave, $es, "La clave `{$clave}` de `{$demo}` no está en lang/es.php.");
                self::assertArrayHasKey($clave, $en, "La clave `{$clave}` de `{$demo}` no está en lang/en.php.");
            }
        }
    }

    /** El catálogo inglés es una traducción, no el español copiado. */
    public function test_el_catalogo_en_traduce_las_superficies_nuevas(): void
    {
        $es = require self::LANG . '/es.php';
        $en = require self::LANG . '/en.php';

        foreach (self::TRADUCIDAS as $clave) {
            self::assertArrayHasKey($clave, $en, "Falta la clave `{$clave}` en lang/en.php.");
            self::assertNotSame(
                $es[$clave],
                $en[$clave],
                "`{$clave}` tiene el mismo texto en los dos idiomas: no está traducido."
            );
        }

        // Y la excepción documentada: `notes` es inglés por diseño, así que sus
        // dos catálogos dicen lo mismo. Si alguien traduce sólo un lado, el
        // demo empieza a cambiar de idioma sin que nadie lo haya decidido.
        foreach (self::MISMO_TEXTO as $clave) {
            self::assertSame(
                $es[$clave],
                $en[$clave],
                "`{$clave}` cambió en un solo catálogo: `notes` dice lo mismo en los dos idiomas a propósito."
            );
        }
    }

    /**
     * El sidebar traduce las etiquetas de la navegación al render.
     *
     * Se arma la lista como la deja el contenedor (la clave, no el texto) y se
     * renderiza la plantilla en los dos idiomas. Se fija el locale en el
     * traductor del contenedor y se restaura en `finally`: es estado del
     * proceso, y el middleware lo reescribe en cada request.
     */
    public function test_el_sidebar_traduce_las_etiquetas_de_los_tres_demos(): void
    {
        $navigation = [['label' => 'nav.home', 'href' => '/', 'icon' => 'home', 'key' => 'home']];

        foreach (self::modules() as $demo => $meta) {
            $navigation[] = ['label' => $meta['label'], 'href' => $meta['href'], 'icon' => $meta['icon'], 'key' => $demo];
        }

        $container = self::app()->getContainer();
        $translator = $container->get(LocaleAwareInterface::class);
        $twig = self::twig();

        try {
            $translator->setLocale('en');
            $ingles = $twig->render('layouts/parts/sidebar.twig', [
                'navigation_items' => $navigation,
                'active'           => 'home',
            ]);

            $translator->setLocale('es');
            $espanol = $twig->render('layouts/parts/sidebar.twig', [
                'navigation_items' => $navigation,
                'active'           => 'home',
            ]);
        } finally {
            $translator->setLocale('es');
        }

        foreach (['Home', 'Tasks', 'Contacts', 'Notes'] as $etiqueta) {
            self::assertStringContainsString('>' . $etiqueta . '<', $ingles, "El sidebar en inglés no muestra «{$etiqueta}».");
        }

        foreach (['Inicio', 'Tareas', 'Contactos', 'Notes'] as $etiqueta) {
            self::assertStringContainsString('>' . $etiqueta . '<', $espanol, "El sidebar en español no muestra «{$etiqueta}».");
        }

        self::assertStringNotContainsString('>Inicio<', $ingles, 'El sidebar en inglés sigue mostrando la etiqueta española.');
        self::assertStringNotContainsString('>nav.home<', $espanol, 'La clave salió cruda: no se está traduciendo en el render.');
    }

    /**
     * La portada resuelve las fichas de los módulos en el idioma de la petición.
     *
     * `demo_modules` lista los tres scaffolds aunque no estén instalados, así
     * que este caso corre en la suite base: es la superficie donde hoy se ve
     * más español en una visita en inglés.
     */
    public function test_la_portada_traduce_las_fichas_de_los_modulos(): void
    {
        $es = self::texto((string) self::app()->handle(self::request('GET', '/'))->getBody());

        self::assertStringContainsString('<span>Inicio</span>', $es);
        self::assertStringContainsString('Lista con edición en línea y cambio de estado', $es);
        self::assertStringContainsString('Grilla Tabulator en modo remoto', $es);
        self::assertStringContainsString('CRUD mínimo y completo', $es);
        self::assertStringContainsString('Demo de tareas', $es);

        $en = self::texto((string) self::app()->handle(self::request('GET', '/?lang=en'))->getBody());

        self::assertStringContainsString('<span>Home</span>', $en);
        self::assertStringContainsString('inline editing and status toggling', $en);
        self::assertStringContainsString('Remote-mode Tabulator grid', $en);
        self::assertStringContainsString('A minimal, complete CRUD', $en);
        self::assertStringContainsString('tasks demo', $en);
        self::assertStringNotContainsString('Lista con edición en línea', $en);
        self::assertStringNotContainsString('<span>Inicio</span>', $en);
        self::assertStringNotContainsString('tasks.summary', $en, 'La clave salió cruda: no se está traduciendo.');
    }

    /**
     * El aviso de `RequireAuthMiddleware` sale del catálogo.
     *
     * Se usa la ruta `/_test/protected` que arma `AppFactory`: es la única ruta
     * protegida que existe sin demos instalados. El flash se escribe antes del
     * 303 y lo lee la pantalla de acceso en el `GET` siguiente.
     */
    public function test_el_aviso_de_la_puerta_de_login_sale_en_el_idioma_activo(): void
    {
        $response = self::app()->handle(self::request('GET', '/_test/protected?lang=en'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));

        $ingles = self::texto((string) self::app()->handle(self::request('GET', '/login?lang=en'))->getBody());

        self::assertStringContainsString('Sign in to continue.', $ingles);
        self::assertStringNotContainsString('Inicia sesión para continuar.', $ingles);

        // El `?lang=en` de arriba quedó recordado en la sesión (es la elección
        // explícita del visitante), así que el caso en español pide `es`
        // explícito: así el test no depende de lo que dejó la mitad anterior.
        self::app()->handle(self::request('GET', '/_test/protected?lang=es'));
        $espanol = self::texto((string) self::app()->handle(self::request('GET', '/login?lang=es'))->getBody());

        self::assertStringContainsString('Inicia sesión para continuar.', $espanol);
        self::assertStringNotContainsString('Sign in to continue.', $espanol);
    }

    /**
     * El JS del demo de contacts no lleva texto visible: lo recibe del servidor.
     *
     * Se mira el archivo instalable del scaffold, que es el que se sirve con el
     * demo. La lista de abajo son los literales que estaban incrustados: si
     * vuelve uno, la tabla queda a medias al cambiar de idioma y este test lo
     * frena. Se revisan sólo las cadenas (`'…'`, `"…"`, `` `…` ``), no los
     * comentarios, que están en español a propósito.
     */
    public function test_el_js_de_contacts_no_tiene_textos_de_ui_en_espanol(): void
    {
        $js = file_get_contents(self::CONTACTS_JS);

        self::assertNotFalse($js, 'No se pudo leer ' . self::CONTACTS_JS);

        self::assertStringContainsString(
            'contacts-table-i18n',
            $js,
            'El JS no lee el bloque de textos que manda el servidor.'
        );
        self::assertStringContainsString('JSON.parse', $js, 'El JS no parsea el JSON de textos.');
        self::assertStringNotContainsString(
            "'es'",
            $js,
            'El JS sigue declarando el locale de Tabulator como un literal: los textos vienen del servidor.'
        );

        $cadenas = [];
        preg_match_all('/\'[^\'\\\\\n]*(?:\\\\.[^\'\\\\\n]*)*\'|"[^"\\\\\n]*(?:\\\\.[^"\\\\\n]*)*"|`[^`\\\\]*(?:\\\\.[^`\\\\]*)*`/', $js, $cadenas);

        self::assertNotSame([], $cadenas[0], 'No se pudo extraer ninguna cadena del JS: el test estaría pasando en vacío.');

        $espanol = [
            'Editar', 'Eliminar', 'Cargando', 'No se pudo', 'No hay contactos', 'Nombre',
            'Teléfono', 'Acciones', 'Filas', 'Página', 'Primera', 'Última', 'Anterior',
            'Siguiente', 'Todas', 'Mostrando', 'Mostrar u ocultar', 'filas', 'páginas',
        ];

        foreach ($cadenas[0] as $cadena) {
            foreach ($espanol as $texto) {
                self::assertStringNotContainsString(
                    $texto,
                    $cadena,
                    "El JS todavía lleva texto de UI en español: {$cadena}"
                );
            }
        }
    }
}
