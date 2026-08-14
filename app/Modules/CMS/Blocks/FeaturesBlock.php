<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class FeaturesBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'features';
    }

    public function label(): string
    {
        return __('Features');
    }

    public function icon(): string
    {
        return 'layout-grid';
    }

    public function description(): string
    {
        return __('A grid of capabilities, each with an icon and a short description.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::textarea('subheading', __('Subheading'))->rules('nullable', 'string', 'max:500'),
            BlockField::select('columns', __('Columns'))
                ->options(['2' => '2', '3' => '3', '4' => '4'])
                ->rules('nullable', 'in:2,3,4')
                ->default('3'),
            BlockField::repeater('items', __('Features'), [
                BlockField::text('icon', __('Icon'))->rules('nullable', 'string', 'max:60'),
                BlockField::text('title', __('Title'))->rules('required', 'string', 'max:120'),
                BlockField::textarea('description', __('Description'))->rules('nullable', 'string', 'max:500'),
            ]),
        ];
    }
}
