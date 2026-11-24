<?php

declare(strict_types=1);

namespace Framework\I18n;

use InvalidArgumentException;

/**
 * Datos de formato por locale: separadores, fecha y ciclo horario.
 *
 * Vive en código y no en `lang/` a propósito (S2): un separador de miles no es
 * texto traducible. Si los patrones vivieran en el catálogo, editar una
 * traducción podría cambiar `1.234` por `1,234` sin que nadie lo note, y el
 * formato dejaría de ser responsabilidad del framework. Acá agregar un idioma
 * es agregar una entrada —y sólo eso—: `decimal` y `group` los usa
 * `Format::number()`; la fecha y el ciclo horario, `Format::date()`.
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

    /** Ciclo horario cuando la entrada del locale no declara `hour_cycle`. */
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
     * `hour_cycle` es opcional y ausente significa `'12'`, el
     * `DEFAULT_HOUR_CYCLE`: es el ciclo natural del campo y el que usa un
     * locale que no lo declara. `es` declara `'24'` porque es su uso natural
     * —forzarlo a 12 haría que el español muestre «3:04 PM»—; `en` lo omite y
     * toma el default. `numeric` es sólo la fecha: la hora sale de
     * `HOUR_PATTERNS` según el ciclo.
     *
     * @var array<string, array{decimal: string, group: string, numeric: string, hour_cycle?: string}>
     */
    private const LOCALES = [
        'es' => [
            'decimal'    => ',',
            'group'      => '.',
            'numeric'    => 'd/m/Y',
            'hour_cycle' => '24',
        ],
        'en' => [
            'decimal' => '.',
            'group'   => ',',
            'numeric' => 'm/d/Y',
            // Sin `hour_cycle`: usa el default del campo, `'12'`.
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
     * El ciclo es el forzado con `useHourCycle()` si hay uno; si no, el que
     * declara la entrada o el default del campo. Las horas y sus combinaciones
     * se derivan acá, así la tabla no repite patrones por kind.
     *
     * @return array{decimal: string, group: string, numeric: string, hour_cycle: string, numeric_time: string, time: string}
     */
    public static function for(string $locale): array
    {
        $entry = self::entry($locale);
        $cycle = self::$hourCycle ?? $entry['hour_cycle'] ?? self::DEFAULT_HOUR_CYCLE;
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
     * Fija el ciclo horario de toda la app, o vuelve al natural del locale.
     *
     * Es una decisión de **app**, no de locale: se llama una vez en el
     * bootstrap y gana sobre `hour_cycle` de la tabla. `null` no fija nada:
     * cada locale vuelve a su ciclo declarado (o al default del campo). Los
     * valores aceptados son `'12'`, `'24'` y `null`; cualquier otro es un
     * error de programación, así que lanza con la lista en el mensaje.
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
     * @return array{decimal: string, group: string, numeric: string, hour_cycle?: string}
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
