<?php

declare(strict_types=1);

namespace Framework\I18n;

/**
 * Datos de formato por locale: separadores de número y patrones de fecha.
 *
 * Vive en código y no en `lang/` a propósito (S2): un separador de miles no es
 * texto traducible. Si los patrones vivieran en el catálogo, editar una
 * traducción podría cambiar `1.234` por `1,234` sin que nadie lo note, y el
 * formato dejaría de ser responsabilidad del framework. Acá agregar un idioma
 * es agregar una entrada —y sólo eso—: `decimal` y `group` los usa
 * `Format::number()` y los tres patrones de fecha los usa `Format::date()` con
 * el nombre del kind.
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

    /** Locale de reserva del proyecto: el mismo default de `TranslatorFactory`. */
    private const BASE = TranslatorFactory::DEFAULT_LOCALE;

    /**
     * Entradas conocidas: sólo los idiomas que el cromo ofrece hoy.
     *
     * @var array<string, array{decimal: string, group: string, numeric: string, numeric_time: string, time: string}>
     */
    private const LOCALES = [
        'es' => [
            'decimal'      => ',',
            'group'        => '.',
            'numeric'      => 'd/m/Y',
            'numeric_time' => 'd/m/Y H:i',
            'time'         => 'H:i',
        ],
        'en' => [
            'decimal'      => '.',
            'group'        => ',',
            'numeric'      => 'm/d/Y',
            'numeric_time' => 'm/d/Y g:i A',
            'time'         => 'g:i A',
        ],
    ];

    /** Clase de datos: se usa estáticamente. */
    private function __construct()
    {
    }

    /**
     * Datos de un locale, con fallback en cadena y sin lanzar.
     *
     * Un locale sin entrada usa el base (`APP_LOCALE`) y, si el base tampoco
     * tiene —un `APP_LOCALE=pt`— la entrada de reserva del proyecto, que
     * siempre existe. Es el mismo criterio que el catálogo del traductor: un
     * idioma a medio configurar muestra números y fechas legibles con el
     * formato del idioma de respaldo en vez de romper el render.
     *
     * @return array{decimal: string, group: string, numeric: string, numeric_time: string, time: string}
     */
    public static function for(string $locale): array
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
