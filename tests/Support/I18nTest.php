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

    /** S4: los dos catálogos existen, son arrays planos y declaran las mismas claves. */
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
