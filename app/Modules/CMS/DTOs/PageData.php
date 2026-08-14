<?php

declare(strict_types=1);

namespace App\Modules\CMS\DTOs;

use App\Modules\CMS\Enums\PageStatus;
use App\Support\DTOs\Data;
use Illuminate\Http\Request;

readonly class PageData extends Data
{
    /**
     * Every field a page form may carry, in the order they are written.
     */
    public const FIELDS = ['title', 'slug', 'status', 'layout', 'parent_id', 'seo', 'is_homepage', 'published_at'];

    /**
     * @param  array<string, mixed>|null  $seo
     * @param  list<string>  $provided  Request keys that were actually submitted.
     *                                  An absent key means "leave unchanged"; a
     *                                  key submitted as null means "clear".
     */
    public function __construct(
        public string $title,
        public ?string $slug = null,
        public PageStatus $status = PageStatus::Draft,
        public string $layout = 'default',
        public ?int $parentId = null,
        public ?array $seo = null,
        public bool $isHomepage = false,
        public ?string $publishedAt = null,
        public array $provided = self::FIELDS,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $parentId = $request->input('parent_id');
        $seo = $request->input('seo');

        /** @var list<string> $provided */
        $provided = array_values(array_filter(
            self::FIELDS,
            static fn (string $field): bool => $request->has($field),
        ));

        return new self(
            title: (string) $request->string('title'),
            slug: $request->string('slug')->toString() ?: null,
            status: PageStatus::tryFrom((string) $request->string('status')) ?? PageStatus::Draft,
            layout: $request->string('layout', 'default')->toString() ?: 'default',
            parentId: is_numeric($parentId) ? (int) $parentId : null,
            seo: is_array($seo) ? $seo : null,
            isHomepage: $request->boolean('is_homepage'),
            publishedAt: $request->string('published_at')->toString() ?: null,
            provided: $provided,
        );
    }

    public function wasProvided(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /**
     * Attributes for a create. Nulls are written as nulls; on an insert there
     * is nothing to preserve.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'layout' => $this->layout,
            'parent_id' => $this->parentId,
            'seo' => $this->seo,
            'is_homepage' => $this->isHomepage,
            'published_at' => $this->publishedAt,
        ];
    }

    /**
     * Attributes for an edit: only the fields the request actually carried.
     *
     * Without this, the block editor — which autosaves the title alone — would
     * wipe the SEO panel and the parent on every keystroke.
     *
     * @return array<string, mixed>
     */
    public function toUpdateAttributes(): array
    {
        return array_intersect_key($this->toAttributes(), array_flip($this->provided));
    }
}
