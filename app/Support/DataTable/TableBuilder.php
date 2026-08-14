<?php

declare(strict_types=1);

namespace App\Support\DataTable;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Turns an Eloquent query plus the current request into the payload the React
 * DataTable expects: rows, pagination meta, and the echoed table state.
 *
 * Sorting, searching and filtering are all whitelisted through {@see Column}
 * and {@see Filter} definitions, so a crafted query string can never reach an
 * undeclared column.
 *
 * @template TModel of Model
 */
class TableBuilder
{
    /** @var list<Column> */
    protected array $columns = [];

    /** @var list<Filter> */
    protected array $filters = [];

    /** @var Closure(TModel): (array<string, mixed>|Arrayable<string, mixed>)|null */
    protected ?Closure $transformer = null;

    protected ?string $defaultSort = null;

    protected string $defaultDirection = 'desc';

    /**
     * @param  Builder<TModel>  $query
     */
    final public function __construct(
        protected Builder $query,
        protected Request $request,
        protected string $name = 'table',
    ) {}

    /**
     * @template TQueryModel of Model
     *
     * @param  Builder<TQueryModel>  $query
     * @return self<TQueryModel>
     */
    public static function for(Builder $query, ?Request $request = null, string $name = 'table'): self
    {
        return new self($query, $request ?? request(), $name);
    }

    /**
     * @param  list<Column>  $columns
     * @return $this
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @param  list<Filter>  $filters
     * @return $this
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return $this
     */
    public function defaultSort(string $column, string $direction = 'desc'): static
    {
        $this->defaultSort = $column;
        $this->defaultDirection = $direction;

        return $this;
    }

    /**
     * @param  Closure(TModel): (array<string, mixed>|Arrayable<string, mixed>)  $transformer
     * @return $this
     */
    public function transform(Closure $transformer): static
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Build the Inertia payload for this table.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $this->applySearch();
        $this->applyFilters();
        $this->applySort();

        $paginator = $this->query
            ->paginate($this->perPage())
            ->withQueryString();

        return [
            'rows' => $this->rows($paginator),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'columns' => array_map(static fn (Column $column): array => $column->toArray(), $this->columns),
            'filters' => array_map(static fn (Filter $filter): array => $filter->toArray(), $this->filters),
            'state' => [
                'search' => $this->searchTerm(),
                'sort' => $this->sortColumn(),
                'direction' => $this->sortDirection(),
                'per_page' => $this->perPage(),
                'filters' => $this->activeFilters(),
            ],
            'per_page_options' => config('saas.tables.per_page_options'),
        ];
    }

    /**
     * The unpaginated, fully filtered query — used by the export pipeline so an
     * export always reflects exactly what the user is looking at.
     *
     * @return Builder<TModel>
     */
    public function exportQuery(): Builder
    {
        $this->applySearch();
        $this->applyFilters();
        $this->applySort();

        return $this->query;
    }

    /**
     * @param  LengthAwarePaginator<int, TModel>  $paginator
     * @return list<array<string, mixed>>
     */
    protected function rows(LengthAwarePaginator $paginator): array
    {
        $transformer = $this->transformer;

        return $paginator->getCollection()
            ->map(static function (Model $model) use ($transformer): array {
                if (! $transformer instanceof Closure) {
                    return $model->toArray();
                }

                $result = $transformer($model);

                return $result instanceof Arrayable ? $result->toArray() : $result;
            })
            ->values()
            ->all();
    }

    protected function applySearch(): void
    {
        $term = $this->searchTerm();

        if ($term === null || $term === '') {
            return;
        }

        $searchable = array_values(array_filter($this->columns, static fn (Column $c): bool => $c->isSearchable()));

        if ($searchable === []) {
            return;
        }

        $this->query->where(function (Builder $query) use ($searchable, $term): void {
            foreach ($searchable as $column) {
                $target = $column->searchColumn();

                if (str_contains($target, '.')) {
                    [$relation, $field] = explode('.', $target, 2);
                    $query->orWhereHas($relation, static fn (Builder $q) => $q->where($field, 'like', "%{$term}%"));

                    continue;
                }

                $query->orWhere($query->qualifyColumn($target), 'like', "%{$term}%");
            }
        });
    }

    protected function applyFilters(): void
    {
        $active = $this->activeFilters();

        foreach ($this->filters as $filter) {
            if (! array_key_exists($filter->key, $active)) {
                continue;
            }

            $filter->apply($this->query, $active[$filter->key]);
        }
    }

    protected function applySort(): void
    {
        $column = $this->sortColumn();

        if ($column === null) {
            return;
        }

        $definition = $this->columnFor($column);

        if (! $definition instanceof Column || ! $definition->isSortable()) {
            return;
        }

        $this->query->orderBy(
            $this->query->qualifyColumn($definition->sortColumn()),
            $this->sortDirection(),
        );
    }

    protected function columnFor(string $key): ?Column
    {
        foreach ($this->columns as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }

    protected function searchTerm(): ?string
    {
        $term = $this->request->query($this->key('search'));

        return is_string($term) ? Str::limit(trim($term), 100, '') : null;
    }

    protected function sortColumn(): ?string
    {
        $sort = $this->request->query($this->key('sort'));

        return is_string($sort) && $sort !== '' ? $sort : $this->defaultSort;
    }

    protected function sortDirection(): string
    {
        $direction = $this->request->query($this->key('direction'));

        return in_array($direction, ['asc', 'desc'], true) ? $direction : $this->defaultDirection;
    }

    protected function perPage(): int
    {
        $requested = (int) $this->request->query($this->key('per_page'), (string) config('saas.tables.per_page'));

        /** @var list<int> $allowed */
        $allowed = config('saas.tables.per_page_options');

        return in_array($requested, $allowed, true) ? $requested : (int) config('saas.tables.per_page');
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(): array
    {
        $raw = $this->request->query($this->key('filters'));

        if (! is_array($raw)) {
            return [];
        }

        $allowed = array_map(static fn (Filter $filter): string => $filter->key, $this->filters);

        return array_filter(
            array_intersect_key($raw, array_flip($allowed)),
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
        );
    }

    /**
     * Namespaces query-string keys so two tables can coexist on one page.
     */
    protected function key(string $suffix): string
    {
        return $this->name === 'table' ? $suffix : "{$this->name}_{$suffix}";
    }
}
