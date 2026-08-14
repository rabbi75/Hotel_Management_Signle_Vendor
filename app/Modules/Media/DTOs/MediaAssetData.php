<?php

declare(strict_types=1);

namespace App\Modules\Media\DTOs;

use App\Support\DTOs\Data;
use Illuminate\Http\Request;

/**
 * The editorial metadata a user may set on an asset.
 */
readonly class MediaAssetData extends Data
{
    public const FIELDS = ['name', 'title', 'alt', 'caption', 'tags', 'folder_id'];

    /**
     * @param  list<string>|null  $tags
     * @param  list<string>  $provided  Request keys actually submitted. An absent
     *                                  key means "leave unchanged"; a key sent as
     *                                  null means "clear".
     */
    public function __construct(
        public ?string $name = null,
        public ?string $title = null,
        public ?string $alt = null,
        public ?string $caption = null,
        public ?array $tags = null,
        public ?int $folderId = null,
        public array $provided = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        /** @var list<string>|null $tags */
        $tags = $request->has('tags')
            ? array_values(array_filter(
                array_map(static fn (mixed $tag): string => trim((string) $tag), (array) $request->input('tags', [])),
                static fn (string $tag): bool => $tag !== '',
            ))
            : null;

        $folderId = $request->input('folder_id');

        return new self(
            name: $request->string('name')->toString() ?: null,
            title: $request->string('title')->toString() ?: null,
            alt: $request->string('alt')->toString() ?: null,
            caption: $request->string('caption')->toString() ?: null,
            tags: $tags,
            folderId: is_numeric($folderId) ? (int) $folderId : null,
            provided: $provided,
        );
    }

    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'alt' => $this->alt,
            'caption' => $this->caption,
            'tags' => $this->tags,
            'folder_id' => $this->folderId,
        ];
    }

    /**
     * Only the fields the request carried.
     *
     * The detail drawer saves one section at a time, so an update that never
     * rendered the caption field must not clear the caption.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        $attributes = array_intersect_key($this->toAttributes(), array_flip($this->provided));

        // A blank name is not a deliberate clear: the column is non-null.
        if (array_key_exists('name', $attributes) && $attributes['name'] === null) {
            unset($attributes['name']);
        }

        return $attributes;
    }
}
