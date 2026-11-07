<?php

declare(strict_types=1);

namespace Framework\Http;

use Illuminate\Database\Eloquent\Builder;

/**
 * Paginación estilo Laravel sin illuminate/pagination (ese paquete exige
 * php ^8.3 y subiría el piso del proyecto; acá son las mismas 2 queries que
 * hace paginate() por dentro: count + slice). Todo testeable sin HTTP.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 10;

    /** @var int[] */
    public const ALLOWED_PER_PAGE = [5, 10, 15, 25, 50];

    /**
     * Pagina un query Eloquent. Devuelve items + metadata lista para
     * components/pagination/simple.twig. $page fuera de rango se clampena (nunca vacío
     * por pedir de más).
     *
     * @return array{items: \Illuminate\Support\Collection, total: int, per_page: int, current_page: int, last_page: int, from: int, to: int, base_url: string, pages: array, allowed_per_page: int[]}
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
            'pages'        => self::window($page, $lastPage),
            // Los tamanos viajan con la paginacion para que el template no
            // mantenga su propia copia de la lista.
            'allowed_per_page' => self::ALLOWED_PER_PAGE,
        ];
    }

    public static function clampPerPage(mixed $value): int
    {
        $value = (int) $value;

        return in_array($value, self::ALLOWED_PER_PAGE, true)
            ? $value
            : self::DEFAULT_PER_PAGE;
    }

    /**
     * Ventana de páginas con techo de CINCO entradas: primera, actual y
     * última, más un ellipsis (null = "…") en cada salto. Con last <= 5
     * entran todas y no hay nada que saltear.
     *
     * Los vecinos de la actual NO se muestran a propósito: llegar a la
     * anterior/siguiente lo cubren los botones Anterior/Siguiente, que están siempre. La
     * regla vieja (current±2, hasta nueve botones) pasaba el techo de cuatro
     * opciones visibles que aguanta un punto de decisión (R7).
     *
     * La deduplicación sale de las guardas, no de array_unique: current == 1
     * o current == last no repiten el número porque el `if` que lo agrega lo
     * excluye. array_unique con comparación laxa borraría además un ellipsis
     * doble, que es válido cuando current está lejos de los dos extremos.
     *
     * @return array<int|null>
     */
    public static function window(int $current, int $last): array
    {
        if ($last <= 5) {
            return range(1, $last);
        }

        $pages = [1];

        if ($current > 2) {
            $pages[] = null;
        }

        if ($current > 1 && $current < $last) {
            $pages[] = $current;
        }

        if ($current < $last - 1) {
            $pages[] = null;
        }

        $pages[] = $last;

        return $pages;
    }
}
