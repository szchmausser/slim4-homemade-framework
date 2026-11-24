<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use Framework\I18n\Format;
use Framework\I18n\LocaleData;
use Framework\I18n\Trans;
use Framework\I18n\TranslationExtension;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * El primitivo de formato por locale (S3-S6).
 *
 * Lo que se fija acá es el contrato de la app: el separador y el patrón los
 * pone el framework según el locale activo, y la app sólo elige la forma y los
 * decimales. Los esperados son literales y no regex: `1.234` en español es tan
 * parte de la salida como el texto del catálogo, y una regex dejaría pasar un
 * cambio de separador.
 *
 * `Format` lee el locale de `Trans`, que es estático. Por eso cada caso fija
 * el locale en el traductor del contenedor —el mismo que cambia
 * `LocaleMiddleware` en cada petición— y `tearDown()` lo deja como estaba: un
 * traductor aparte dejaría a `Trans` apuntando a una instancia que el
 * middleware ya no toca y el resto de la suite vería el idioma equivocado.
 */
final class FormatTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../resources/views';

    /** Locale que tenía el traductor de la app antes de este test. */
    private string $previousLocale;

    protected function setUp(): void
    {
        $this->previousLocale = self::translator()->getLocale();

        // El ciclo horario es estático y global: cada test arranca y termina en
        // el default de 12 horas.
        LocaleData::useHourCycle(null);
    }

    protected function tearDown(): void
    {
        self::localeAware()->setLocale($this->previousLocale);
        LocaleData::useHourCycle(null);
    }

    /** Traductor de la app, ya registrado en `Trans`. */
    private static function translator(): TranslatorInterface
    {
        $translator = AppFactory::make()->getContainer()->get(TranslatorInterface::class);
        Trans::register($translator);

        return $translator;
    }

    /** El mismo singleton, visto como el contrato que sabe cambiar de idioma. */
    private static function localeAware(): LocaleAwareInterface
    {
        return AppFactory::make()->getContainer()->get(LocaleAwareInterface::class);
    }

    /** Fija el locale activo como lo haría el middleware y devuelve el traductor. */
    private static function useLocale(string $locale): TranslatorInterface
    {
        self::localeAware()->setLocale($locale);

        return self::translator();
    }

    /** Twig con la extensión real: los filtros se prueban como los usa la app. */
    private static function twig(TranslatorInterface $translator): Environment
    {
        $twig = new Environment(new FilesystemLoader(self::VIEWS), [
            'autoescape'       => 'html',
            'strict_variables' => false,
        ]);
        $twig->addExtension(new TranslationExtension($translator));

        return $twig;
    }

    /** Fecha fija: el formato se prueba contra un valor que no cambia por día. */
    private static function moment(): DateTimeImmutable
    {
        return new DateTimeImmutable('2024-03-07 15:04:05');
    }

    /**
     * Metadata con la forma que devuelve `Pagination::paginate()`, con cuatro
     * cifras para que el separador de miles sea observable.
     *
     * @return array<string, mixed>
     */
    private static function pagination(): array
    {
        return [
            'total'            => 1234,
            'per_page'         => 10,
            'current_page'     => 1,
            'last_page'        => 124,
            'from'             => 1,
            'to'               => 10,
            'base_url'         => '/tareas',
            'pages'            => [1, 2, 3, 4, 5],
            'allowed_per_page' => [5, 10, 25, 50, 100],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int|float, 2: int, 3: string}>
     */
    public static function numbers(): array
    {
        return [
            'es: 1234 sin decimales'          => ['es', 1234, 0, '1.234'],
            'es: 1234 con un decimal'         => ['es', 1234, 1, '1.234,0'],
            'es: 1234 con dos decimales'      => ['es', 1234, 2, '1.234,00'],
            'es: 1234,5 sin decimales'        => ['es', 1234.5, 0, '1.235'],
            'es: 1234,5 con un decimal'       => ['es', 1234.5, 1, '1.234,5'],
            'es: 1234,5 con dos decimales'    => ['es', 1234.5, 2, '1.234,50'],
            'es: negativo con dos decimales'  => ['es', -1234.5, 2, '-1.234,50'],
            'es: cero sin decimales'          => ['es', 0, 0, '0'],
            'es: cero con dos decimales'      => ['es', 0, 2, '0,00'],
            'en: 1234 sin decimales'          => ['en', 1234, 0, '1,234'],
            'en: 1234 con un decimal'         => ['en', 1234, 1, '1,234.0'],
            'en: 1234 con dos decimales'      => ['en', 1234, 2, '1,234.00'],
            'en: 1234.5 sin decimales'        => ['en', 1234.5, 0, '1,235'],
            'en: 1234.5 con un decimal'       => ['en', 1234.5, 1, '1,234.5'],
            'en: 1234.5 con dos decimales'    => ['en', 1234.5, 2, '1,234.50'],
            'en: negativo con dos decimales'  => ['en', -1234.5, 2, '-1,234.50'],
            'en: cero sin decimales'          => ['en', 0, 0, '0'],
            'en: cero con dos decimales'      => ['en', 0, 2, '0.00'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_number(string $locale, int|float $value, int $decimals, string $expected): void
    {
        self::useLocale($locale);

        self::assertSame($expected, Format::number($value, $decimals));
    }

    public function test_number_usa_cero_decimales_por_defecto(): void
    {
        self::useLocale('es');

        self::assertSame('1.234', Format::number(1234));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function dates(): array
    {
        return [
            'es: numérica'            => ['es', 'numeric', '07/03/2024'],
            'es: numérica con hora'   => ['es', 'numeric_time', '07/03/2024 3:04 PM'],
            'es: sólo hora'           => ['es', 'time', '3:04 PM'],
            'en: numérica'            => ['en', 'numeric', '03/07/2024'],
            'en: numérica con hora'   => ['en', 'numeric_time', '03/07/2024 3:04 PM'],
            'en: sólo hora'           => ['en', 'time', '3:04 PM'],
        ];
    }

    #[DataProvider('dates')]
    public function test_date(string $locale, string $kind, string $expected): void
    {
        self::useLocale($locale);

        self::assertSame($expected, Format::date(self::moment(), $kind));
    }

    public function test_date_usa_numeric_por_defecto(): void
    {
        self::useLocale('es');

        self::assertSame('07/03/2024', Format::date(self::moment()));
    }

    /** Un string de la base se interpreta como fecha, igual que el objeto. */
    public function test_date_acepta_un_string(): void
    {
        self::useLocale('es');

        self::assertSame('07/03/2024 3:04 PM', Format::date('2024-03-07 15:04:05', 'numeric_time'));
    }

    /** S8: el default es 12 horas para todos los idiomas, también para `es`. */
    public function test_es_usa_12_horas_por_defecto(): void
    {
        self::useLocale('es');

        self::assertSame('12', LocaleData::for('es')['hour_cycle']);
        self::assertSame('07/03/2024 3:04 PM', Format::date(self::moment(), 'numeric_time'));
        self::assertSame('3:04 PM', Format::date(self::moment(), 'time'));
    }

    /** S8: `en` también arranca en 12 horas; el ciclo no depende del idioma. */
    public function test_en_usa_12_horas_por_defecto(): void
    {
        self::useLocale('en');

        self::assertSame('12', LocaleData::for('en')['hour_cycle']);
        self::assertSame('03/07/2024 3:04 PM', Format::date(self::moment(), 'numeric_time'));
        self::assertSame('3:04 PM', Format::date(self::moment(), 'time'));
    }

    /** S8: el ciclo es una política global; 24 horas aplica a los dos idiomas. */
    public function test_use_hour_cycle_24_lleva_los_dos_idiomas_a_24_horas(): void
    {
        LocaleData::useHourCycle('24');

        self::useLocale('es');
        self::assertSame('24', LocaleData::for('es')['hour_cycle']);
        self::assertSame('07/03/2024 15:04', Format::date(self::moment(), 'numeric_time'));
        self::assertSame('15:04', Format::date(self::moment(), 'time'));
        self::assertSame('07/03/2024', Format::date(self::moment(), 'numeric'), 'El ciclo no toca la fecha.');

        self::useLocale('en');
        self::assertSame('24', LocaleData::for('en')['hour_cycle']);
        self::assertSame('03/07/2024 15:04', Format::date(self::moment(), 'numeric_time'));
        self::assertSame('15:04', Format::date(self::moment(), 'time'));
    }

    /** S8: `null` deshace el override y vuelve al default de 12 horas. */
    public function test_use_hour_cycle_null_vuelve_a_12_horas(): void
    {
        LocaleData::useHourCycle('24');

        self::useLocale('en');
        self::assertSame('03/07/2024 15:04', Format::date(self::moment(), 'numeric_time'));

        LocaleData::useHourCycle(null);
        self::assertSame('03/07/2024 3:04 PM', Format::date(self::moment(), 'numeric_time'));

        self::useLocale('es');
        self::assertSame('07/03/2024 3:04 PM', Format::date(self::moment(), 'numeric_time'));
    }

    /** S8: un ciclo que no es `'12'`, `'24'` ni `null` es error de programación. */
    public function test_un_ciclo_horario_desconocido_lanza_con_la_lista_de_los_validos(): void
    {
        try {
            LocaleData::useHourCycle('13');
        } catch (InvalidArgumentException $e) {
            self::assertSame(
                "Unknown hour cycle '13'; valid values: '12', '24', null.",
                $e->getMessage()
            );

            return;
        }

        self::fail('Un ciclo desconocido tiene que lanzar InvalidArgumentException.');
    }

    /** S4: `pt` no tiene tabla; cae al locale base y no lanza. */
    public function test_un_locale_sin_datos_cae_al_locale_base(): void
    {
        self::useLocale('pt');

        self::assertSame('1.234', Format::number(1234), 'pt tiene que usar el separador del locale base.');
        self::assertSame('07/03/2024', Format::date(self::moment()), 'pt tiene que usar los patrones del locale base.');
        self::assertSame(LocaleData::for('es'), LocaleData::for('pt'), 'La tabla de pt tiene que ser la del locale base.');
    }

    /**
     * S4: si el locale base tampoco tiene tabla, el fallback final es `es`.
     *
     * Con `APP_LOCALE=pt`, `LocaleData::for('pt')` prueba `pt` (no está) y después
     * el base `pt` (tampoco está), así que sólo la reserva `es` puede responder.
     * Corre en un proceso nuevo porque `APP_LOCALE` se lee al construir el
     * traductor y `Trans` cachea el suyo: el único estado limpio es un PHP que
     * nunca arrancó la app, igual que el probe de `tests/Support/I18nTest.php`.
     */
    public function test_si_el_locale_base_tampoco_tiene_tabla_cae_a_es(): void
    {
        $env = getenv();
        $env['APP_LOCALE'] = 'pt';

        $script = 'require "vendor/autoload.php"; '
            . '$momento = new DateTimeImmutable("2024-03-07 15:04:05"); '
            . 'echo Framework\\I18n\\Format::number(1234), "|", Framework\\I18n\\Format::date($momento);';

        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            __DIR__ . '/../..',
            $env
        );

        if (!is_resource($process)) {
            self::fail('No se pudo ejecutar PHP para probar el fallback final de LocaleData.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        self::assertSame(0, proc_close($process), 'Format explotó con APP_LOCALE=pt: ' . $stderr);
        self::assertSame('', $stderr);
        self::assertSame('1.234|07/03/2024', $stdout, 'El fallback final de LocaleData tiene que ser es.');
    }

    /** Un kind desconocido es error de programación, no dato del usuario (S3). */
    public function test_un_kind_desconocido_lanza_con_la_lista_de_los_validos(): void
    {
        try {
            Format::date(self::moment(), 'iso');
        } catch (InvalidArgumentException $e) {
            self::assertSame(
                "Unknown date format kind 'iso'; valid kinds: numeric, numeric_time, time.",
                $e->getMessage()
            );

            return;
        }

        self::fail('Un kind desconocido tiene que lanzar InvalidArgumentException.');
    }

    /** Trans es la fuente del locale: no se lee `APP_LOCALE` por otro camino. */
    public function test_trans_expone_el_locale_del_traductor_registrado(): void
    {
        self::useLocale('en');
        self::assertSame('en', Trans::locale());

        self::useLocale('es');
        self::assertSame('es', Trans::locale());
    }

    /** S5: los filtros existen en Twig y formatean con el locale activo. */
    public function test_los_filtros_num_y_localdate_usan_el_locale_activo(): void
    {
        $fecha = self::moment();

        $es = self::twig(self::useLocale('es'))
            ->createTemplate("{{ 1234|num }}|{{ 1234.5|num(2) }}|{{ fecha|localdate }}|{{ fecha|localdate('numeric_time') }}|{{ fecha|localdate('time') }}")
            ->render(['fecha' => $fecha]);

        self::assertSame('1.234|1.234,50|07/03/2024|07/03/2024 3:04 PM|3:04 PM', $es);

        $en = self::twig(self::useLocale('en'))
            ->createTemplate("{{ 1234|num }}|{{ 1234.5|num(2) }}|{{ fecha|localdate }}|{{ fecha|localdate('numeric_time') }}|{{ fecha|localdate('time') }}")
            ->render(['fecha' => $fecha]);

        self::assertSame('1,234|1,234.50|03/07/2024|03/07/2024 3:04 PM|3:04 PM', $en);
    }

    /** S5: los filtros devuelven texto normal; Twig los escapa como cualquier string. */
    public function test_los_filtros_no_marcan_la_salida_como_segura(): void
    {
        $filtros = [];

        foreach ((new TranslationExtension(self::useLocale('es')))->getFilters() as $filtro) {
            $filtros[$filtro->getName()] = $filtro;
        }

        foreach (['num', 'localdate'] as $nombre) {
            self::assertArrayHasKey($nombre, $filtros);
            self::assertSame([], $filtros[$nombre]->getSafe(new \Twig\Node\Node()));
            self::assertSame([], $filtros[$nombre]->getPreservesSafety());
        }
    }

    /** S6: la paginación no imprime contadores crudos; el separador es del locale. */
    public function test_la_paginacion_formatea_los_tres_contadores(): void
    {
        $twig = self::twig(self::useLocale('es'));

        $html = $twig->render('components/pagination/simple.twig', [
            'pagination' => self::pagination(),
            'target'     => '#tareas-panel',
        ]);

        self::assertStringContainsString('Mostrando 1 al 10 de 1.234 registros', $html);
    }

    /** S6: con el locale activo en inglés, el separador es el de `en`. */
    public function test_la_paginacion_formatea_los_tres_contadores_en_ingles(): void
    {
        $twig = self::twig(self::useLocale('en'));

        $html = $twig->render('components/pagination/simple.twig', [
            'pagination' => self::pagination(),
            'target'     => '#tareas-panel',
        ]);

        self::assertStringContainsString('Showing 1 to 10 of 1,234 records', $html);
    }
}
