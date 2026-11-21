<?php

declare(strict_types=1);

namespace Framework\I18n;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traducción desde PHP: el mismo `t()` que Twig, pero para el código que no
 * renderiza una plantilla.
 *
 *     Trans::t('note.created');
 *     Trans::t('pagination.showing', ['%from%' => 1, '%to%' => 10]);
 *     Flash::set('success', Trans::t('note.created'));
 *
 * Existe porque `t()` y `|trans` son extensiones de Twig: un controlador que
 * arma el mensaje de un flash antes de redirigir no tiene plantilla donde
 * pedirlo, así que el texto quedaba escrito en español adentro del PHP y el
 * cambio de idioma se detenía en el borde del cromo. Sigue el patrón de `Flash`
 * y `Url`: un helper estático, sin estado propio más que el traductor.
 *
 * No es un servicio inyectado, y es deliberado. Los controladores de este
 * proyecto no tienen constructor (Slim los resuelve por nombre de clase desde
 * las rutas), así que inyectar el traductor obligaría a darles constructor y a
 * resolverlos por el contenedor: una decisión de arquitectura bastante más
 * grande que este accesor y sin probar acá. El costo del helper estático es que
 * no se puede sustituir sin tocar el registro (ver `register()`), y por eso el
 * último registro gana.
 *
 * El traductor lo registra `config/container.php` al construir
 * `TranslatorInterface` —el mismo objeto que consume Twig, no una segunda
 * copia—. Si nadie lo registró todavía (un script, un comando de consola, un
 * test que no arranca el contenedor), la primera llamada arma el traductor por
 * defecto con `TranslatorFactory::create()`, que ya lee `APP_LOCALE` y
 * `APP_FALLBACK_LOCALE`, y lo deja cacheado: `t()` no explota por falta de
 * registro. El precio de cachearlo es que un cambio de esas variables después
 * de la primera llamada no se nota hasta que el contenedor registre el suyo.
 */
final class Trans
{
    /** Traductor en uso; null hasta que alguien lo registre o se use el fallback. */
    private static ?TranslatorInterface $translator = null;

    /** Clase de accesor: se usa estáticamente. */
    private function __construct()
    {
    }

    /**
     * Registra el traductor de la app. Lo llama `config/container.php`.
     *
     * El último registro gana, y eso es lo que hace útil el accesor en tests y
     * en un eventual «previsualizar otro idioma» sin reiniciar el proceso.
     */
    public static function register(TranslatorInterface $translator): void
    {
        self::$translator = $translator;
    }

    /**
     * Traduce una clave del catálogo al locale activo.
     *
     * Mismos parámetros que en Twig: `%nombre%` en el texto del catálogo y
     * `['%nombre%' => $valor]` acá. Una clave ausente devuelve la clave, como en
     * `t()` de Twig (S5): traducir no puede romper el request.
     *
     * @param array<string, string|int|float> $params
     */
    public static function t(string $key, array $params = []): string
    {
        return self::translator()->trans($key, $params);
    }

    /**
     * Traductor registrado, o el de por defecto la primera vez.
     *
     * El fallback se construye una sola vez: hacerlo en cada `t()` sería
     * rearmar el traductor por cada mensaje de un listado.
     */
    private static function translator(): TranslatorInterface
    {
        return self::$translator ??= TranslatorFactory::create();
    }
}
