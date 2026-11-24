<?php

declare(strict_types=1);

namespace Framework\I18n;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Formato de cantidades y fechas según el locale activo.
 *
 *     Format::number(1234.5, 2);                     // 1.234,50 en es; 1,234.50 en en
 *     Format::date($task->completed_at, 'numeric_time'); // 07/03/2024 15:04 en es
 *
 * La división es la de S2: el framework posee el primitivo —separadores y
 * patrones según idioma— y la app elige **qué** mostrar y con cuántos
 * decimales. Los patrones no son texto traducible y no viven en `lang/`:
 * `LocaleData` los tiene en código, así una edición de traducción no puede
 * romper el formato.
 *
 * La API expone `kinds` y no patrones libres: la app pide «numérica con hora»
 * y el framework decide cómo se escribe en el idioma activo. Sin `ext-intl` y
 * sin dependencias nuevas (S3): el motor es `number_format()` y
 * `DateTimeInterface::format()`.
 */
final class Format
{
    /** Clase de utilidad: se usa estáticamente. */
    private function __construct()
    {
    }

    /**
     * Número con los separadores del locale activo.
     *
     * La app decide cuántos decimales mostrar; el idioma decide con qué
     * carácter se agrupan y se separan. Un negativo conserva su signo y el
     * cero respeta los decimales pedidos (`0,00`).
     */
    public static function number(int|float $value, int $decimals = 0): string
    {
        $data = LocaleData::for(Trans::locale());

        return number_format((float) $value, $decimals, $data['decimal'], $data['group']);
    }

    /**
     * Fecha y/o hora con el patrón del locale activo para ese kind.
     *
     * Un string se interpreta con `DateTimeImmutable`: es lo que llega crudo
     * de la base cuando el valor no viene ya como Carbon.
     */
    public static function date(DateTimeInterface|string $value, string $kind = 'numeric'): string
    {
        $pattern = self::pattern($kind);
        $moment = is_string($value) ? new DateTimeImmutable($value) : $value;

        return $moment->format($pattern);
    }

    /**
     * Patrón del kind en el locale activo.
     *
     * Un kind desconocido es un error de programación —una vista escribió
     * `'short'` creyendo que existía—, no una entrada del usuario, así que
     * lanza con la lista de los válidos en el mensaje (S3).
     */
    private static function pattern(string $kind): string
    {
        if (!in_array($kind, LocaleData::KINDS, true)) {
            throw new InvalidArgumentException(sprintf(
                "Unknown date format kind '%s'; valid kinds: %s.",
                $kind,
                implode(', ', LocaleData::KINDS)
            ));
        }

        $data = LocaleData::for(Trans::locale());

        // El kind está en KINDS y LocaleData garantiza las tres claves.
        return $data[$kind];
    }
}
