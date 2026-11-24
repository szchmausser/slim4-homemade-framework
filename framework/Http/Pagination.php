<?php

declare(strict_types=1);

namespace Framework\Http;

use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Paginación server-side sobre un query Eloquent, sin `illuminate/pagination`.
 *
 * Hace solo lo que la interfaz necesita: cuenta el total, recorta la página pedida
 * y arma la metadata que consume `components/pagination/simple.twig`. No conoce el
 * request; `fromRequest()` es la única puerta que lee el query string, y existe
 * para que los controladores de listado no repitan el cálculo de defaults.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 10;

    /** @var int[] */
    public const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    public const WINDOW_SIZE = 5;

    /**
     * Pagina un query ya construido y devuelve `items` más la metadata del paginador.
     *
     * Clona el query dos veces para que `count()` y `get()` no compartan estado: el
     * `LIMIT` que agrega `forPage()` no debe contaminar el conteo. La página pedida
     * se recorta al rango real, de modo que una URL con `page=999` muestra la última
     * página en lugar de una lista vacía. `$baseUrl` viaja en el resultado porque el
     * paginador lo necesita para armar sus enlaces.
     *
     * @param string $baseUrl destino de los enlaces, ya resuelto con Url::path()
     *
     * @return array{items: \Illuminate\Support\Collection, total: int, per_page: int, current_page: int, last_page: int, from: int, to: int, base_url: string, page_url_base: string, pages: array, allowed_per_page: int[]}
     */
    public static function paginate(Builder $query, int $page, int $perPage, string $baseUrl): array
    {
        $perPage = self::clampPerPage($perPage);

        $total = (int) (clone $query)->count();
        $lastPage = (int) max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $items = (clone $query)->forPage($page, $perPage)->get();

        return [
            'items'        => $items,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
            'from'         => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
            'to'           => $total === 0 ? 0 : min($page * $perPage, $total),
            'base_url'     => $baseUrl,
            // La base ya trae elegido el separador (`?` o `&`): la plantilla
            // agrega `page=…&per_page=…` sin adivinar la forma de la URL.
            'page_url_base'   => self::pageUrlBase($baseUrl),
            'pages'        => self::window($page, $lastPage),
            // Los tamaños viajan con la paginación para que la plantilla no
            // mantenga su propia copia de la lista blanca.
            'allowed_per_page' => self::ALLOWED_PER_PAGE,
        ];
    }

    /**
     * URL de una página concreta, conservando la query string de la base.
     *
     * `$baseUrl` puede venir de `ListQuery::url()` y traer búsqueda, orden o
     * filtros; el enlace no puede perderlos. Para una base sin query string el
     * resultado es idéntico al que la plantilla concatenaba a mano.
     */
    public static function pageUrl(string $baseUrl, int $page, int $perPage): string
    {
        return self::pageUrlBase($baseUrl) . 'page=' . $page . '&per_page=' . $perPage;
    }

    /**
     * Base de los enlaces de página: la URL recibida con el separador de query
     * ya elegido (`?` si no trae query string, `&` si ya trae).
     */
    private static function pageUrlBase(string $baseUrl): string
    {
        if (str_ends_with($baseUrl, '?') || str_ends_with($baseUrl, '&')) {
            return $baseUrl;
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?');
    }

    /**
     * Pagina leyendo `page` y `per_page` del query string.
     *
     * Es el atajo de los controladores de listado: `page` ausente vale 1, `per_page`
     * ausente vale `DEFAULT_PER_PAGE` y el recorte final lo decide `paginate()`. Al
     * centralizar esa lectura, los defaults y los límites no pueden desincronizarse
     * entre pantallas.
     *
     * @param string $baseUrl destino de los enlaces, ya resuelto con Url::path()
     *
     * @return array{items: \Illuminate\Support\Collection, total: int, per_page: int, current_page: int, last_page: int, from: int, to: int, base_url: string, page_url_base: string, pages: array, allowed_per_page: int[]}
     */
    public static function fromRequest(Builder $query, ServerRequestInterface $request, string $baseUrl): array
    {
        $params = $request->getQueryParams();

        return self::paginate(
            $query,
            (int) ($params['page'] ?? 1),
            (int) ($params['per_page'] ?? self::DEFAULT_PER_PAGE),
            $baseUrl
        );
    }

    /**
     * Reduce un tamaño de página pedido por el usuario a uno de la lista blanca.
     *
     * `per_page` llega del query string y puede ser cualquier valor; uno no previsto
     * cae en `DEFAULT_PER_PAGE` en vez de producir un listado enorme o una división
     * por cero en el cálculo de páginas.
     */
    public static function clampPerPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::ALLOWED_PER_PAGE, true)
            ? $value
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * Ventana de `WINDOW_SIZE` páginas consecutivas, centrada en la actual.
     *
     * La ventana se corre para no salirse de los extremos: cerca de la primera o de
     * la última página se muestran igual cinco números. Los saltos largos quedan
     * cubiertos por los enlaces de primera/última del paginador, así que la vista no
     * necesita puntos suspensivos.
     *
     * @return list<int>
     */
    public static function window(int $current, int $last): array
    {
        if ($last <= self::WINDOW_SIZE) {
            return range(1, $last);
        }

        $start = max(1, min($current - 2, $last - self::WINDOW_SIZE + 1));

        return range($start, $start + self::WINDOW_SIZE - 1);
    }
}
