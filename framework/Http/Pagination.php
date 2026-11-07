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
    public const ALLOWED_PER_PAGE = [5, 10, 25, 50, 100];

    public const WINDOW_SIZE = 5;

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
     * Ventana fija de cinco páginas consecutivas, centrada en la actual y
     * recortada en los extremos. Es el mismo patrón de Tabulator: los saltos
     * largos quedan cubiertos por Primera/Anterior/Siguiente/Última, así que
     * no hacen falta puntos suspensivos.
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
