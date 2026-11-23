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
 * - El catálogo español de los demos: el texto de cada superficie es el literal
 *   que se renderiza hoy. La referencia se lee del SCAFFOLD
 *   (`framework/scaffolds/<demo>/lang/es/<demo>.php`) y no del repo: el catálogo
 *   de un demo existe en `lang/<locale>/` sólo mientras el demo está instalado
 *   (S1-S2), y esta suite corre sin demos. Los tres demos se traducen Y SE
 *   TRADUCE SU NOMBRE: `notes` dejó de ser inglés en los dos catálogos (L10 de
 *   `odd/tasks/i18n.md`), así que su español es una traducción real, sus 24
 *   claves cambian con el locale como las de `tasks` y `contacts`, y su
 *   etiqueta de menú dice «Notas» donde `nav.tasks` dice «Tareas». Ya no hay
 *   ninguna excepción declarada de «clave que dice lo mismo a propósito»: sólo
 *   quedan las palabras que se escriben igual en los dos idiomas («Email», «#»).
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
     * cambio. Es la referencia de S7: si alguien «mejora» una cadena en el
     * catálogo de un demo, el usuario ve otro texto y este test lo frena.
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

    /**
     * Catálogo de un demo en un idioma, tal como lo instala el scaffold.
     *
     * El destino instalado (`lang/<locale>/<demo>.php`) no sirve de referencia:
     * existe sólo mientras el demo está instalado. El scaffold es la fuente que
     * el instalador copia, así que su texto es el mismo en los dos estados.
     *
     * @return array<string, string>
     */
    private static function catalogoDelDemo(string $demo, string $locale): array
    {
        $file = self::SCAFFOLDS . '/' . $demo . '/lang/' . $locale . '/' . $demo . '.php';

        self::assertFileExists($file, "Falta el catálogo `{$locale}` del demo `{$demo}`.");

        $catalogo = require $file;

        self::assertIsArray($catalogo, $file . ' tiene que devolver un array plano.');

        return $catalogo;
    }

    /**
     * Claves que aportan los tres scaffolds para un idioma.
     *
     * @return array<string, string>
     */
    private static function catalogoDeDemos(string $locale): array
    {
        $catalogo = [];

        foreach (self::modules() as $demo => $meta) {
            $catalogo = array_merge($catalogo, self::catalogoDelDemo($demo, $locale));
        }

        self::assertNotSame([], $catalogo, "Ningún scaffold trae catálogo `{$locale}`: el test estaría pasando en vacío.");

        return $catalogo;
    }

    /**
     * Catálogo de referencia de un idioma: el base del shell más el de cada demo.
     *
     * Es lo que resolvería el traductor con los tres demos instalados, armado
     * desde los archivos y no desde el repo instalado, para que el test no
     * dependa de qué demos están puestos.
     *
     * @return array<string, string>
     */
    private static function catalogo(string $locale): array
    {
        $base = require self::LANG . '/' . $locale . '.php';

        self::assertIsArray($base, "lang/{$locale}.php tiene que devolver un array plano.");

        return array_merge($base, self::catalogoDeDemos($locale));
    }

    /** ¿El demo está instalado en este repo? */
    private static function instalado(string $demo): bool
    {
        return is_file(__DIR__ . '/../../routes/' . $demo . '.php');
    }

    /** S7: el catálogo en español de los demos conserva el texto que ya se renderizaba. */
    public function test_el_catalogo_es_de_los_demos_conserva_el_texto_de_head(): void
    {
        $es = self::catalogo('es');

        foreach (self::ESPANOL_DEMOS as $clave => $texto) {
            self::assertArrayHasKey($clave, $es, "Falta la clave `{$clave}` en el catálogo español.");
            self::assertSame($texto, $es[$clave], "El texto de `{$clave}` cambió para el usuario.");
        }
    }

    /**
     * S4: los catálogos base no tienen ninguna clave de demo.
     *
     * Es el guard inverso al que protegía la referencia: las claves de demo
     * vivían en `lang/<locale>.php` y hoy las trae el scaffold
     * (`framework/scaffolds/<demo>/lang/<locale>/<demo>.php`), que el instalador
     * copia a `lang/<locale>/<demo>.php` con el resto del demo. Una clave de
     * demo que vuelva al catálogo base queda en el proyecto de quien nunca
     * instaló el demo, que es lo que S1 prohíbe.
     */
    public function test_los_catalogos_base_no_tienen_claves_de_los_demos(): void
    {
        foreach (['es', 'en'] as $locale) {
            $base = require self::LANG . '/' . $locale . '.php';

            self::assertIsArray($base, "lang/{$locale}.php tiene que devolver un array plano.");

            $deDemos = array_values(array_filter(
                array_map('strval', array_keys($base)),
                static fn (string $clave): bool => preg_match('/^(tasks|contacts|notes|nav\.(tasks|contacts|notes))\.?/', $clave) === 1
            ));

            self::assertSame(
                [],
                $deDemos,
                "lang/{$locale}.php tiene claves de demo; viven en framework/scaffolds/<demo>/lang/{$locale}/<demo>.php: "
                . implode(', ', $deDemos)
            );
        }
    }

    /** S6: la referencia no se queda corta cuando un scaffold suma una clave. */
    public function test_la_referencia_cubre_las_claves_de_los_demos(): void
    {
        $deDemos = array_keys(self::catalogoDeDemos('es'));

        self::assertNotSame([], $deDemos, 'Los scaffolds no tienen claves de demo: el test estaría pasando en vacío.');

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
     * el catálogo de SU demo, en los dos idiomas. Si alguien escribe el texto
     * directamente en `config/navigation/<demo>.php`, la portada vuelve a
     * mostrar español en inglés y la clave no aparece en ningún catálogo.
     */
    public function test_los_metadatos_de_los_modulos_son_claves_del_catalogo_de_su_demo(): void
    {
        foreach (self::modules() as $demo => $meta) {
            // El catálogo de un demo sólo existe cuando está instalado, así que
            // el vocabulario del scaffold se lee del propio scaffold.
            $catalogos = [
                'es' => self::catalogoDelDemo($demo, 'es'),
                'en' => self::catalogoDelDemo($demo, 'en'),
            ];

            foreach (['label', 'summary', 'why'] as $campo) {
                self::assertArrayHasKey($campo, $meta, "El módulo `{$demo}` no declara `{$campo}`.");

                $clave = $meta[$campo];

                self::assertIsString($clave, "`{$campo}` de `{$demo}` tiene que ser una clave, no texto.");

                foreach ($catalogos as $locale => $catalogo) {
                    self::assertArrayHasKey(
                        $clave,
                        $catalogo,
                        "La clave `{$clave}` de `{$demo}` no está en el catálogo `{$locale}` de ese demo."
                    );
                }
            }
        }
    }

    /** El catálogo inglés es una traducción, no el español copiado. */
    public function test_el_catalogo_en_traduce_las_superficies_nuevas(): void
    {
        $es = self::catalogo('es');
        $en = self::catalogo('en');

        foreach (self::TRADUCIDAS as $clave) {
            self::assertArrayHasKey($clave, $en, "Falta la clave `{$clave}` en el catálogo inglés.");
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
     * `odd/tasks/i18n.md`. Se miran TODAS las claves del `namespace` y no una
     * muestra: si alguien copia el español al catálogo inglés en una sola, el
     * demo deja de cambiar de idioma en esa pantalla y la clave del tutorial
     * vuelve a mentir.
     */
    public function test_el_demo_notes_cambia_de_idioma_en_todas_sus_claves(): void
    {
        $es = self::catalogo('es');
        $en = self::catalogo('en');

        $claves = array_values(array_filter(
            array_map('strval', array_keys($es)),
            static fn (string $clave): bool => str_starts_with($clave, 'notes.')
        ));

        self::assertNotSame([], $claves, 'Los catálogos no tienen claves `notes.*`: el test estaría pasando en vacío.');

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
     * Se renderiza el menú REAL del contenedor (`navigation_items`) y no uno
     * armado acá: con los tres demos instalados tiene las cuatro entradas; sin
     * demos, sólo Inicio. La clave de un demo existe sólo mientras el demo está
     * instalado (S1-S2), así que su etiqueta se exige únicamente cuando la
     * entrada está en el menú; cada scaffold fija la suya en sus propios tests.
     *
     * Se fija el locale en el traductor del contenedor y se restaura en
     * `finally`: es estado del proceso, y el middleware lo reescribe en cada
     * request.
     */
    public function test_el_sidebar_traduce_las_etiquetas_del_menu(): void
    {
        $container = self::app()->getContainer();
        $translator = $container->get(LocaleAwareInterface::class);
        $twig = self::twig();

        $navigation = $twig->getGlobals()['navigation_items'] ?? null;

        self::assertIsArray($navigation, 'El contenedor no expone `navigation_items`.');
        self::assertNotSame([], $navigation, 'El menú está vacío: Inicio tiene que estar siempre.');

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

        self::assertStringContainsString('>Inicio<', $espanol, 'El sidebar en español no muestra «Inicio».');
        self::assertStringContainsString('>Home<', $ingles, 'El sidebar en inglés no muestra «Home».');
        self::assertStringNotContainsString('>Inicio<', $ingles, 'El sidebar en inglés sigue mostrando la etiqueta española.');
        self::assertStringNotContainsString('>nav.home<', $espanol, 'La clave salió cruda: no se está traduciendo en el render.');

        $enMenu = array_column($navigation, 'label');

        foreach (['nav.tasks' => ['Tareas', 'Tasks'], 'nav.contacts' => ['Contactos', 'Contacts'], 'nav.notes' => ['Notas', 'Notes']] as $clave => [$textoEs, $textoEn]) {
            if (!in_array($clave, $enMenu, true)) {
                continue;
            }

            self::assertStringContainsString('>' . $textoEs . '<', $espanol, "El sidebar en español no muestra «{$textoEs}».");
            self::assertStringContainsString('>' . $textoEn . '<', $ingles, "El sidebar en inglés no muestra «{$textoEn}».");
            self::assertStringNotContainsString('>' . $clave . '<', $espanol, "La etiqueta \`{$clave}\` salió cruda.");
        }
    }

    /**
     * La portada resuelve la ficha de cada demo en el idioma de la petición y
     * en sus DOS estados, sin pedir nunca una clave de demo que no exista.
     *
     * - Con el demo instalado: `label`, `summary` y `why` de su catálogo
     *   (existe en `lang/<locale>/<demo>.php`). El esperado se lee del scaffold
     *   —lo que el instalador copia— para que valga instalado o no.
     * - Sin instalar: `home.demo_available` del catálogo base y el comando de
     *   instalación, sin `summary` ni `why`: esas claves todavía no existen
     *   (S1) y el HTML tiene que mostrar texto, no la clave cruda.
     */
    public function test_la_portada_traduce_las_fichas_de_los_demos_en_sus_dos_estados(): void
    {
        $es = self::texto((string) self::app()->handle(self::request('GET', '/'))->getBody());
        $en = self::texto((string) self::app()->handle(self::request('GET', '/?lang=en'))->getBody());

        self::assertStringContainsString('<span>Inicio</span>', $es);
        self::assertStringContainsString('<span>Home</span>', $en);
        self::assertStringContainsString('Módulos y demos', $es);
        self::assertStringContainsString('Modules and demos', $en);
        self::assertStringNotContainsString('<span>Inicio</span>', $en);
        self::assertStringNotContainsString('home.modules.title', $en, 'La clave salió cruda: no se está traduciendo.');

        $baseEs = require self::LANG . '/es.php';
        $baseEn = require self::LANG . '/en.php';

        foreach (self::modules() as $demo => $meta) {
            self::assertStringNotContainsString(
                'home.demo_available',
                $es,
                'La clave de la ficha «no instalado» salió cruda: no se está traduciendo.'
            );

            if (!self::instalado($demo)) {
                self::assertStringContainsString(
                    str_replace('%name%', $demo, $baseEs['home.demo_available']),
                    $es,
                    "La ficha de `{$demo}` sin instalar no sale del catálogo base."
                );
                self::assertStringContainsString(
                    str_replace('%name%', $demo, $baseEn['home.demo_available']),
                    $en,
                    "La ficha de `{$demo}` sin instalar no sale del catálogo base en inglés."
                );
                self::assertStringContainsString('composer demo:' . $demo . ':install', $es);

                // Sin `summary` ni `why`: sus claves viven en el catálogo del
                // demo y no existen sin instalar. Ni el texto ni la clave
                // pueden aparecer.
                foreach (['summary', 'why'] as $campo) {
                    $clave = $demo . '.' . $campo;

                    self::assertStringNotContainsString($clave, $es, "La clave `{$clave}` salió cruda en la portada.");
                    self::assertStringNotContainsString($clave, $en, "La clave `{$clave}` salió cruda en la portada en inglés.");
                }

                continue;
            }

            $esDemo = self::catalogoDelDemo($demo, 'es');
            $enDemo = self::catalogoDelDemo($demo, 'en');

            // El nombre del módulo también se traduce: la ficha de `notes` dice
            // «Demo de notas» en español y «notes demo» en inglés.
            self::assertStringContainsString(
                'Demo de ' . mb_strtolower($esDemo['nav.' . $demo]),
                $es,
                "La ficha de `{$demo}` no traduce el nombre del módulo en español."
            );
            self::assertStringContainsString(
                mb_strtolower($enDemo['nav.' . $demo]) . ' demo',
                $en,
                "La ficha de `{$demo}` no traduce el nombre del módulo en inglés."
            );

            self::assertStringContainsString($esDemo[$demo . '.summary'], $es, "La ficha de `{$demo}` no traduce el resumen.");
            self::assertStringContainsString($enDemo[$demo . '.summary'], $en, "La ficha de `{$demo}` no traduce el resumen en inglés.");
            self::assertStringContainsString($esDemo[$demo . '.why'], $es, "La ficha de `{$demo}` no traduce el porqué.");
            self::assertStringContainsString($enDemo[$demo . '.why'], $en, "La ficha de `{$demo}` no traduce el porqué en inglés.");
            self::assertStringContainsString('composer demo:' . $demo . ':remove', $es);

            self::assertStringNotContainsString($demo . '.summary', $en, 'La clave salió cruda: no se está traduciendo.');
            self::assertStringNotContainsString($esDemo[$demo . '.summary'], $en, "La ficha de `{$demo}` sigue en español en la visita en inglés.");
            self::assertStringNotContainsString(
                str_replace('%name%', $demo, $baseEs['home.demo_available']),
                $es,
                "La ficha de `{$demo}` está instalada y no puede salir como «no instalado»."
            );
        }
    }

    /**
     * S5: sin demos instalados, las TRES fichas de la portada salen del catálogo
     * base (`home.demo_available` + el comando de instalación), sin una sola
     * clave de demo.
     *
     * Es el estado de un clon limpio. Si el caso corre con algún demo instalado,
     * exige lo mismo para las fichas que faltan —nunca queda vacío— y reserva la
     * cuenta de «las tres» para cuando no hay ninguna.
     */
    public function test_la_portada_muestra_las_fichas_sin_instalar_con_el_catalogo_base(): void
    {
        $es = self::texto((string) self::app()->handle(self::request('GET', '/'))->getBody());
        $baseEs = require self::LANG . '/es.php';
        $sinInstalar = [];

        foreach (self::modules() as $demo => $meta) {
            if (self::instalado($demo)) {
                continue;
            }

            $sinInstalar[] = $demo;

            self::assertStringContainsString(
                str_replace('%name%', $demo, $baseEs['home.demo_available']),
                $es,
                "La ficha de `{$demo}` sin instalar no sale del catálogo base."
            );
            self::assertStringContainsString('composer demo:' . $demo . ':install', $es);
            self::assertStringNotContainsString($meta['label'], $es, "La clave `{$meta['label']}` salió cruda en la portada.");
            self::assertStringNotContainsString($meta['summary'], $es, "La clave `{$meta['summary']}` salió cruda en la portada.");
            self::assertStringNotContainsString($meta['why'], $es, "La clave `{$meta['why']}` salió cruda en la portada.");
        }

        self::assertNotSame([], $sinInstalar, 'Los tres demos están instalados: no hay ficha «no instalado» que revisar.');
        self::assertStringNotContainsString('home.demo_available', $es, 'La clave salió cruda: no se está traduciendo.');

        if (count($sinInstalar) === 3) {
            self::assertSame(
                3,
                substr_count($es, ': no instalado'),
                'Sin demos, la portada tiene que mostrar las tres fichas en estado «no instalado».'
            );
            self::assertStringNotContainsString('Demo de ', $es, 'Sin demos no hay ninguna ficha en estado instalado.');
        }
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
