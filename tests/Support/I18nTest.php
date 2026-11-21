<?php

declare(strict_types=1);

namespace Tests\Support;

use Framework\I18n\TranslatorFactory;
use Framework\Support\Env;
use Slim\Views\Twig;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

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
     */
    private const ESPANOL = [
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
        'modal.close'            => 'Cerrar',
        'field.optional'         => '(opcional)',
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
}
