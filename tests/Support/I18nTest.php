<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\I18n\Trans;
use Framework\I18n\TranslationExtension;
use Framework\I18n\TranslatorFactory;
use Framework\Support\Env;
use Slim\Views\Twig;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Contrato de la capa de traducción (S1-S7).
 *
 * Lo que se fija acá no es "que Symfony funcione" —eso ya lo prueba su propia
 * suite— sino el contrato de esta app: los catálogos viven en `lang/`, el
 * locale sale del entorno con default `es` y fallback `en`, una clave ausente
 * devuelve la clave en vez de romper, y el cromo del shell se traduce por
 * `t()` sin cambiar una sola letra del español que ya veía el usuario (S7).
 *
 * Los textos esperados son literales copiados del shell anterior a la
 * migración: si alguien "mejora" una cadena en el catálogo, este test lo
 * frena. Ese es el punto.
 */
final class I18nTest extends HttpTestCase
{
    private const LANG = __DIR__ . '/../../lang';

    private const SCAFFOLDS = __DIR__ . '/../../framework/scaffolds';

    /**
     * Catálogo en español del cromo migrado, tal como estaba escrito a mano en
     * los templates. Es la referencia de S7: el render tiene que decir esto
     * mismo, carácter por carácter.
     *
     * Tiene que cubrir TODO el namespace `layout.*` del catálogo: si aparece una
     * clave nueva y no entra acá, su español queda sin protección y el test
     * seguiría verde mientras el texto cambia.
     */
    private const ESPANOL = [
        // Marca y datos de ejecución del shell.
        'app.name'               => 'Mi App',
        'footer.environment'     => 'entorno %env%',

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

        // Aviso flotante del shell.
        'toast.close'            => 'Cerrar aviso',

        // Modal nativo y campos de formulario.
        'modal.close'            => 'Cerrar',
        'field.optional'         => '(opcional)',

        // Paginador reutilizable.
        'pagination.showing'     => 'Mostrando %from% al %to% de %total% %label%',
        'pagination.label'       => 'registros',
        'pagination.per_page'    => '%label% por página',
        'pagination.nav'         => 'Paginación',
        'pagination.first'       => 'Primera',
        'pagination.previous'    => 'Anterior',
        'pagination.next'        => 'Siguiente',
        'pagination.last'        => 'Última',
        'pagination.go_first'    => 'Primera página',
        'pagination.go_previous' => 'Página anterior',
        'pagination.go_next'     => 'Página siguiente',
        'pagination.go_last'     => 'Última página',
        'pagination.go_page'     => 'Página %page%',
        'pagination.apply'       => 'Aplicar',
    ];

    /**
     * Español de las páginas propias, los flashes de auth, el validador y el
     * aviso de CSRF, tal como estaba escrito a mano antes de externalizarlo.
     *
     * Es la referencia de S7 para las superficies de esta unidad: si alguien
     * "mejora" una cadena en `lang/es.php`, el render cambia para el usuario y
     * este test lo frena. Los textos largos van en una sola línea —en el
     * catálogo lo están—: el HTML anterior los partía por indentación, y lo que
     * se compara acá es el texto, no los saltos de línea del template.
     *
     * Tiene que cubrir TODO el namespace de cada prefijo (`home.`, `login.`,
     * `error.`, `validation.`, `auth.`): una clave nueva que no entre acá deja
     * su español sin protección y el test sigue verde mientras el texto cambia.
     */
    private const ESPANOL_PAGINAS = [
        // Portada.
        'home.sign_in'                 => 'Ingresar',
        'home.open_demo'               => 'Abrir %name%',
        'home.tagline'                 => 'Framework PHP Fullstack con renderizado en el servidor',
        'home.intro'                   => 'Esqueleto optimizado para desarrollo ágil y asistido por IA en entornos acotados (Windows, Linux o Termux 32 bits offline). Arquitectura limpia con renderizado en el servidor, reactividad ligera sin sobrecarga de SPA, y recursos frontend servidos de forma local sin Node, sin bundlers ni dependencias de CDNs externas.',
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
        'home.tech.php'                => 'Lenguaje base y runtime moderno',
        'home.tech.slim'               => 'Router HTTP, middlewares y pipeline PSR-7/15',
        'home.tech.phpdi'              => 'Contenedor de inyección de dependencias PSR-11',
        'home.tech.csrf'               => 'Protección contra falsificación de peticiones',
        'home.tech.dotenv'             => 'Configuración centralizada por variables de entorno',
        'home.tech.respect'            => 'Reglas declarativas para validación de entradas',
        'home.tech.monolog'            => 'Registro estructurado de eventos del sistema',
        'home.tech.twig'               => 'Plantillas server-rendered vía slim/twig-view',
        'home.tech.tailwind'           => 'Estilos utilitarios ejecutados localmente sin Node',
        'home.tech.daisyui'            => 'Componentes de interfaz vendorizados y temables',
        'home.tech.htmx'               => 'Navegación reactiva y swaps HTML sin SPA',
        'home.tech.alpine'             => 'Control de estado interactivo en el cliente',
        'home.tech.eloquent'           => 'Mapeo objeto-relacional (illuminate/database)',
        'home.tech.sqlite'             => 'Base de datos embebida de cero mantenimiento',
        'home.tech.phinx'              => 'Migraciones idempotentes y versionado de esquema',
        'home.tech.phpunit'            => 'Pruebas unitarias, funcionales y de seguridad',
        'home.tech.phpstan'            => 'Análisis estático de tipos nivel 5 (PHAR)',
        'home.tech.whoops'             => 'Gestor de errores detallado para desarrollo',
        'home.tech.composer'           => 'Gestor de paquetes y scripts de verificación',
        'home.modules.title'           => 'Módulos y demos',
        'home.modules.subtitle'        => 'Scaffolds opcionales para explorar el stack o usar como base.',
        'home.demo.title'              => 'Demo de %name%',
        'home.demo_available'          => 'Demo %name%: no instalado',
        'home.demo.installed'          => 'Instalado',
        'home.demo.optional'           => 'Opcional',
        'home.demo.uninstall'          => 'Desinstalar módulo:',
        'home.demo.install'            => 'Comando de instalación:',
        'home.requirements.title'      => 'Requisitos y compatibilidad',
        'home.requirements.envs'       => 'Entornos',
        'home.requirements.envs_value' => 'Termux (ARM 32/64b), Windows, Linux, Mac',
        'home.requirements.php'        => 'PHP',
        'home.requirements.extensions' => 'Extensiones',
        'home.requirements.composer'   => 'Composer',
        'home.requirements.browsers'   => 'Navegadores',
        'home.requirements.note'       => 'Sin Node.js, sin paso de build ni CDN en runtime.',

        // Login.
        'login.title'      => 'Acceder',
        'login.subtitle'   => 'Ingresa con tu email y clave para continuar.',
        'login.email'      => 'Email',
        'login.email_hint' => 'tu@ejemplo.com',
        'login.password'   => 'Clave',
        'login.remember'   => 'Recordarme 30 días',
        'login.submit'     => 'Entrar',

        // Páginas de error (autónomas y el partial de HTMX).
        'error.title'              => '%code% · %app%',
        'error.badge'              => 'Error %code%',
        'error.back_home'          => 'Volver al inicio',
        'error.not_found'          => 'No encontramos esa página.',
        'error.forbidden'          => 'No tienes permiso para ver esto.',
        'error.method_not_allowed' => 'Ese método no está permitido.',
        'error.bad_request'        => 'La petición no es válida.',
        'error.server'             => 'Algo salió mal de nuestro lado.',

        // Validador: mensajes por campo.
        'validation.required' => 'Este campo es obligatorio.',
        'validation.string'   => 'Este campo debe ser texto.',
        'validation.email'    => 'Este campo debe ser un email válido.',
        'validation.numeric'  => 'Este campo debe ser un número.',
        'validation.max'      => 'No puede superar los %max% caracteres.',
        'validation.min'      => 'Debe tener al menos %min% caracteres.',

        // Flashes de auth, aviso del Guard cuando falla el CSRF y aviso de la
        // puerta de login (`RequireAuthMiddleware`).
        'auth.invalid_credentials' => 'Credenciales inválidas.',
        'auth.logged_out'          => 'Sesión cerrada.',
        'auth.login_required'      => 'Inicia sesión para continuar.',
        'auth.csrf_reload'         => 'Tu sesión expiró. Recarga la página.',
        'auth.csrf_login'          => 'Tu sesión expiró. Vuelve a iniciar sesión.',
    ];

    private static function twig(): Environment
    {
        return self::app()->getContainer()->get(Twig::class)->getEnvironment();
    }

    /**
     * Metadata con la forma que devuelve `Pagination::paginate()`.
     *
     * @return array<string, mixed>
     */
    private static function pagination(): array
    {
        return [
            'total'            => 42,
            'per_page'         => 10,
            'current_page'     => 1,
            'last_page'        => 5,
            'from'             => 1,
            'to'               => 10,
            'base_url'         => '/tareas',
            'pages'            => [1, 2, 3, 4, 5],
            'allowed_per_page' => [5, 10, 25, 50, 100],
        ];
    }

    /**
     * Corre $callback con el entorno de locale dado y lo deja como estaba.
     *
     * Las tres fuentes que mira `Env::get()` se tocan a la vez: si la variable
     * queda sólo en `getenv()` —un uso válido, p. ej. `APP_LOCALE=en php
     * vendor/bin/phpunit`— el test tiene que seguir siendo determinista. El
     * contenedor se rearma después porque el locale se fija al construirlo.
     *
     * @param array<string, string|null> $values null deja la variable ausente
     */
    private static function withLocale(array $values, callable $callback): void
    {
        $saved = [];

        foreach (array_keys($values) as $key) {
            $saved[$key] = [
                array_key_exists($key, $_ENV) ? $_ENV[$key] : null,
                array_key_exists($key, $_ENV),
                array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null,
                array_key_exists($key, $_SERVER),
                getenv($key),
            ];

            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            if ($values[$key] !== null) {
                $_ENV[$key] = $values[$key];
            }
        }

        AppFactory::reset();

        try {
            $callback();
        } finally {
            foreach ($saved as $key => [$envValue, $envExisted, $serverValue, $serverExisted, $processValue]) {
                if ($envExisted) {
                    $_ENV[$key] = $envValue;
                } else {
                    unset($_ENV[$key]);
                }

                if ($serverExisted) {
                    $_SERVER[$key] = $serverValue;
                } else {
                    unset($_SERVER[$key]);
                }

                if ($processValue === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $processValue);
                }
            }

            AppFactory::reset();
        }
    }

    /**
     * S4: los dos catálogos base existen, son arrays planos y declaran las
     * mismas claves.
     *
     * La paridad es POR ARCHIVO: el catálogo base dice lo que dice el otro, y
     * cada catálogo de demo (`lang/<locale>/<demo>.php` instalado) tiene que
     * tener su pareja en el otro idioma con el mismo juego de claves. El
     * scaffold se revisa también porque el archivo instalado existe sólo
     * mientras el demo está puesto: sin esto la paridad de los demos se
     * verificaría únicamente en la matriz con demos instalados.
     */
    public function test_los_catalogos_es_y_en_declaran_las_mismas_claves(): void
    {
        $es = require self::LANG . '/es.php';
        $en = require self::LANG . '/en.php';

        self::assertIsArray($es, 'lang/es.php tiene que devolver un array plano.');
        self::assertIsArray($en, 'lang/en.php tiene que devolver un array plano.');
        self::assertNotSame([], $es, 'lang/es.php está vacío: nada del cromo quedaría traducido.');

        foreach ([$es, $en] as $catalogo) {
            foreach ($catalogo as $clave => $texto) {
                self::assertIsString($clave);
                self::assertIsString($texto);
                self::assertNotSame('', trim($texto), "La clave `{$clave}` está vacía.");
            }
        }

        $clavesEs = array_keys($es);
        $clavesEn = array_keys($en);
        sort($clavesEs);
        sort($clavesEn);

        self::assertSame(
            $clavesEs,
            $clavesEn,
            'Los catálogos se desincronizaron: una clave que existe en un idioma y no en el otro se ve como texto en el idioma equivocado.'
        );

        // Paridad por archivo de los catálogos de demo ya instalados.
        self::assertParidadPorArchivo(
            self::catalogosDeDirectorio(self::LANG . '/es'),
            self::catalogosDeDirectorio(self::LANG . '/en'),
            'lang/<locale>/'
        );

        // Y la del scaffold, que es la fuente de esos archivos y existe siempre.
        $scaffold = dirname(self::LANG) . '/framework/scaffolds';

        $demosEs = [];
        foreach (glob($scaffold . '/*/lang/es/*.php') ?: [] as $archivo) {
            $demosEs[basename(dirname($archivo, 3)) . '/' . basename($archivo)] = require $archivo;
        }

        $demosEn = [];
        foreach (glob($scaffold . '/*/lang/en/*.php') ?: [] as $archivo) {
            $demosEn[basename(dirname($archivo, 3)) . '/' . basename($archivo)] = require $archivo;
        }

        self::assertNotSame([], $demosEs, 'Ningún scaffold trae catálogo `es`: la paridad por archivo estaría pasando en vacío.');

        self::assertParidadPorArchivo($demosEs, $demosEn, 'framework/scaffolds/<demo>/lang/');
    }

    /**
     * Catálogos `lang/<locale>/*.php` de un directorio, indexados por archivo.
     *
     * Un directorio inexistente (no hay demos instalados) devuelve `[]`: no es
     * un error, es el estado normal de un clon limpio (S1).
     *
     * @return array<string, array<mixed>>
     */
    private static function catalogosDeDirectorio(string $directorio): array
    {
        if ($directorio === '' || !is_dir($directorio)) {
            return [];
        }

        $catalogos = [];

        foreach (glob($directorio . '/*.php') ?: [] as $archivo) {
            $catalogo = require $archivo;

            self::assertIsArray($catalogo, $archivo . ' tiene que devolver un array plano.');
            $catalogos[basename($archivo)] = $catalogo;
        }

        return $catalogos;
    }

    /**
     * Los dos juegos de archivos tienen los mismos nombres y cada pareja declara
     * las mismas claves.
     *
     * @param array<string, array<mixed>> $es
     * @param array<string, array<mixed>> $en
     */
    private static function assertParidadPorArchivo(array $es, array $en, string $origen): void
    {
        self::assertSame(
            array_keys($es),
            array_keys($en),
            "{$origen}: los archivos de catálogo no coinciden entre `es` y `en`."
        );

        foreach ($es as $archivo => $catalogoEs) {
            $clavesEs = array_keys($catalogoEs);
            $clavesEn = array_keys($en[$archivo]);
            sort($clavesEs);
            sort($clavesEn);

            self::assertSame(
                $clavesEs,
                $clavesEn,
                "{$origen}{$archivo} no declara las mismas claves en `es` y `en`."
            );
        }
    }

    /** S7: el catálogo en español conserva el texto que el usuario ya veía. */
    public function test_el_catalogo_es_conserva_los_textos_del_shell(): void
    {
        $es = require self::LANG . '/es.php';

        foreach (self::ESPANOL as $clave => $texto) {
            self::assertArrayHasKey($clave, $es, "Falta la clave `{$clave}` en lang/es.php.");
            self::assertSame($texto, $es[$clave], "El texto de `{$clave}` cambió para el usuario.");
        }
    }

    /**
     * S7: la referencia no se queda corta cuando el cromo suma una clave.
     *
     * Agregar `layout.*` y olvidarla en ESPANOL dejaría un texto del shell sin
     * proteger: el render seguiría verde y nadie vería que la cadena cambió.
     */
    public function test_la_referencia_del_espanol_cubre_todas_las_claves_del_cromo(): void
    {
        $es = require self::LANG . '/es.php';

        $deLayout = array_values(array_filter(
            array_keys($es),
            static fn (string $clave): bool => str_starts_with($clave, 'layout.')
        ));

        self::assertNotSame([], $deLayout, 'lang/es.php no tiene claves `layout.*`: el test estaría pasando en vacío.');

        $faltan = array_values(array_diff($deLayout, array_keys(self::ESPANOL)));

        self::assertSame(
            [],
            $faltan,
            'Faltan en la referencia de S7, así que su español no lo protege nadie: ' . implode(', ', $faltan)
        );
    }

    /** S2: sin APP_LOCALE en el entorno, el default `es` vive en código (S6: .env no viaja). */
    public function test_sin_app_locale_el_default_es_es(): void
    {
        self::withLocale(['APP_LOCALE' => null], static function (): void {
            self::assertSame('es', TranslatorFactory::create()->getLocale());

            $traductor = self::app()->getContainer()->get(TranslatorInterface::class);

            self::assertInstanceOf(TranslatorInterface::class, $traductor);
            self::assertSame('es', $traductor->getLocale());
            self::assertSame('Primera', $traductor->trans('pagination.first'));
        });
    }

    /** S2: APP_LOCALE y APP_FALLBACK_LOCALE se leen del entorno. */
    public function test_el_locale_y_el_fallback_salen_del_entorno(): void
    {
        self::withLocale(['APP_LOCALE' => 'en', 'APP_FALLBACK_LOCALE' => 'es'], static function (): void {
            $traductor = TranslatorFactory::create();

            self::assertSame('en', $traductor->getLocale());
            self::assertSame('First', $traductor->trans('pagination.first'));

            // `fr` no tiene catálogo: lo que se lea sale de APP_FALLBACK_LOCALE.
            $conFallback = TranslatorFactory::create('fr');

            self::assertSame('fr', $conFallback->getLocale());
            self::assertSame('Primera', $conFallback->trans('pagination.first'));
        });
    }

    /**
     * S2: un locale que no es un código de idioma no escapa de `lang/`.
     *
     * El locale termina dentro de `lang/<locale>.php`, así que `es/../en` arma
     * otra ruta, `en.php` otra extensión y el vacío ninguna. En los tres casos
     * manda el default `es` y el catálogo real: no se lee nada de afuera.
     */
    public function test_un_locale_invalido_no_escapa_de_lang_y_cae_en_es(): void
    {
        // El fallback se fija en código para que el resultado no dependa del
        // entorno del que corre la suite.
        self::withLocale(['APP_FALLBACK_LOCALE' => null], static function (): void {
            foreach (['es/../en', '../en', '/etc/passwd', 'en.php', '..', '', '   ', 'es en'] as $invalido) {
                $traductor = TranslatorFactory::create($invalido);

                self::assertSame(
                    'es',
                    $traductor->getLocale(),
                    "El locale «{$invalido}» tenía que caer en el default `es`."
                );
                self::assertSame(
                    'Primera',
                    $traductor->trans('pagination.first'),
                    "El locale «{$invalido}» resolvió un catálogo fuera de `lang/`."
                );
            }
        });
    }

    /** S2: un locale sin catálogo usa el fallback `en` que vive en código. */
    public function test_un_locale_sin_catalogo_usa_el_fallback_en(): void
    {
        self::withLocale(['APP_FALLBACK_LOCALE' => null], static function (): void {
            $traductor = TranslatorFactory::create('fr');

            self::assertSame('fr', $traductor->getLocale());
            self::assertSame('First', $traductor->trans('pagination.first'));
            self::assertSame('Showing %from% to %to% of %total% %label%', $traductor->trans('pagination.showing'));
        });
    }

    /** S2: una clave que no está en el locale activo se resuelve en el fallback. */
    public function test_una_clave_ausente_del_locale_activo_cae_en_el_fallback(): void
    {
        // `fr` no tiene catálogo: es el caso de un idioma a medio traducir, sin
        // inventar catálogos de prueba ni tocar los reales.
        $traductor = TranslatorFactory::create('fr', 'en');

        self::assertSame('fr', $traductor->getLocale());
        self::assertSame('First', $traductor->trans('pagination.first'));
        self::assertSame('Showing %from% to %to% of %total% %label%', $traductor->trans('pagination.showing'));
    }

    /**
     * S3: el loader suma `lang/<locale>/*.php` y no deja residuos.
     *
     * La fixture se llama `_loader_test_tmp.php` y no como un demo real: el caso
     * no depende de qué demos están instalados y no pisa ni restaura el catálogo
     * de ninguno. Declara las claves del demo `notes` (para probar el texto
     * real) más un marcador propio (`notes.loader_probe`), que no existe en
     * ningún catálogo del repo: cuando la fixture se va, esa clave tiene que
     * volver cruda aunque el demo `notes` esté instalado.
     *
     * El test verifica que la fixture no existía antes y que no queda después;
     * el `finally` borra archivo y —si lo creó— el directorio del locale. Un
     * `lang/<locale>/` que ya existía (un demo instalado) no se toca: sus
     * archivos no son de este caso.
     *
     * Además, `lang/es/` es un directorio y no crea un locale: un idioma existe
     * sólo si tiene catálogo base, así que el selector del pie no cambia.
     */
    public function test_el_loader_suma_los_catalogos_por_locale_y_no_deja_residuos(): void
    {
        $fixture = '_loader_test_tmp.php';
        $rutas = [
            'es' => self::LANG . '/es/' . $fixture,
            'en' => self::LANG . '/en/' . $fixture,
        ];

        foreach ($rutas as $ruta) {
            self::assertFileDoesNotExist($ruta, "La fixture tiene que empezar ausente: '{$ruta}'.");
        }

        try {
            foreach ($rutas as $locale => $ruta) {
                $dir = dirname($ruta);
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    self::fail("No se pudo crear '{$dir}'.");
                }

                $catalogo = require self::SCAFFOLDS . '/notes/lang/' . $locale . '/notes.php';
                $catalogo['notes.loader_probe'] = 'Cargado';

                file_put_contents(
                    $ruta,
                    "<?php\n\ndeclare(strict_types=1);\n\n// Fixture temporal de I18nTest: el test la borra en el finally.\nreturn "
                    . var_export($catalogo, true) . ";\n"
                );
            }

            self::assertSame(
                'Notas',
                TranslatorFactory::create('es')->trans('notes.title'),
                'El catálogo base no alcanza: falta cargar `lang/<locale>/*.php`.'
            );
            self::assertSame('Notes', TranslatorFactory::create('en')->trans('notes.title'));
            self::assertSame('Cargado', TranslatorFactory::create('es')->trans('notes.loader_probe'));
            self::assertSame('Cargado', TranslatorFactory::create('en')->trans('notes.loader_probe'));
            self::assertSame(
                ['en', 'es'],
                TranslatorFactory::availableLocales(),
                'Un catálogo de demo creó un locale: `lang/<locale>/` es un directorio, no un idioma.'
            );

            foreach ($rutas as $ruta) {
                self::assertTrue(unlink($ruta), "No se pudo quitar '{$ruta}'.");
            }

            self::assertSame(
                'notes.loader_probe',
                TranslatorFactory::create('es')->trans('notes.loader_probe'),
                'Quitar la fixture dejó su marcador cargado.'
            );
            self::assertSame(
                'Módulos y demos',
                TranslatorFactory::create('es')->trans('home.modules.title'),
                'El catálogo base tiene que seguir resolviendo sin demos.'
            );
        } finally {
            foreach ($rutas as $ruta) {
                if (is_file($ruta)) {
                    unlink($ruta);
                }

                $dir = dirname($ruta);

                if (is_dir($dir) && (scandir($dir) ?: []) === ['.', '..']) {
                    rmdir($dir);
                }
            }

            foreach ($rutas as $ruta) {
                self::assertFileDoesNotExist($ruta, 'El test dejó la fixture en `lang/`: ' . $ruta);
            }
        }
    }

    /**
     * S4: una clave de demo resuelve sólo si el demo está instalado.
     *
     * Mira el estado real del repo en vez de suponer que no hay demos: sin el
     * demo la clave se devuelve cruda, y con el demo resuelve el texto de su
     * catálogo. Así el caso cubre el clon limpio y la matriz con demos, sin
     * pasar en vacío en ninguno de los dos.
     */
    public function test_las_claves_de_demo_resuelven_solo_si_el_demo_esta_instalado(): void
    {
        foreach (['tasks', 'contacts', 'notes'] as $demo) {
            foreach (['es', 'en'] as $locale) {
                $clave = $demo . '.title';
                $ruta = self::LANG . '/' . $locale . '/' . $demo . '.php';
                $traductor = TranslatorFactory::create($locale);

                if (!is_file($ruta)) {
                    self::assertSame(
                        $clave,
                        $traductor->trans($clave),
                        "Sin el demo `{$demo}` instalado, `{$clave}` no puede resolver en `{$locale}`."
                    );
                    continue;
                }

                $catalogo = require $ruta;

                self::assertArrayHasKey($clave, $catalogo, "El catálogo instalado de `{$demo}` no declara `{$clave}`.");
                self::assertSame(
                    $catalogo[$clave],
                    $traductor->trans($clave),
                    "El demo instalado `{$demo}` no resuelve `{$clave}` en `{$locale}`."
                );
            }
        }
    }

    /** S5: una clave inexistente no rompe: devuelve la clave. */
    public function test_una_clave_inexistente_devuelve_la_clave(): void
    {
        self::assertSame(
            'no.existe.esta.clave',
            TranslatorFactory::create('es', 'en')->trans('no.existe.esta.clave')
        );

        self::assertSame(
            'no.existe.esta.clave',
            self::twig()->createTemplate("{{ 'no.existe.esta.clave'|trans }}")->render([]),
            'En Twig la clave ausente tiene que salir tal cual, no vacía ni como error.'
        );
    }

    /** S3: `t()` y `|trans` están en el Twig del contenedor, así que llegan a toda vista. */
    public function test_twig_expone_la_funcion_t_y_el_filtro_trans(): void
    {
        $html = self::twig()
            ->createTemplate("{{ t('pagination.first') }}|{{ 'pagination.next'|trans }}|{{ t('pagination.go_page', {'%page%': 3}) }}")
            ->render([]);

        self::assertSame('Primera|Siguiente|Página 3', $html);
    }

    /**
     * Trans: desde PHP se traduce el locale activo del contenedor, con parámetros.
     *
     * El registro lo hace el contenedor, cuando construye `TranslatorInterface`:
     * el accesor tiene que trabajar con ESE traductor y no con una copia armada
     * por su cuenta, o el locale de la app dejaría de aplicar en los controladores.
     */
    public function test_trans_traduce_con_el_locale_activo_y_con_parametros(): void
    {
        self::withLocale(['APP_LOCALE' => 'en', 'APP_FALLBACK_LOCALE' => 'es'], static function (): void {
            $traductor = self::app()->getContainer()->get(TranslatorInterface::class);

            self::assertSame(
                'Showing 1 to 10 of 42 records',
                Trans::t('pagination.showing', [
                    '%from%'  => 1,
                    '%to%'    => 10,
                    '%total%' => 42,
                    '%label%' => Trans::t('pagination.label'),
                ]),
                'Trans::t() no tradujo con el locale del contenedor.'
            );

            // Mismo objeto: lo que traduce el contenedor y lo que traduce el
            // accesor no pueden venir de dos traductores distintos.
            self::assertSame($traductor->trans('pagination.next'), Trans::t('pagination.next'));
        });
    }

    /**
     * Trans: sin registro previo no explota, y con registro manda ese traductor.
     *
     * El «antes» corre en un proceso nuevo a propósito: para cuando esta suite
     * llega acá, el contenedor ya se armó en algún test anterior, así que el
     * único estado limpio posible es un PHP que nunca arrancó la app. El caso es
     * real: un script o un comando de consola que traduce sin pedir el
     * contenedor.
     */
    public function test_trans_funciona_antes_y_despues_del_registro_del_contenedor(): void
    {
        $env = getenv();
        unset($env['APP_LOCALE'], $env['APP_FALLBACK_LOCALE']);

        $process = proc_open(
            [PHP_BINARY, '-r', 'require "vendor/autoload.php"; echo Framework\\I18n\\Trans::t("pagination.first");'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            __DIR__ . '/../..',
            $env
        );

        if (!is_resource($process)) {
            self::fail('No se pudo ejecutar PHP para probar Trans sin contenedor.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), 'Trans::t() explotó sin el contenedor registrado: ' . $stderr);
        self::assertSame('', $stderr);
        self::assertSame('Primera', $stdout, 'El fallback de Trans::t() no armó el traductor por defecto.');

        // Y después del registro: el traductor del contenedor es el que manda.
        self::withLocale(['APP_LOCALE' => 'en', 'APP_FALLBACK_LOCALE' => 'es'], static function (): void {
            self::app()->getContainer()->get(TranslatorInterface::class);

            self::assertSame('First', Trans::t('pagination.first'));
        });
    }

    /** Trans: una clave inexistente devuelve la clave, igual que en Twig (S5). */
    public function test_trans_devuelve_la_clave_inexistente(): void
    {
        self::assertSame('no.existe.esta.clave', Trans::t('no.existe.esta.clave'));
    }

    /** Trans: cambiar `APP_LOCALE` cambia lo que devuelve `Trans::t()`. */
    public function test_cambiar_app_locale_cambia_lo_que_devuelve_trans(): void
    {
        $en = '';
        $es = '';

        self::withLocale(['APP_LOCALE' => 'en', 'APP_FALLBACK_LOCALE' => 'es'], static function () use (&$en): void {
            self::app()->getContainer()->get(TranslatorInterface::class);
            $en = Trans::t('pagination.first');
        });

        self::withLocale(['APP_LOCALE' => 'es'], static function () use (&$es): void {
            self::app()->getContainer()->get(TranslatorInterface::class);
            $es = Trans::t('pagination.first');
        });

        self::assertSame('First', $en);
        self::assertSame('Primera', $es);
    }

    /** S7: el cromo migrado rinde exactamente el mismo español que antes. */
    public function test_el_cromo_renderiza_el_mismo_espanol(): void
    {
        $twig = self::twig();

        $paginador = $twig->render('components/pagination/simple.twig', [
            'pagination' => self::pagination(),
            'target'     => '#tareas-panel',
        ]);

        self::assertStringContainsString('Mostrando 1 al 10 de 42 registros', $paginador);
        self::assertStringContainsString('Registros por página', $paginador);
        self::assertStringContainsString('>Aplicar<', $paginador);
        self::assertStringContainsString('aria-label="Paginación"', $paginador);
        self::assertStringContainsString('aria-label="Primera página"', $paginador);
        self::assertStringContainsString('aria-label="Página anterior"', $paginador);
        self::assertStringContainsString('aria-label="Página 2"', $paginador);
        self::assertStringContainsString('aria-label="Página siguiente"', $paginador);
        self::assertStringContainsString('aria-label="Última página"', $paginador);
        self::assertStringContainsString('>Última<', $paginador);

        // El `label` del demo sigue mandando sobre el default del catálogo.
        $conLabel = $twig->render('components/pagination/simple.twig', [
            'pagination' => self::pagination(),
            'target'     => '#tareas-panel',
            'label'      => 'tareas',
        ]);

        self::assertStringContainsString('Mostrando 1 al 10 de 42 tareas', $conLabel);
        self::assertStringContainsString('Tareas por página', $conLabel);

        $modal = $twig->createTemplate(
            "{% embed 'components/ui/modal.twig' with {id: 'demo-modal', title: 'Demo'} %}{% endembed %}"
        )->render([]);

        self::assertStringContainsString('aria-label="Cerrar"', $modal);
        self::assertStringContainsString('<button>Cerrar</button>', $modal);

        $campo = $twig->render('components/form/field.twig', [
            'name'     => 'body',
            'label'    => 'Cuerpo',
            'optional' => true,
        ]);

        self::assertStringContainsString('<span class="opacity-60">(opcional)</span>', $campo);

        $pie = $twig->render('layouts/parts/footer.twig');

        self::assertStringContainsString('· entorno ' . Env::get('APP_ENV', 'local'), $pie);
    }

    /** S2 + S7: cambiar APP_LOCALE cambia el texto renderizado, sin tocar .env. */
    public function test_cambiar_app_locale_cambia_el_texto_renderizado(): void
    {
        $en = '';

        self::withLocale(['APP_LOCALE' => 'en'], static function () use (&$en): void {
            $en = self::twig()->render('components/pagination/simple.twig', [
                'pagination' => self::pagination(),
                'target'     => '#tareas-panel',
            ]);
        });

        $es = self::twig()->render('components/pagination/simple.twig', [
            'pagination' => self::pagination(),
            'target'     => '#tareas-panel',
        ]);

        self::assertStringContainsString('Showing 1 to 10 of 42 records', $en);
        self::assertStringContainsString('Records per page', $en);
        self::assertStringNotContainsString('Mostrando', $en);

        self::assertStringContainsString('Mostrando 1 al 10 de 42 registros', $es);
        self::assertStringNotContainsString('Showing', $es);
    }

    /**
     * S7 + escape: un apóstrofo del catálogo no rompe la directiva de Alpine.
     *
     * El nombre accesible de los dos botones del header depende del estado
     * (`sidebarOpen`, `sidebarMini`), así que se escribe en un ternario JS
     * entre comillas simples y ahí se interpola la traducción. Sin `|e('js')`,
     * «Ouvrir l'app» cierra la cadena, Alpine no evalúa el ternario y el botón
     * se queda sin nombre accesible: el control existe y no se anuncia.
     *
     * El catálogo va en memoria (ArrayLoader) y no en un archivo temporal: el
     * caso es el texto, no de dónde sale.
     */
    public function test_un_apostrofo_del_catalogo_no_rompe_los_nombres_de_alpine(): void
    {
        $traductor = new Translator('fr');
        $traductor->addLoader('array', new ArrayLoader());
        $traductor->addResource('array', [
            'layout.breadcrumb'    => 'Fil',
            'layout.close_menu'    => "Fermer l'app",
            'layout.collapse_menu' => "Réduire l'app",
            'layout.expand_menu'   => "Déplier l'app",
            'layout.open_menu'     => "Ouvrir l'app",
            'layout.theme_toggle'  => 'Thème',
        ], 'fr');

        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../resources/views'));
        $twig->addExtension(new TranslationExtension($traductor));

        $html = $twig->render('layouts/parts/topbar.twig');

        // El `aria-label` estático es HTML puro: ahí alcanza el escape normal de
        // Twig y el texto se ve tal cual. Escaparlo para JS mostraría `\uXXXX`.
        self::assertStringContainsString('aria-label="Ouvrir l&#039;app"', $html);

        $atributos = [];
        preg_match_all('/:aria-label="([^"]*)"/', $html, $atributos);
        self::assertArrayHasKey(1, $atributos, 'El header no tiene los dos nombres accesibles dependientes del estado.');
        self::assertCount(2, $atributos[1], 'El header dejó de tener dos nombres accesibles dependientes del estado.');

        $nombres = [];

        foreach ($atributos[1] as $valor) {
            $expresion = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $literales = self::literalesDelTernario($expresion);

            self::assertCount(
                2,
                $literales,
                'La traducción cerró la cadena de JS: Alpine no puede evaluar `' . $expresion . '`.'
            );

            $nombres = array_merge($nombres, $literales);
        }

        sort($nombres);

        self::assertSame(
            ["Déplier l'app", "Fermer l'app", "Ouvrir l'app", "Réduire l'app"],
            $nombres,
            'Los nombres accesibles que evalúa Alpine no son los del catálogo.'
        );
    }

    /**
     * S7: el catálogo en español de las páginas, los flashes y el validador.
     *
     * El español de estas superficies no cambia ni un carácter: el valor del
     * catálogo es el texto que el usuario ya veía, no una versión mejorada.
     */
    public function test_el_catalogo_de_las_paginas_propias_conserva_el_espanol(): void
    {
        $es = require self::LANG . '/es.php';

        foreach (self::ESPANOL_PAGINAS as $clave => $texto) {
            self::assertArrayHasKey($clave, $es, "Falta la clave `{$clave}` en lang/es.php.");
            self::assertSame($texto, $es[$clave], "El texto de `{$clave}` cambió para el usuario.");
        }
    }

    /**
     * S7: la referencia no se queda corta cuando una superficie suma una clave.
     *
     * Mismo guard que `test_la_referencia_del_espanol_cubre_todas_las_claves_del_cromo`,
     * un prefijo por superficie: `home.`, `login.`, `error.`, `validation.` y
     * `auth.`. Una clave nueva que no entre en la referencia dejaría su texto
     * sin proteger.
     */
    public function test_la_referencia_cubre_todas_las_claves_de_las_paginas_propias(): void
    {
        $es = require self::LANG . '/es.php';

        $prefijos = ['home.', 'login.', 'error.', 'validation.', 'auth.'];
        $dePaginas = array_values(array_filter(
            array_keys($es),
            static function (string $clave) use ($prefijos): bool {
                foreach ($prefijos as $prefijo) {
                    if (str_starts_with($clave, $prefijo)) {
                        return true;
                    }
                }

                return false;
            }
        ));

        self::assertNotSame([], $dePaginas, 'lang/es.php no tiene claves de las páginas propias.');

        $faltan = array_values(array_diff($dePaginas, array_keys(self::ESPANOL_PAGINAS)));

        self::assertSame(
            [],
            $faltan,
            'Faltan en la referencia de S7, así que su español no lo protege nadie: ' . implode(', ', $faltan)
        );
    }

    /**
     * S7: las cuatro vistas renderizan ese mismo español, sin literales.
     *
     * Se pide en español por HTTP y no por `self::twig()->render()` a propósito:
     * el locale lo fija `LocaleMiddleware` en cada petición, así que el
     * resultado no depende del idioma que dejó el test anterior.
     */
    public function test_las_paginas_propias_renderizan_el_mismo_espanol(): void
    {
        $portada = self::texto((string) self::app()->handle(self::request('GET', '/'))->getBody());

        self::assertStringContainsString('Framework PHP Fullstack con renderizado en el servidor', $portada);
        self::assertStringContainsString('Características operativas', $portada);
        self::assertStringContainsString('Núcleo & Backend', $portada);
        self::assertStringContainsString('Calidad & Herramientas', $portada);
        self::assertStringContainsString('<span>Ingresar</span>', $portada);
        // La ficha de cada módulo muestra el comando de instalación o el de
        // desinstalación según esté instalado: la aserción no puede fijar uno
        // solo, porque la suite corre en los dos estados (con y sin demos).
        // La ficha de cada módulo tiene dos estados y ninguno resuelve claves de
        // demo sin instalar (S1-S5): con el demo puesto, sus metadatos; sin
        // instalar, `home.demo_available` del catálogo base. En la suite base no
        // hay demos, así que las tres fichas tienen que salir del catálogo base.
        $sinInstalar = array_values(array_filter(
            ['tasks', 'contacts', 'notes'],
            static fn (string $demo): bool => !is_file(__DIR__ . '/../../routes/' . $demo . '.php')
        ));

        self::assertNotSame(
            [],
            $sinInstalar,
            'Los tres demos están instalados: el estado «no instalado» de la portada no se ve en este árbol.'
        );

        foreach ($sinInstalar as $demo) {
            self::assertStringContainsString(
                'Demo ' . $demo . ': no instalado',
                $portada,
                "La ficha de `{$demo}` sin instalar no sale del catálogo base."
            );
        }

        self::assertStringNotContainsString('home.demo_available', $portada, 'La clave salió cruda: no se está traduciendo.');
        self::assertStringNotContainsString('tasks.summary', $portada, 'La clave de un demo salió cruda: no existe sin instalar.');

        self::assertTrue(
            str_contains($portada, 'Comando de instalación:') || str_contains($portada, 'Desinstalar módulo:'),
            'La portada no muestra ni el comando de instalación ni el de desinstalación de los módulos.'
        );
        self::assertStringContainsString('Sin Node.js, sin paso de build ni CDN en runtime.', $portada);

        $login = self::texto((string) self::app()->handle(self::request('GET', '/login'))->getBody());

        self::assertStringContainsString('<title>Acceder</title>', $login);
        self::assertStringContainsString('Ingresa con tu email y clave para continuar.', $login);
        self::assertStringContainsString('Recordarme 30 días', $login);
        self::assertStringContainsString('<span>Entrar</span>', $login);
        self::assertStringContainsString('placeholder="tu@ejemplo.com"', $login);

        $noEncontrada = self::texto((string) self::app()->handle(self::request('GET', '/no-existe'))->getBody());

        self::assertStringContainsString('<html lang="es"', $noEncontrada);
        self::assertStringContainsString('Error 404', $noEncontrada);
        self::assertStringContainsString('No encontramos esa página.', $noEncontrada);
        self::assertStringContainsString('aria-label="Mi App, ir al inicio"', $noEncontrada);
        self::assertStringContainsString('<span>Mi App</span>', $noEncontrada);
        self::assertStringContainsString('<span>Volver al inicio</span>', $noEncontrada);

        $caida = self::texto((string) self::app()->handle(self::request('GET', '/_test/boom'))->getBody());

        self::assertStringContainsString('Algo salió mal de nuestro lado.', $caida);
    }

    /**
     * El cambio de idioma llega a TODAS las superficies de esta unidad.
     *
     * Una aserción visible por superficie (portada, login, error) y el `lang`
     * del documento: es lo que el visitante ve al elegir `English` en el pie.
     */
    public function test_con_lang_en_la_portada_el_login_y_el_error_salen_en_ingles(): void
    {
        $portada = self::app()->handle(self::request('GET', '/?lang=en'));
        $htmlPortada = self::texto((string) $portada->getBody());

        self::assertStringContainsString('<html lang="en"', $htmlPortada);
        self::assertStringContainsString('Full-stack PHP framework with server-side rendering', $htmlPortada);
        self::assertStringContainsString('Operational features', $htmlPortada);
        self::assertStringContainsString('Core & Backend', $htmlPortada);
        self::assertStringContainsString('<span>Sign in</span>', $htmlPortada);
        self::assertStringNotContainsString('Características operativas', $htmlPortada);

        $htmlLogin = self::texto((string) self::app()->handle(self::request('GET', '/login?lang=en'))->getBody());

        self::assertStringContainsString('<title>Sign in</title>', $htmlLogin);
        self::assertStringContainsString('Enter your email and password to continue.', $htmlLogin);
        self::assertStringContainsString('Remember me for 30 days', $htmlLogin);
        self::assertStringContainsString('<span>Log in</span>', $htmlLogin);
        self::assertStringNotContainsString('Recordarme 30 días', $htmlLogin);

        $htmlError = self::texto((string) self::app()->handle(self::request('GET', '/no-existe?lang=en'))->getBody());

        self::assertStringContainsString('<html lang="en"', $htmlError);
        self::assertStringContainsString('<title>404 · My App</title>', $htmlError);
        self::assertStringContainsString('We could not find that page.', $htmlError);
        self::assertStringContainsString('<span>Back to home</span>', $htmlError);
        self::assertStringContainsString('aria-label="My App, go to the home page"', $htmlError);
    }

    /**
     * Las páginas de error respetan el locale y llevan `Vary`.
     *
     * El 404 lo lanza el routing y el 500 una excepción de la ruta; los dos los
     * renderiza el error middleware, que corre POR FUERA de `LocaleMiddleware`,
     * así que la respuesta no hereda ni el idioma ni la cabecera: la resuelve y
     * la agrega el propio handler. Sin `Vary`, un caché guarda el 404 en un
     * idioma y lo sirve a todos.
     */
    public function test_las_paginas_de_error_llevan_vary_y_el_lang_del_locale(): void
    {
        foreach ([
            'es' => ['', 'lang="es"', 'No encontramos esa página.', 'Algo salió mal de nuestro lado.'],
            'en' => ['?lang=en', 'lang="en"', 'We could not find that page.', 'Something went wrong on our side.'],
        ] as $locale => [$query, $lang, $noEncontrada, $caida]) {
            $notFound = self::app()->handle(self::request('GET', '/no-existe' . $query));
            $htmlNotFound = (string) $notFound->getBody();

            self::assertSame(404, $notFound->getStatusCode());
            self::assertStringContainsString('Accept-Language', $notFound->getHeaderLine('Vary'), "El 404 en {$locale} no lleva Vary.");
            self::assertStringContainsString('<html ' . $lang, $htmlNotFound, "El 404 en {$locale} no declara el idioma.");
            self::assertStringContainsString($noEncontrada, $htmlNotFound);

            $serverError = self::app()->handle(self::request('GET', '/_test/boom' . $query));
            $htmlServerError = (string) $serverError->getBody();

            self::assertSame(500, $serverError->getStatusCode());
            self::assertStringContainsString('Accept-Language', $serverError->getHeaderLine('Vary'), "El 500 en {$locale} no lleva Vary.");
            self::assertStringContainsString('<html ' . $lang, $htmlServerError, "El 500 en {$locale} no declara el idioma.");
            self::assertStringContainsString($caida, $htmlServerError);
        }
    }

    /**
     * Los flashes de `AuthController` salen en el idioma activo.
     *
     * Credenciales inválidas (constante histórica del controlador) y el aviso
     * de logout: los dos se escriben con `Trans::t()`, así que el objeto que
     * usa el controlador y el que usa la plantilla son el mismo traductor.
     */
    public function test_los_flashes_de_auth_salen_en_el_idioma_activo(): void
    {
        $token = self::token('/login');

        $invalido = (string) self::app()->handle(self::request('POST', '/login?lang=en', [
            'email'    => 'nadie-idioma@ejemplo.com',
            'password' => 'clave-errada',
        ] + $token))->getBody();

        self::assertStringContainsString('Invalid credentials.', $invalido);
        self::assertStringNotContainsString('Credenciales inválidas.', $invalido);

        self::loginAs();
        $salir = self::token('/');

        $response = self::app()->handle(self::request('POST', '/logout?lang=en', $salir));

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString(
            'Signed out.',
            (string) self::app()->handle(self::request('GET', '/login?lang=en'))->getBody()
        );
    }

    /**
     * El aviso del Guard cuando falla el CSRF también sale del catálogo.
     *
     * Sin token no hay form válido: el handler pone el flash y redirige al
     * login, que es donde el visitante lo lee. Con `?lang=en` tiene que salir en
     * inglés, igual que cualquier otro texto de la pantalla.
     */
    public function test_el_aviso_de_csrf_sale_en_el_idioma_activo(): void
    {
        $response = self::app()->handle(self::request('POST', '/login?lang=en', [
            'email'    => 'nadie@ejemplo.com',
            'password' => 'x',
        ]));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));

        $html = (string) self::app()->handle(self::request('GET', '/login?lang=en'))->getBody();

        self::assertStringContainsString('Your session expired. Sign in again.', $html);
        self::assertStringNotContainsString('Tu sesión expiró.', $html);
    }

    /**
     * Texto del render, con las entidades HTML decodificadas.
     *
     * Lo que se compara es lo que se lee, no cómo está escapado: `Núcleo &
     * Backend` hoy es texto literal de la plantilla y mañana sale del catálogo
     * con autoescape (`&amp;`). El carácter que ve el usuario es el mismo.
     */
    private static function texto(string $html): string
    {
        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Los dos literales de `estado ? 'a' : 'b'`, ya decodificados.
     *
     * Devuelve `[]` cuando el ternario no se puede leer: ése es justo el modo en
     * que se rompe la directiva, porque una comilla sin escapar cierra la
     * cadena antes de tiempo.
     *
     * @return list<string>
     */
    private static function literalesDelTernario(string $expresion): array
    {
        $pregunta = strpos($expresion, "? '");
        $separador = strpos($expresion, "' : '");
        $cierre = strrpos($expresion, "'");

        if ($pregunta === false || $separador === false || $cierre === false) {
            return [];
        }

        if ($separador <= $pregunta || $cierre <= $separador) {
            return [];
        }

        $literales = [
            substr($expresion, $pregunta + 3, $separador - $pregunta - 3),
            substr($expresion, $separador + 5, $cierre - $separador - 5),
        ];

        $textos = [];

        foreach ($literales as $literal) {
            if (!self::literalCompleto($literal)) {
                return [];
            }

            // El escape de Twig para JS produce JSON válido (`\uXXXX`, `\\`,
            // `\/`, `\n`…), así que un literal sano se decodifica como string.
            $texto = json_decode('"' . $literal . '"', true);

            if (!is_string($texto)) {
                return [];
            }

            $textos[] = $texto;
        }

        return $textos;
    }

    /**
     * ¿El literal no tiene comillas sin escapar?
     *
     * Se salta el carácter que sigue a cada barra: `\u0027` es una comilla
     * inofensiva, una `'` suelta cierra la cadena.
     */
    private static function literalCompleto(string $literal): bool
    {
        $largo = strlen($literal);

        for ($i = 0; $i < $largo; $i++) {
            if ($literal[$i] === '\\') {
                $i++;
                continue;
            }

            if ($literal[$i] === "'" || $literal[$i] === '"') {
                return false;
            }
        }

        return $largo > 0;
    }
}
