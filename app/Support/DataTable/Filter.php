<?php

declare(strict_types=1);

namespace App\Support\DataTable;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;

/**
 * A named, whitelisted filter a data table exposes to the client.
 *
 * Filters are always declared server-side: the request may only reference a key
 * that exists here, which keeps arbitrary user input out of the query builder.
 *
 * @implements Arrayable<string, mixed>
 */
class Filter implements Arrayable
{
    /** @var Closure(Builder<covariant \Illuminate\Database\Eloquent\Model>, mixed): void|null */
    protected ?Closure $using = null;

    /** @var list<array{value: string, label: string}> */
    protected array $options = [];

    protected string $type = 'select';

    protected bool $multiple = false;

    protected ?string $column = null;

    final public function __construct(
        public readonly string $key,
        public readonly string $label,
    ) {}

    public static function make(string $key, ?string $label = null): static
    {
        return new static($key, $label ?? str($key)->headline()->toString());
    }

    /**
     * @param  Closure(Builder<covariant \Illuminate\Database\Eloquent\Model>, mixed): void  $callback
     */
    public function using(Closure $callback): static
    {
        $this->using = $callback;

        return $this;
    }

    public function column(string $column): static
    {
        $this->column = $column;

        return $this;
    }

    /**
     * @param  array<string, string>|list<array{value: string, label: string}>  $options
     */
    public function options(array $options): static
    {
        $this->options = array_is_list($options)
            ? $options
            : array_map(
                static fn (string $label, string $value): array => ['value' => $value, 'label' => $label],
                array_values($options),
                array_map(strval(...), array_keys($options)),
            );

        return $this;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    public function fromEnum(string $enum): static
    {
        return $this->options(array_map(
            static fn (BackedEnum $case): array => [
                'value' => (string) $case->value,
                'label' => method_exists($case, 'label') ? $case->label() : str($case->name)->headline()->toString(),
            ],
            $enum::cases(),
        ));
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function boolean(): static
    {
        return $this->type('boolean')->options(['Yes' => '1', 'No' => '0']);
    }

    public function dateRange(): static
    {
        return $this->type('date_range');
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public function apply(Builder $query, mixed $value): void
    {
        if ($this->using instanceof Closure) {
            ($this->using)($query, $value);

            return;
        }

        $column = $query->qualifyColumn($this->column ?? $this->key);

        match (true) {
            $this->type === 'date_range' && is_array($value) => $this->applyDateRange($query, $column, $value),
            is_array($value) => $query->whereIn($column, $value),
            $this->type === 'boolean' => $query->where($column, filter_var($value, FILTER_VALIDATE_BOOL)),
            default => $query->where($column, $value),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'multiple' => $this->multiple,
            'options' => $this->options,
        ];
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array{from?: string|null, to?: string|null}  $value
     */
    protected function applyDateRange(Builder $query, string $column, array $value): void
    {
        if (! empty($value['from'])) {
            $query->whereDate($column, '>=', $value['from']);
        }

        if (! empty($value['to'])) {
            $query->whereDate($column, '<=', $value['to']);
        }
    }
}
