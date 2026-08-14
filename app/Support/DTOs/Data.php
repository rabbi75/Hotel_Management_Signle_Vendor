<?php

declare(strict_types=1);

namespace App\Support\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Base for the readonly data objects that cross layer boundaries.
 *
 * Actions and services accept DTOs rather than arrays so that the shape of
 * their input is enforced by the type system instead of by convention.
 *
 * @implements Arrayable<string, mixed>
 */
abstract readonly class Data implements Arrayable
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            get_object_vars($this),
            static fn (mixed $value): bool => $value !== null,
        );
    }

    /**
     * All properties including nulls — used when a null must be persisted as a
     * deliberate "clear this field" rather than "leave unchanged".
     *
     * @return array<string, mixed>
     */
    public function toArrayWithNulls(): array
    {
        return get_object_vars($this);
    }
}
