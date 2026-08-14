<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class RoomsBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'rooms';
    }

    public function label(): string
    {
        return __('Rooms');
    }

    public function icon(): string
    {
        return 'bed-double';
    }

    public function description(): string
    {
        return __('Live room types with tonight’s availability and a book button.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160')->default(__('Rooms')),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::text('button_label', __('Button label'))->rules('nullable', 'string', 'max:60')->default(__('Book this room')),
        ];
    }
}
