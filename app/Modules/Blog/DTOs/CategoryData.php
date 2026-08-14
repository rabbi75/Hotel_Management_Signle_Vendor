<?php

declare(strict_types=1);

namespace App\Modules\Blog\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class CategoryData extends Data
{
    /**
     * @param  list<string>  $provided
     */
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public ?int $parentId = null,
        public array $provided = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: (string) $request->string('name'),
            slug: $request->string('slug')->toString() ?: null,
            description: $request->string('description')->toString() ?: null,
            parentId: $request->filled('parent_id') ? (int) $request->input('parent_id') : null,
            provided: array_map(strval(...), array_keys($request->all())),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent_id' => $this->parentId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Re-parenting to the top level and clearing a description are both done by
     * submitting the key with a null value, so those keys survive here.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        $attributes = $this->toAttributes();

        if (in_array('parent_id', $this->provided, true)) {
            $attributes['parent_id'] = $this->parentId;
        }

        if (in_array('description', $this->provided, true)) {
            $attributes['description'] = $this->description;
        }

        return $attributes;
    }
}
