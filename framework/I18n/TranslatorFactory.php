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
 * Cada locale tiene UN catálogo base, `lang/<locale>.php`, que devuelve
 * `clave => texto` (S4), y opcionalmente catálogos de demo,
 * `lang/<locale>/<demo>.php`, que instala y quita el propio demo: mientras no
 * esté instalado, sus claves no existen en el proyecto y `t()` devuelve la
 * clave. Si el catálogo base no existe —un idioma a medio traducir— no se
 * agrega el recurso y sus claves las resuelve el fallback: una clave sin
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

        // Todos los catálogos, no sólo el activo y el fallback: el locale se
        // resuelve por request (`LocaleMiddleware`), así que la petición
        // siguiente puede pedir cualquiera de los idiomas que existen en
        // `lang/`. Cargarlos ahora es lo que hace que un `setLocale()` a un
        // tercer idioma encuentre su archivo en vez de devolver claves.
        //
        // El catálogo base va primero y los de demo después: si un demo repite
        // una clave del shell, gana la suya, y con las claves repartidas por
        // demo el resultado es la unión de todo lo instalado.
        foreach (self::availableLocales() as $each) {
            $translator->addResource('php', self::directory() . '/' . $each . '.php', $each);

            foreach (self::demoCatalogs($each) as $demoCatalog) {
                $translator->addResource('php', $demoCatalog, $each);
            }
        }

        return $translator;
    }

    /**
     * Locale configurado de la app: `APP_LOCALE` validado, o `es`.
     *
     * Público porque `LocaleMiddleware` lo necesita en cada petición como
     * default de la resolución, y no puede sacarlo de
     * `$translator->getLocale()`: ese valor cambia con cada request y usarlo de
     * default encadenaría una petición en inglés con la siguiente.
     */
    public static function defaultLocale(): string
    {
        return self::resolve(null, 'APP_LOCALE', self::DEFAULT_LOCALE);
    }

    /**
     * Locales con catálogo: cada `lang/<locale>.php` válido, en orden alfabético.
     *
     * Un demo copia `lang/<locale>/<demo>.php`, que es un directorio y no crea
     * un locale: el selector del pie lista sólo los idiomas con catálogo base.
     *
     * El orden importa: es el que usa `LocaleResolver` cuando `APP_LOCALE` no
     * tiene catálogo. Se lee en cada petición (un `glob` de dos archivos) y no
     * se cachea a propósito: una caché estática es exactamente el tipo de
     * estado que la i18n por request tiene que evitar.
     *
     * @return list<string>
     */
    public static function availableLocales(): array
    {
        $locales = [];

        foreach (glob(self::directory() . '/*.php') ?: [] as $file) {
            $locale = strtolower(basename($file, '.php'));

            if (preg_match(self::LOCALE_PATTERN, $locale) === 1) {
                $locales[] = $locale;
            }
        }

        sort($locales);

        return array_values(array_unique($locales));
    }

    /**
     * Nombre con el que cada idioma se llama a sí mismo.
     *
     * El selector del footer lo muestra en el idioma de cada uno (quien no lee
     * el activo tiene que reconocer el suyo), así que el nombre no puede salir
     * del catálogo activo: `lang/<locale>.php` declara `locale.name` en el
     * idioma que ese archivo traduce. Se lee sólo el catálogo base: un demo no
     * puede renombrar un idioma. Un catálogo sin la clave cae en el código en
     * mayúsculas, que es feo pero legible.
     *
     * @return array<string, string>
     */
    public static function localeNames(): array
    {
        $names = [];

        foreach (self::availableLocales() as $locale) {
            $name = self::catalog($locale)['locale.name'] ?? null;

            $names[$locale] = is_string($name) && trim($name) !== '' ? $name : strtoupper($locale);
        }

        return $names;
    }

    /** Directorio de catálogos: `lang/` en la raíz del proyecto. */
    private static function directory(): string
    {
        return dirname(__DIR__, 2) . '/lang';
    }

    /**
     * Catálogos que los demos instalados aportan a un locale.
     *
     * Cada demo copia `lang/<locale>/<demo>.php` junto con sus vistas y sus
     * rutas, así que el nombre del archivo lo decide el scaffold, no este
     * loader: se leen todos los que haya y en orden alfabético para que el
     * resultado no dependa del orden del `glob`.
     *
     * Se escanea en cada petición, como el catálogo base: instalar o quitar un
     * demo tiene que verse en la petición siguiente, sin cachés que invalidar.
     *
     * @return list<string>
     */
    private static function demoCatalogs(string $locale): array
    {
        // `$locale` viene de `availableLocales()`, que ya lo validó: el valor
        // nunca tiene `/` ni `..`, así que no puede armar otra ruta.
        $files = glob(self::directory() . '/' . $locale . '/*.php') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Catálogo de un locale, o `[]` si el nombre no es un código válido.
     *
     * @return array<string, mixed>
     */
    private static function catalog(string $locale): array
    {
        $file = self::directory() . '/' . $locale . '.php';

        if (preg_match(self::LOCALE_PATTERN, $locale) !== 1 || !is_file($file)) {
            return [];
        }

        $catalog = require $file;

        return is_array($catalog) ? $catalog : [];
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
