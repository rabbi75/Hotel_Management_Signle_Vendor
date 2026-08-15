<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class OffersBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'offers';
    }

    public function label(): string
    {
        return __('Offers');
    }

    public function icon(): string
    {
        return 'tag';
    }

    public function description(): string
    {
        return __('Stay packages, seasonal rates and highlighted booking offers.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::repeater('items', __('Offers'), [
                BlockField::text('badge', __('Badge'))->rules('nullable', 'string', 'max:40'),
                BlockField::text('title', __('Title'))->rules('required', 'string', 'max:120'),
                BlockField::textarea('description', __('Description'))->rules('nullable', 'string', 'max:500'),
                BlockField::text('price', __('Price'))->rules('nullable', 'string', 'max:60'),
                BlockField::text('price_note', __('Price note'))->rules('nullable', 'string', 'max:80'),
                BlockField::image('image', __('Image')),
                BlockField::text('button_label', __('Button label'))->rules('nullable', 'string', 'max:60'),
                BlockField::url('button_url', __('Button link')),
            ]),
        ];
    }
}
