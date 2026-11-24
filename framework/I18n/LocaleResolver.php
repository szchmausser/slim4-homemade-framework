<?php

declare(strict_types=1);

namespace Framework\I18n;

/**
 * Elige el locale de una petición. Función pura: no lee la sesión, ni cabeceras,
 * ni el traductor; recibe los cinco datos que hacen falta y devuelve un código
 * de la lista de catálogos disponibles.
 *
 * La precedencia es explícito (`?lang=`) > sesión > `Accept-Language` > default.
 * Sirve a los dos casos del stack: en la web el visitante que elige un idioma lo
 * conserva en su sesión, y una API sin sesión negocia con la cabecera. El
 * default es el locale configurado (`APP_LOCALE`), y si ese no tiene catálogo,
 * el primero disponible.
 *
 * `Accept-Language` se parsea de verdad, porque es la única entrada que escribe
 * el cliente: q-values, variantes regionales (`es-VE` → `es`), comodín, `q=0`
 * («no acepto») y basura. Nunca lanza y nunca devuelve un código que no esté en
 * la lista: un locale sin catálogo se ve como claves crudas en la pantalla.
 */
final class LocaleResolver
{
    /** Mismo formato que `TranslatorFactory`: letras, dígitos, guion y guion bajo. */
    private const LOCALE_PATTERN = '/^[a-z0-9_-]{1,20}$/';

    /** Etiqueta BCP 47 razonable: `es`, `en-US`, `zh-Hant-TW`. */
    private const TAG_PATTERN = '/^[a-z]{1,8}(?:-[a-z0-9]{1,8})*$/';

    /** Clase de utilidad: se usa estáticamente. */
    private function __construct()
    {
    }

    /**
     * Locale elegido para esta petición.
     *
     * @param string|null  $explicit      `?lang=` crudo
     * @param string|null  $session       locale guardado en la sesión
     * @param string|null  $acceptLanguage cabecera `Accept-Language` cruda
     * @param string       $default       locale configurado de la app
     * @param list<string> $available     catálogos que existen
     */
    public static function resolve(
        ?string $explicit,
        ?string $session,
        ?string $acceptLanguage,
        string $default,
        array $available
    ): string {
        $locales = self::normalizeAvailable($available);

        foreach ([$explicit, $session] as $candidate) {
            $found = $candidate === null ? null : self::find($candidate, $locales);

            if ($found !== null) {
                return $found;
            }
        }

        $fallback = self::fallback($default, $locales);

        foreach (self::accepted($acceptLanguage) as $tag) {
            // El comodín no nombra ningún idioma: cualquier catálogo sirve, y
            // el que la app prefiere es el suyo.
            if ($tag === '*') {
                return $fallback;
            }

            $found = self::find($tag, $locales);

            if ($found !== null) {
                return $found;
            }
        }

        return $fallback;
    }

    /**
     * Código de `$available` que corresponde a la etiqueta, o `null`.
     *
     * `es-VE` no tiene catálogo propio: se prueba la etiqueta completa y, si no
     * está, el idioma base (`es`). La comparación ignora mayúsculas y trata
     * `pt_BR` y `pt-br` como el mismo código. Devolver el código de la lista —y
     * no la etiqueta pedida— es lo que garantiza que el locale tenga catálogo.
     *
     * @param list<string> $available
     */
    public static function find(string $tag, array $available): ?string
    {
        $normalized = self::normalizeTag($tag);

        if ($normalized === null) {
            return null;
        }

        $candidates = [$normalized];
        $base = strstr($normalized, '-', true);

        if (is_string($base)) {
            $candidates[] = $base;
        }

        foreach ($candidates as $candidate) {
            foreach ($available as $locale) {
                if (self::normalizeTag($locale) === $candidate) {
                    return $locale;
                }
            }
        }

        return null;
    }

    /**
     * Etiquetas de `Accept-Language` en el orden en que se deben probar: q
     * descendente y, a igual q, el orden de la cabecera.
     *
     * `q=0` significa «no acepto este idioma» y se descarta. Una etiqueta
     * malformada o un `q` que no es número también se descartan: la cabecera la
     * escribe el cliente y no puede tumbar la petición.
     *
     * @return list<string>
     */
    public static function accepted(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $weighted = [];

        foreach (explode(',', $header) as $position => $part) {
            $segments = explode(';', $part);
            $tag = trim((string) array_shift($segments));
            $quality = self::quality($segments);

            if ($tag === '*') {
                $weighted[] = ['tag' => '*', 'q' => $quality, 'position' => $position];

                continue;
            }

            $normalized = self::normalizeTag($tag);

            if ($normalized === null) {
                continue;
            }

            $weighted[] = ['tag' => $normalized, 'q' => $quality, 'position' => $position];
        }

        usort($weighted, static function (array $a, array $b): int {
            if ($a['q'] !== $b['q']) {
                return $b['q'] <=> $a['q'];
            }

            return $a['position'] <=> $b['position'];
        });

        $tags = [];

        foreach ($weighted as $entry) {
            if ($entry['q'] <= 0.0) {
                continue;
            }

            $tags[] = $entry['tag'];
        }

        return $tags;
    }

    /**
     * Etiqueta comparable: minúsculas y `_` como `-`; `null` si no es una
     * etiqueta de idioma.
     */
    private static function normalizeTag(string $value): ?string
    {
        $tag = strtolower(str_replace('_', '-', trim($value)));

        if ($tag === '' || strlen($tag) > 35) {
            return null;
        }

        return preg_match(self::TAG_PATTERN, $tag) === 1 ? $tag : null;
    }

    /**
     * q de una entrada; 1 cuando no declara `q`.
     *
     * Un `q` que no es número no es una preferencia válida, así que la entrada
     * se descarta (0). Se recorta a [0, 1]: `q=2` no puede adelantar a todos.
     *
     * @param list<string> $params
     */
    private static function quality(array $params): float
    {
        foreach ($params as $param) {
            $pair = explode('=', $param, 2);

            if (strtolower(trim($pair[0])) !== 'q') {
                continue;
            }

            $value = trim($pair[1] ?? '');

            return is_numeric($value) ? max(0.0, min(1.0, (float) $value)) : 0.0;
        }

        return 1.0;
    }

    /**
     * Lista de catálogos normalizada: códigos válidos, sin duplicados.
     *
     * @param array<array-key, mixed> $available
     *
     * @return list<string>
     */
    private static function normalizeAvailable(array $available): array
    {
        $locales = [];

        foreach ($available as $value) {
            if (!is_string($value)) {
                continue;
            }

            $code = strtolower(trim($value));

            if (preg_match(self::LOCALE_PATTERN, $code) !== 1 || in_array($code, $locales, true)) {
                continue;
            }

            $locales[] = $code;
        }

        return $locales;
    }

    /**
     * Locale de respaldo: el default si tiene catálogo; si no, el primero
     * disponible.
     *
     * @param list<string> $available
     */
    private static function fallback(string $default, array $available): string
    {
        $found = self::find($default, $available);

        if ($found !== null) {
            return $found;
        }

        return $available[0] ?? self::normalizeTag($default) ?? $default;
    }
}
