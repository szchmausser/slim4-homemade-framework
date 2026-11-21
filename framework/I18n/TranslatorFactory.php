<?php

declare(strict_types=1);

namespace Framework\I18n;

use Framework\Support\Env;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Arma el traductor de la app: catálogos PHP en `lang/`, locale activo desde
 * `APP_LOCALE` y fallback desde `APP_FALLBACK_LOCALE`.
 *
 * Los dos defaults viven acá y no en `.env.example` a propósito: el archivo
 * `.env` no se versiona (S6), así que un clon tiene que arrancar en español sin
 * configurar nada. La variable sólo hace falta para cambiar de idioma.
 *
 * Cada locale es UN archivo plano, `lang/<locale>.php`, que devuelve
 * `clave => texto` (S4). Si el archivo no existe —un idioma a medio traducir—
 * no se agrega el recurso y sus claves las resuelve el fallback: una clave sin
 * traducir no es un error, es texto en el idioma de respaldo.
 */
final class TranslatorFactory
{
    public const DEFAULT_LOCALE = 'es';

    public const DEFAULT_FALLBACK_LOCALE = 'en';

    /**
     * Letras, dígitos, guion y guion bajo: alcanza para `es`, `en`, `pt_br`.
     *
     * Es también la guarda del path del catálogo: el locale termina dentro de
     * `lang/<locale>.php`, así que un valor con `/` o `..` no puede convertir
     * esa ruta en otra cosa. Un valor que no matchea cae en el default.
     */
    private const LOCALE_PATTERN = '/^[a-z0-9_-]{1,20}$/';

    /** Clase de fábrica: se usa estáticamente. */
    private function __construct()
    {
    }

    /**
     * Traductor listo para usar.
     *
     * Los argumentos existen para los tests y para un eventual "previsualizar
     * otro idioma" sin reiniciar el contenedor; en runtime se pasan `null` y
     * mandan las variables de entorno.
     */
    public static function create(?string $locale = null, ?string $fallbackLocale = null): TranslatorInterface
    {
        $locale = self::resolve($locale, 'APP_LOCALE', self::DEFAULT_LOCALE);
        $fallback = self::resolve($fallbackLocale, 'APP_FALLBACK_LOCALE', self::DEFAULT_FALLBACK_LOCALE);

        $translator = new Translator($locale);
        // Con fallback == locale no hay cadena que recorrer: el catálogo activo
        // ya es el mismo y Symfony cargaría el archivo dos veces.
        $translator->setFallbackLocales($fallback === $locale ? [] : [$fallback]);
        $translator->addLoader('php', new PhpFileLoader());

        foreach (array_unique([$locale, $fallback]) as $each) {
            $file = self::directory() . '/' . $each . '.php';

            if (is_file($file)) {
                $translator->addResource('php', $file, $each);
            }
        }

        return $translator;
    }

    /** Directorio de catálogos: `lang/` en la raíz del proyecto. */
    private static function directory(): string
    {
        return dirname(__DIR__, 2) . '/lang';
    }

    /**
     * Locale normalizado: minúsculas, sin espacios y sin nada que no sea un
     * código de idioma. Vacío o inválido usa el default.
     */
    private static function resolve(?string $value, string $key, string $default): string
    {
        $locale = strtolower(trim($value ?? Env::get($key, $default)));

        return preg_match(self::LOCALE_PATTERN, $locale) === 1 ? $locale : $default;
    }
}
