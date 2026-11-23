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
 * - El catálogo español: el texto de cada superficie es el literal que se
 *   renderiza hoy. Los tres demos se traducen Y SE TRADUCE SU NOMBRE: `notes`
 *   dejó de ser inglés en los dos catálogos (L10 de `odd/tasks/i18n.md`), así que
 *   su español es una traducción real, sus 24 claves cambian con el locale como
 *   las de `tasks` y `contacts`, y su etiqueta de menú dice «Notas» donde
 *   `nav.tasks` dice «Tareas». Ya no hay ninguna excepción declarada de «clave
 *   que dice lo mismo a propósito»: sólo quedan las palabras que se escriben
 *   igual en los dos idiomas («Email», «#»).
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
     * `notes.*` lleva español de verdad: el demo nació en inglés (L16 de
     * `odd/tasks/demo-conventions.md`) y L10 de `odd/tasks/i18n.md` revirtió esa
     * decisión porque era el único demo que no cambiaba de idioma y bloqueaba
     * reusar sus claves en el tutorial.
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
        'nav.notes'    => 'Notas',

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

        // Demo Notes: el vocabulario sigue al tutorial, que es quien va a
        // reusar estas claves («Borrar nota», «¿Borrar «X»? No se puede
        // deshacer.», «Nota creada.»).
        'notes.title'             => 'Notas',
        'notes.subtitle'          => 'Tus notas, con alta y acciones por fila.',
        'notes.add'               => 'Agregar nota',
        'notes.list_heading'      => 'Todas las notas',
        'notes.create_title'      => 'Nueva nota',
        'notes.create_subtitle'   => 'Ponle un título; el cuerpo es opcional.',
        'notes.create_submit'     => 'Crear nota',
        'notes.edit_title'        => 'Editar nota',
        'notes.save_changes'      => 'Guardar cambios',
        'notes.empty'             => 'No hay notas todavía.',
        'notes.edit_aria'         => 'Editar %title%',
        'notes.delete_aria'       => 'Borrar %title%',
        'notes.delete_title'      => 'Borrar nota',
        'notes.delete_question'   => '¿Borrar «%title%»? No se puede deshacer.',
        'notes.cancel'            => 'Cancelar',
        'notes.delete_confirm'    => 'Borrar',
        'notes.save'              => 'Guardar',
        'notes.field_title'       => 'Título',
        'notes.field_body'        => 'Cuerpo (opcional)',
        'notes.summary'           => 'CRUD mínimo y completo: alta y edición en modales, borrado confirmado y paginación del servidor.',
        'notes.why'               => 'Es el patrón del tutorial con el dominio en inglés (columnas title y body) y el layout plano en app/Models y app/Http/Controllers: compara comportamiento, no ubicación.',
        'notes.created'           => 'Nota creada.',
        'notes.updated'           => 'Nota actualizada.',
        'notes.deleted'           => 'Nota eliminada.',
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
        'nav.notes',
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
     * Claves del catálogo que dicen lo mismo en los dos idiomas.
     *
     * Hoy no hay ninguna y la lista se queda vacía a propósito: era donde vivía
     * `nav.notes` («el nombre del módulo es su propio nombre»), y L10 de
     * `odd/tasks/i18n.md` también lo tradujo a «Notas». Si alguna clave vuelve a
     * quedar igual en los dos catálogos, se declara acá con su motivo en vez de
     * aflojar `TRADUCIDAS`.
     */
    private const MISMO_TEXTO = [];

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

        // Y ninguna excepción que sobreviva: la lista existe para declarar las
        // claves que se repiten a propósito, y hoy está vacía (hasta el nombre
        // del módulo se traduce). Recorrerla fija que la excepción, si vuelve,
        // sea explícita y no un valor copiado de un catálogo al otro.
        foreach (self::MISMO_TEXTO as $clave) {
            self::assertSame(
                $es[$clave],
                $en[$clave],
                "`{$clave}` cambió en un solo catálogo: está declarada como igual en los dos idiomas a propósito."
            );
        }
    }

    /**
     * El demo `notes` es bilingüe: sus 24 claves cambian con el locale.
     *
     * Reemplaza la excepción de L16 de `odd/tasks/demo-conventions.md` (el demo
     * nació en inglés y sus dos catálogos decían lo mismo), revertida en L10 de
     * `odd/tasks/i18n.md`. Se miran TODAS las claves del namespace y no una
     * muestra: si alguien copia el español a `en.php` en una sola, el demo deja
     * de cambiar de idioma en esa pantalla y la clave del tutorial vuelve a
     * mentir.
     */
    public function test_el_demo_notes_cambia_de_idioma_en_todas_sus_claves(): void
    {
        $es = require self::LANG . '/es.php';
        $en = require self::LANG . '/en.php';

        $claves = array_values(array_filter(
            array_keys($es),
            static fn (string $clave): bool => str_starts_with($clave, 'notes.')
        ));

        self::assertNotSame([], $claves, 'lang/es.php no tiene claves `notes.*`: el test estaría pasando en vacío.');

        $sinPareja = array_values(array_diff($claves, array_keys($en)));

        self::assertSame(
            [],
            $sinPareja,
            'Claves de `notes.*` que existen en un solo catálogo: la paridad la fija I18nTest, acá sobra una clave huérfana: '
            . implode(', ', $sinPareja)
        );

        $iguales = [];
        foreach ($claves as $clave) {
            if ($es[$clave] === $en[$clave]) {
                $iguales[] = $clave;
            }
        }

        self::assertSame(
            [],
            $iguales,
            'Estas claves de `notes` dicen lo mismo en los dos idiomas, así que el demo no cambia de idioma: '
            . implode(', ', $iguales)
        );
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

        foreach (['Inicio', 'Tareas', 'Contactos', 'Notas'] as $etiqueta) {
            self::assertStringContainsString('>' . $etiqueta . '<', $espanol, "El sidebar en español no muestra «{$etiqueta}».");
        }

        self::assertStringNotContainsString('>Inicio<', $ingles, 'El sidebar en inglés sigue mostrando la etiqueta española.');
        self::assertStringNotContainsString('>Notes<', $espanol, 'El nombre del módulo no se traduce: el sidebar español sigue en inglés.');
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
        self::assertStringContainsString('Demo de notas', $es, 'La ficha de `notes` no traduce el nombre del módulo.');

        $en = self::texto((string) self::app()->handle(self::request('GET', '/?lang=en'))->getBody());

        self::assertStringContainsString('<span>Home</span>', $en);
        self::assertStringContainsString('inline editing and status toggling', $en);
        self::assertStringContainsString('Remote-mode Tabulator grid', $en);
        self::assertStringContainsString('A minimal, complete CRUD', $en);
        self::assertStringContainsString('tasks demo', $en);
        self::assertStringContainsString('notes demo', $en, 'La ficha de `notes` no traduce el nombre del módulo.');
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
