<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class LocationBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'location';
    }

    public function label(): string
    {
        return __('Location');
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function description(): string
    {
        return __('Map, address, hours and nearby landmarks for a hotel landing page.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::textarea('address', __('Address'))->rules('nullable', 'string', 'max:500'),
            BlockField::text('hours', __('Hours'))->rules('nullable', 'string', 'max:200'),
            BlockField::url('map_embed_url', __('Map embed URL'))
                ->help(__('Paste a Google Maps embed URL (the src of the iframe).')),
            BlockField::url('directions_url', __('Directions link')),
            BlockField::repeater('landmarks', __('Nearby'), [
                BlockField::text('title', __('Place'))->rules('required', 'string', 'max:120'),
                BlockField::text('distance', __('Distance'))->rules('nullable', 'string', 'max:60'),
            ]),
        ];
    }
}
