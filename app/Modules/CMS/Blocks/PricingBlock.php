<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class PricingBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'pricing';
    }

    public function label(): string
    {
        return __('Pricing');
    }

    public function icon(): string
    {
        return 'badge-dollar-sign';
    }

    public function description(): string
    {
        return __('Plan cards with a price, a feature list and a call to action.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::repeater('plans', __('Plans'), [
                BlockField::text('name', __('Name'))->rules('required', 'string', 'max:80'),
                BlockField::text('price', __('Price'))->rules('nullable', 'string', 'max:40'),
                BlockField::text('period', __('Billing period'))->rules('nullable', 'string', 'max:40'),
                BlockField::textarea('description', __('Description'))->rules('nullable', 'string', 'max:300'),

                // A newline-separated list rather than a nested repeater: two
                // levels of drag-and-drop nesting is a worse editing experience
                // than a textarea, and the renderer splits it on render.
                BlockField::textarea('features', __('Features, one per line'))->rules('nullable', 'string', 'max:2000'),

                BlockField::text('cta_label', __('Button label'))->rules('nullable', 'string', 'max:60'),
                BlockField::url('cta_url', __('Button link')),
                BlockField::boolean('featured', __('Highlight this plan')),
            ]),
        ];
    }
}
