<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

use App\Modules\Blog\Services\HtmlSanitizer;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The contract every page block satisfies.
 *
 * Adding a block type to the kit is one subclass here plus one React component
 * named after its `type()`; nothing else is edited. The registry discovers the
 * class, the editor renders its fields, and the renderer looks up the matching
 * component by type.
 *
 * @implements Arrayable<string, mixed>
 */
abstract class BlockSchema implements Arrayable
{
    /**
     * Stable identifier, stored on every row of `page_blocks`. Never rename one
     * without a migration: existing rows carry the old value.
     */
    abstract public static function type(): string;

    abstract public function label(): string;

    /**
     * @return list<BlockField>
     */
    abstract public function fields(): array;

    public function icon(): string
    {
        return 'square';
    }

    public function description(): string
    {
        return '';
    }

    /**
     * The layout controls every block carries, on top of its own content fields.
     *
     * Declared once here rather than merged into each subclass: they are not
     * what a block *is*, they are how any section sits on a page. A block that
     * genuinely cannot be laid out — none today — overrides this with `[]`.
     *
     * Names are prefixed so they can never collide with a content field; the
     * `richtext` block, for instance, already has a `width` of its own.
     *
     * @return list<BlockField>
     */
    public function sectionFields(): array
    {
        return [
            BlockField::select('section_background', __('Background'))
                ->options([
                    'default' => __('Default'),
                    'muted' => __('Muted'),
                    'accent' => __('Accent'),
                    'gradient' => __('Gradient'),
                    'dark' => __('Dark'),
                ])
                ->rules('nullable', 'in:default,muted,accent,gradient,dark')
                ->default('default')
                ->group(BlockField::GROUP_SECTION),

            BlockField::select('section_padding', __('Vertical space'))
                ->options([
                    'none' => __('None'),
                    'compact' => __('Compact'),
                    'normal' => __('Normal'),
                    'spacious' => __('Spacious'),
                ])
                ->rules('nullable', 'in:none,compact,normal,spacious')
                ->default('normal')
                ->group(BlockField::GROUP_SECTION),

            BlockField::select('section_width', __('Content width'))
                ->options([
                    'narrow' => __('Narrow'),
                    'normal' => __('Normal'),
                    'wide' => __('Wide'),
                    'full' => __('Full bleed'),
                ])
                ->rules('nullable', 'in:narrow,normal,wide,full')
                ->default('normal')
                ->group(BlockField::GROUP_SECTION),

            BlockField::text('section_id', __('Anchor id'))
                ->rules('nullable', 'string', 'max:80')
                ->help(__('Used for in-page links such as /#rooms.'))
                ->group(BlockField::GROUP_SECTION),
        ];
    }

    /**
     * Content fields followed by layout fields — the single list that defaults,
     * rules, sanitising and the editor payload are all derived from.
     *
     * @return list<BlockField>
     */
    public function allFields(): array
    {
        return [...$this->fields(), ...$this->sectionFields()];
    }

    /**
     * The `data` payload written when this block is first added to a page.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->allFields() as $field) {
            $defaults[$field->name] = $field->defaultValue();
        }

        return $defaults;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(string $prefix = 'data'): array
    {
        $rules = [];

        foreach ($this->allFields() as $field) {
            $rules = [...$rules, ...$field->validationRules($prefix)];
        }

        return $rules;
    }

    /**
     * Drop anything the schema does not declare.
     *
     * A block's stored data is user input that has already been through
     * validation once; re-filtering on read means a schema that loses a field
     * stops emitting it rather than leaking a stale key into the renderer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitise(array $data): array
    {
        $clean = [];

        foreach ($this->allFields() as $field) {
            $clean[$field->name] = $this->sanitiseField($field, $data[$field->name] ?? $field->defaultValue());
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => static::type(),
            'label' => $this->label(),
            'icon' => $this->icon(),
            'description' => $this->description(),
            'defaults' => $this->defaults(),
            'fields' => array_map(static fn (BlockField $field): array => $field->toArray(), $this->allFields()),
        ];
    }

    /**
     * Per-field cleaning, applied on every read and every write.
     *
     * A `richtext` value is HTML the author composed in their own browser, so it
     * is passed through the same sanitiser the blog module uses rather than
     * being trusted: block data reaches the public page renderer, and a stored
     * `<script>` would run for every visitor.
     */
    protected function sanitiseField(BlockField $field, mixed $value): mixed
    {
        if ($field->type === 'richtext' && is_string($value)) {
            return app(HtmlSanitizer::class)->sanitize($value);
        }

        return $value;
    }
}
