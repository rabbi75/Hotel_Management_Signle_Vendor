<?php

declare(strict_types=1);

namespace App\Modules\Search\Contracts;

use Illuminate\Support\Collection;

/**
 * One searchable domain.
 *
 * A provider owns its own permission check and its own tenancy: the aggregator
 * decides *whether* to call it, never *what* it is allowed to return.
 */
interface SearchProvider
{
    /**
     * Stable identifier, e.g. `users`.
     */
    public function key(): string;

    /**
     * The heading this provider's results appear under.
     */
    public function label(): string;

    public function icon(): string;

    /**
     * Permission required to search this domain, or null when unrestricted.
     */
    public function permission(): ?string;

    /**
     * Matches for a term, already constrained to the active workspace.
     *
     * @return Collection<int, mixed>
     */
    public function search(string $term, int $limit): Collection;

    /**
     * Present one match as a palette row.
     *
     * @return array{id: string, title: string, subtitle: string|null, icon: string|null, url: string|null, group: string}
     */
    public function toResult(mixed $model): array;
}
