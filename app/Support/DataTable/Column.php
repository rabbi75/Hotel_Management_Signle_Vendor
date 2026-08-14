<?php

declare(strict_types=1);

namespace App\Support\DataTable;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Declarative description of one data-table column.
 *
 * The same definition drives server-side sorting/searching and the column
 * visibility menu in the React table, so the two can never drift apart.
 *
 * @implements Arrayable<string, mixed>
 */
class Column implements Arrayable
{
    protected bool $sortable = false;

    protected bool $searchable = false;

    protected bool $hidden = false;

    protected bool $toggleable = true;

    protected ?string $sortUsing = null;

    protected ?string $searchUsing = null;

    protected string $align = 'left';

    protected ?string $width = null;

    final public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {}

    public static function make(string $key, ?string $label = null): static
    {
        return new static($key, $label ?? str($key)->afterLast('.')->headline()->toString());
    }

    /**
     * @param  string|null  $column  Database column to order by, when it differs from the key.
     */
    public function sortable(?string $column = null): static
    {
        $this->sortable = true;
        $this->sortUsing = $column;

        return $this;
    }

    /**
     * @param  string|null  $column  Database column (or `relation.column`) to match against.
     */
    public function searchable(?string $column = null): static
    {
        $this->searchable = true;
        $this->searchUsing = $column;

        return $this;
    }

    /**
     * Present in the column menu but not rendered until the user enables it.
     */
    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Always rendered; removed from the column visibility menu.
     */
    public function locked(): static
    {
        $this->toggleable = false;
        $this->hidden = false;

        return $this;
    }

    public function align(string $align): static
    {
        $this->align = $align;

        return $this;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function sortColumn(): string
    {
        return $this->sortUsing ?? $this->key;
    }

    public function searchColumn(): string
    {
        return $this->searchUsing ?? $this->key;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'sortable' => $this->sortable,
            'hidden' => $this->hidden,
            'toggleable' => $this->toggleable,
            'align' => $this->align,
            'width' => $this->width,
        ];
    }
}
