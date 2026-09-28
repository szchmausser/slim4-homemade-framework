<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Paginación estilo Laravel sin illuminate/pagination (ese paquete exige
 * php ^8.3 y subiría el piso del proyecto; acá son las mismas 2 queries que
 * hace paginate() por dentro: count + slice). Todo testeable sin HTTP.
 */
final class Pagination
{
    public const DEFAULT_PER_PAGE = 5;

    /** @var int[] */
    public const ALLOWED_PER_PAGE = [10, 15, 25, 50];

    /**
     * Pagina un query Eloquent. Devuelve items + metadata lista para
     * partials/_pagination.twig. $page fuera de rango se clampena (nunca vacío
     * por pedir de más).
     *
     * @return array{items: \Illuminate\Support\Collection, total: int, per_page: int, current_page: int, last_page: int, from: int, to: int, base_url: string, pages: array}
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
     * Ventana de páginas estilo Laravel: primera, última y actual±2.
     * null = ellipsis ("…"). Los números no pueden repetirse por construcción
     * (el rango va de 2 a last-1), así que no se deduplica: array_unique con
     * comparación laxa borraría un ellipsis doble, que es válido.
     *
     * @return array<int|null>
     */
    public static function window(int $current, int $last): array
    {
        if ($last <= 7) {
            return range(1, $last);
        }

        $pages = [1];

        if ($current > 4) {
            $pages[] = null;
        }

        foreach (range(max(2, $current - 2), min($last - 1, $current + 2)) as $p) {
            $pages[] = $p;
        }

        if ($current < $last - 3) {
            $pages[] = null;
        }

        $pages[] = $last;

        return $pages;
    }
}
