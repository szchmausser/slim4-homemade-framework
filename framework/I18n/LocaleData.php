<?php

declare(strict_types=1);

namespace Framework\I18n;

use InvalidArgumentException;

/**
 * Datos de formato por locale: separadores y patrón de fecha.
 *
 * Vive en código y no en `lang/` a propósito (S2): un separador de miles no es
 * texto traducible. Si los patrones vivieran en el catálogo, editar una
 * traducción podría cambiar `1.234` por `1,234` sin que nadie lo note, y el
 * formato dejaría de ser responsabilidad del framework. Acá agregar un idioma
 * es agregar una entrada —y sólo eso—: `decimal` y `group` los usa
 * `Format::number()` y `numeric`, `Format::date()`. El ciclo horario no es del
 * locale: es una política global de la app (ver `useHourCycle()`).
 *
 * Los patrones son de `DateTimeInterface::format()`, no de ICU: el stack no
 * puede asumir `ext-intl` en Termux, y dos motores para la misma salida darían
 * dos resultados distintos según la máquina. Lo que queda afuera —nombres de
 * mes, plurales, moneda, zonas horarias— es deliberado: todavía no hay uso y
 * cada uno traería su propia tabla.
 */
final class LocaleData
{
    /** Kinds de `Format::date()`; el orden es el de la lista que muestra el error. */
    public const KINDS = ['numeric', 'numeric_time', 'time'];

    /** Ciclo horario por defecto de la app: 12 horas, para todos los idiomas. */
    private const DEFAULT_HOUR_CYCLE = '12';

    /**
     * Patrón de hora de cada ciclo. Es la única fuente de las horas: `time` y
     * `numeric_time` se derivan de acá en `for()`, no se guardan por kind.
     */
    private const HOUR_PATTERNS = [
        '12' => 'g:i A',
        '24' => 'H:i',
    ];

    /** Locale de reserva del proyecto: el mismo default de `TranslatorFactory`. */
    private const BASE = TranslatorFactory::DEFAULT_LOCALE;

    /**
     * Entradas conocidas: sólo los idiomas que el cromo ofrece hoy.
     *
     * `decimal` y `group` son los separadores de número y `numeric` es la
     * fecha. La hora no vive acá: el ciclo horario es una política global de la
     * app (`useHourCycle()`), no una propiedad del idioma, y `HOUR_PATTERNS` lo
     * traduce a patrón.
     *
     * @var array<string, array{decimal: string, group: string, numeric: string}>
     */
    private const LOCALES = [
        'es' => [
            'decimal' => ',',
            'group'   => '.',
            'numeric' => 'd/m/Y',
        ],
        'en' => [
            'decimal' => '.',
            'group'   => ',',
            'numeric' => 'm/d/Y',
        ],
    ];

    /** Ciclo forzado por la app; null = el natural de cada locale. */
    private static ?string $hourCycle = null;

    /** Clase de datos: se usa estáticamente. */
    private function __construct()
    {
    }

    /**
     * Datos resueltos de un locale, con fallback en cadena y sin lanzar.
     *
     * Un locale sin entrada usa el base (`APP_LOCALE`) y, si el base tampoco
     * tiene —un `APP_LOCALE=pt`— la entrada de reserva del proyecto, que
     * siempre existe. Es el mismo criterio que el catálogo del traductor: un
     * idioma a medio configurar muestra números y fechas legibles con el
     * formato del idioma de respaldo en vez de romper el render.
     *
     * El ciclo es global: el que fijó `useHourCycle()` o el default de la app
     * (`'12'`), nunca el del locale. Las horas y sus combinaciones se derivan
     * acá, así la tabla no repite patrones por kind.
     *
     * @return array{decimal: string, group: string, numeric: string, hour_cycle: string, numeric_time: string, time: string}
     */
    public static function for(string $locale): array
    {
        $entry = self::entry($locale);
        $cycle = self::$hourCycle ?? self::DEFAULT_HOUR_CYCLE;
        $time = self::HOUR_PATTERNS[$cycle];

        return [
            'decimal'      => $entry['decimal'],
            'group'        => $entry['group'],
            'numeric'      => $entry['numeric'],
            'hour_cycle'   => $cycle,
            'numeric_time' => $entry['numeric'] . ' ' . $time,
            'time'         => $time,
        ];
    }

    /**
     * Fija el ciclo horario de toda la app, o vuelve al default de 12 horas.
     *
     * Es una decisión de **app**, no de locale: se llama una vez en el
     * bootstrap y aplica a todos los idiomas. `null` deshace el override y
     * deja el default de la app (`'12'`, `DEFAULT_HOUR_CYCLE`); no hay ciclo
     * «natural» del idioma. Los valores aceptados son `'12'`, `'24'` y
     * `null`; cualquier otro es un error de programación, así que lanza con la
     * lista en el mensaje.
     */
    public static function useHourCycle(?string $cycle): void
    {
        if ($cycle !== null && !isset(self::HOUR_PATTERNS[$cycle])) {
            // Las claves de HOUR_PATTERNS son los ciclos; PHP las castea a int
            // por ser cadenas numéricas, así que la lista se arma con implode.
            $valid = "'" . implode("', '", array_keys(self::HOUR_PATTERNS)) . "'";

            throw new InvalidArgumentException(sprintf(
                "Unknown hour cycle '%s'; valid values: %s, null.",
                $cycle,
                $valid
            ));
        }

        self::$hourCycle = $cycle;
    }

    /**
     * Entrada del locale o, si no está, la del base y la de reserva.
     *
     * @return array{decimal: string, group: string, numeric: string}
     */
    private static function entry(string $locale): array
    {
        return self::LOCALES[self::normalize($locale)]
            ?? self::LOCALES[self::normalize(TranslatorFactory::defaultLocale())]
            ?? self::LOCALES[self::BASE];
    }

    /** Código comparable: minúsculas y sin espacios, como el resto de la i18n. */
    private static function normalize(string $locale): string
    {
        return strtolower(trim($locale));
    }
}
