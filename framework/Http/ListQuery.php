<?php

declare(strict_types=1);

namespace Framework\Http;

use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Estado de un listado server-rendered leído del query string: búsqueda, orden
 * y filtros, validados contra un whitelist declarado por el listado.
 *
 * La URL es el estado (nada en sesión): `?q=&sort=&dir=&<filtro>` se comparte y
 * se recarga igual. El request nunca aporta nombres de columna: `sort` sólo
 * entra si está en `sort`, un filtro sólo si su nombre está declarado en
 * `filters` y el valor está en su lista, y `dir` se reduce a `asc`/`desc`. El
 * término de búsqueda se recorta y se limita en largo, y sus comodines de `LIKE`
 * se escapan: buscar `100%` busca ese literal, no «todo».
 *
 * No pagina: compone con `Pagination`. El listado arma
 * `$listQuery->url('/tasks')`, lo pasa como `$baseUrl` a `Pagination` y cada
 * enlace de página conserva el estado.
 *
 * La instancia es inmutable: `apply()` muta y devuelve el `Builder` recibido
 * para poder encadenar, pero no este objeto.
 */
final class ListQuery
{
    /**
     * Largo máximo del término, en caracteres (mbstring), después del `trim`.
     *
     * 100 alcanza para una búsqueda escrita a mano y acota el patrón `LIKE` y el
     * trabajo de SQLite; un término más largo se recorta en vez de rechazarse,
     * así el listado sigue respondiendo a lo que el usuario quiso buscar.
     */
    public const MAX_SEARCH_LENGTH = 100;

    public const DEFAULT_DIRECTION = 'asc';

    private const DIRECTIONS = ['asc', 'desc'];

    /**
     * Escapa los comodines de `LIKE` con barra invertida y declara esa barra
     * como `ESCAPE` en el SQL: la barra misma se escapa primero para que un
     * término con `\` no altere el patrón.
     */
    private const LIKE_ESCAPE = '\\';

    /** @var list<string> */
    private array $searchColumns;

    private ?string $search;

    private ?string $defaultSort;

    /** Columna pedida por la URL y aceptada por el whitelist; `null` si no hubo. */
    private ?string $requestSort;

    /** Dirección pedida por la URL y aceptada; `null` si no hubo. */
    private ?string $requestDirection;

    /** Dirección efectiva: la pedida, o la del default, o `asc`. */
    private string $direction;

    /** @var array<string, string> */
    private array $filters;

    /**
     * @param list<string>          $searchColumns
     * @param array<string, string> $filters
     */
    private function __construct(
        array $searchColumns,
        ?string $search,
        ?string $defaultSort,
        ?string $requestSort,
        ?string $requestDirection,
        string $direction,
        array $filters
    ) {
        $this->searchColumns = $searchColumns;
        $this->search = $search;
        $this->defaultSort = $defaultSort;
        $this->requestSort = $requestSort;
        $this->requestDirection = $requestDirection;
        $this->direction = $direction;
        $this->filters = $filters;
    }

    /**
     * Lee el estado del query string y lo valida contra las opciones declaradas.
     *
     * Opciones (todas opcionales):
     * - `search`  => columnas donde buscar el término con `LIKE` (OR entre ellas).
     * - `sort`    => columnas ordenables; `sort` del request sólo entra si está acá.
     * - `default` => `[columna, dirección]` cuando el request no trae `sort` válido.
     * - `filters` => `['status' => ['pendiente', 'completado']]`: sólo esos valores.
     *
     * Un valor fuera de whitelist se ignora (no es un error): la lista se
     * muestra con lo que sí es válido. Un `sort` no whitelisteado cae al default,
     * que es configuración de la pantalla y no dato del request.
     *
     * @param array<string, mixed> $options
     */
    public static function fromRequest(ServerRequestInterface $request, array $options): self
    {
        $params = $request->getQueryParams();

        $searchColumns = self::columns($options['search'] ?? null);
        $sortable = self::columns($options['sort'] ?? null);
        $filterWhitelist = self::filterWhitelist($options['filters'] ?? null);

        $default = is_array($options['default'] ?? null) ? $options['default'] : [];
        $defaultSort = isset($default[0]) && is_string($default[0]) && $default[0] !== ''
            ? $default[0]
            : null;
        $defaultDirection = self::normalizedDirection($default[1] ?? null) ?? self::DEFAULT_DIRECTION;

        $requestSort = self::whitelisted($params['sort'] ?? null, $sortable);
        $requestDirection = self::normalizedDirection($params['dir'] ?? null);

        return new self(
            $searchColumns,
            self::searchTerm($params['q'] ?? null),
            $defaultSort,
            $requestSort,
            $requestDirection,
            $requestDirection ?? $defaultDirection,
            self::acceptedFilters($filterWhitelist, $params)
        );
    }

    /** Término normalizado (recortado y limitado) o `null` si no hay búsqueda. */
    public function search(): ?string
    {
        return $this->search;
    }

    /** Columna de orden efectiva: la pedida si es válida, si no el default. */
    public function sort(): ?string
    {
        return $this->requestSort ?? $this->defaultSort;
    }

    /** Dirección efectiva, siempre `asc` o `desc`. */
    public function direction(): string
    {
        return $this->direction;
    }

    /**
     * Filtros aceptados, `nombre => valor`, en el orden declarado en `filters`.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * ¿La URL trae estado propio? Los defaults no cuentan: sirve para decidir
     * si mostrar el enlace de «limpiar» búsqueda/filtros/orden.
     */
    public function active(): bool
    {
        return $this->search !== null
            || $this->requestSort !== null
            || $this->requestDirection !== null
            || $this->filters !== [];
    }

    /**
     * Aplica búsqueda, filtros y orden al query, en ese orden.
     *
     * El grupo de búsqueda va entre paréntesis y con OR: el término puede
     * aparecer en cualquiera de las columnas declaradas, y ese OR no se escapa
     * del grupo. Sin término no se agrega ninguna cláusula. Devuelve el mismo
     * `Builder` para encadenar `->get()`, `Pagination::paginate()`, etc.
     */
    public function apply(Builder $query): Builder
    {
        $this->applySearch($query);

        foreach ($this->filters as $column => $value) {
            // La columna sale del whitelist, nunca del request; el valor ya
            // pasó por su lista. whereIn evita interpolar el valor.
            $query->whereIn($column, [$value]);
        }

        $sort = $this->sort();
        if ($sort !== null) {
            $query->orderBy($sort, $this->direction);
        }

        return $query;
    }

    /**
     * Ruta con el query string del estado explícito de la URL.
     *
     * Sólo viaja lo que el request aportó y fue aceptado: los defaults no se
     * escriben, así la URL sin estado vuelve a ser la ruta pelada. `$path` ya
     * viene resuelto con el prefijo de despliegue (`Url::path()` / `url()`);
     * este primitivo no conoce el base path. Si la ruta ya trae query string
     * (por ejemplo `?page=2`), el estado se agrega con `&`.
     */
    public function url(string $path): string
    {
        $params = [];

        if ($this->search !== null) {
            $params['q'] = $this->search;
        }
        if ($this->requestSort !== null) {
            $params['sort'] = $this->requestSort;
        }
        if ($this->requestDirection !== null) {
            $params['dir'] = $this->requestDirection;
        }
        foreach ($this->filters as $name => $value) {
            $params[$name] = $value;
        }

        if ($params === []) {
            return $path;
        }

        return $path . self::separator($path) . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function applySearch(Builder $query): void
    {
        if ($this->search === null || $this->searchColumns === []) {
            return;
        }

        $pattern = '%' . str_replace(
            ['\\', '%', '_'],
            [self::LIKE_ESCAPE . self::LIKE_ESCAPE, self::LIKE_ESCAPE . '%', self::LIKE_ESCAPE . '_'],
            $this->search
        ) . '%';

        // El nombre de columna se interpola porque sale del whitelist de
        // opciones: el request jamás llega a esta cadena. El valor y el patrón
        // viajan como bindings.
        $grammar = $query->getQuery()->getGrammar();
        $conditions = [];
        $bindings = [];
        foreach ($this->searchColumns as $column) {
            $conditions[] = $grammar->wrap($column) . " LIKE ? ESCAPE '" . self::LIKE_ESCAPE . "'";
            $bindings[] = $pattern;
        }

        $query->whereRaw('(' . implode(' OR ', $conditions) . ')', $bindings);
    }

    /**
     * Elige el separador de la query según la forma de la ruta base.
     */
    private static function separator(string $path): string
    {
        if (str_ends_with($path, '?') || str_ends_with($path, '&')) {
            return '';
        }

        return str_contains($path, '?') ? '&' : '?';
    }

    /**
     * Lista de nombres de columna válidos: strings no vacíos y sin repetir.
     *
     * @return list<string>
     */
    private static function columns(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $columns = [];
        foreach ($value as $column) {
            if (is_string($column) && $column !== '' && !in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Whitelist de filtros: `['status' => ['pendiente', 'completado']]`.
     *
     * @return array<string, list<string>>
     */
    private static function filterWhitelist(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $whitelist = [];
        foreach ($value as $name => $allowed) {
            if (!is_string($name) || $name === '' || !is_array($allowed)) {
                continue;
            }

            $values = [];
            foreach ($allowed as $candidate) {
                if (is_string($candidate) && !in_array($candidate, $values, true)) {
                    $values[] = $candidate;
                }
            }
            $whitelist[$name] = $values;
        }

        return $whitelist;
    }

    /**
     * Sólo acepta una columna del whitelist; otra cosa (array, typo, `id`)
     * vuelve `null` y deja actuar al default.
     *
     * @param list<string> $whitelist
     */
    private static function whitelisted(mixed $value, array $whitelist): ?string
    {
        return is_string($value) && in_array($value, $whitelist, true) ? $value : null;
    }

    /**
     * Normaliza `dir` a los dos literales permitidos o `null` si no es uno.
     * Lo que no sea `asc`/`desc` no llega nunca al `ORDER BY`.
     */
    private static function normalizedDirection(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return in_array($value, self::DIRECTIONS, true) ? $value : null;
    }

    /**
     * Recorta, limita en largo y descarta lo que no sea un string (un
     * `?q[]=…` no es un término de búsqueda).
     */
    private static function searchTerm(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $term = trim($value);
        if ($term === '') {
            return null;
        }

        return mb_substr($term, 0, self::MAX_SEARCH_LENGTH);
    }

    /**
     * Filtros presentes en el request cuyo nombre está declarado y cuyo valor
     * está en la lista de ese nombre; el resto se ignora en silencio.
     *
     * @param array<string, list<string>> $whitelist
     * @param array<array-key, mixed>     $params
     *
     * @return array<string, string>
     */
    private static function acceptedFilters(array $whitelist, array $params): array
    {
        $filters = [];
        foreach ($whitelist as $name => $allowed) {
            $value = $params[$name] ?? null;
            if (is_string($value) && in_array($value, $allowed, true)) {
                $filters[$name] = $value;
            }
        }

        return $filters;
    }
}
