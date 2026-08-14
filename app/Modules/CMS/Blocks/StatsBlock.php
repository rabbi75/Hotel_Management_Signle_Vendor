<?php

declare(strict_types=1);

namespace App\Modules\CMS\Blocks;

class StatsBlock extends BlockSchema
{
    public static function type(): string
    {
        return 'stats';
    }

    public function label(): string
    {
        return __('Stats');
    }

    public function icon(): string
    {
        return 'chart-no-axes-column';
    }

    public function description(): string
    {
        return __('A row of headline numbers.');
    }

    public function fields(): array
    {
        return [
            BlockField::text('heading', __('Heading'))->rules('nullable', 'string', 'max:160'),
            BlockField::repeater('items', __('Stats'), [
                BlockField::text('value', __('Value'))->rules('required', 'string', 'max:40'),
                BlockField::text('label', __('Label'))->rules('required', 'string', 'max:120'),
                BlockField::text('description', __('Description'))->rules('nullable', 'string', 'max:200'),
            ]),
        ];
    }
}
